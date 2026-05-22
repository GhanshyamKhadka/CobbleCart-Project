-- ============================================
-- ORDS REST SERVICES FOR COBBLECART
-- Run this in SQL Developer / SQL*Plus
-- Connected as: COBBLEUSER (or your app schema)
-- ============================================

-- Note: ORDS modules must be created with proper PL/SQL handlers
-- This creates the REST endpoints and their backend logic

BEGIN
  ORDS.DROP_RESTFUL_SERVICE (
    p_service   => 'cobblecart_api'
  );
EXCEPTION
  WHEN OTHERS THEN
    NULL;
END;
/

-- Create REST Module
BEGIN
  ORDS.CREATE_MODULE(
    p_module_name    => 'cobblecart_api',
    p_base_path      => '/cobbleuser/',
    p_pattern        => '.*',
    p_items_per_page => 0,
    p_status         => 'PUBLISHED',
    p_comments       => 'CobbleCart API'
  );
  COMMIT;
END;
/

-- ============================================
-- LOGIN HANDLER
-- ============================================
BEGIN
  ORDS.DEFINE_HANDLER(
    p_module_name    => 'cobblecart_api',
    p_pattern        => 'login',
    p_method         => 'POST',
    p_source_type    => 'plsql/block',
    p_source         => q'[
DECLARE
    v_email VARCHAR2(100);
    v_password VARCHAR2(255);
    v_user Users%ROWTYPE;
    v_role VARCHAR2(20);
    v_shop CLOB;
BEGIN
    -- Get input parameters
    v_email := :email;
    v_password := :password;

    -- Find user by email
    BEGIN
        SELECT * INTO v_user 
        FROM Users 
        WHERE email = v_email;
    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            :status := 401;
            HTP.PRINT('{"success":false,"message":"Invalid email or password"}');
            RETURN;
    END;

    -- Check password (plain text for now - upgrade to hashed later)
    IF v_user.password <> v_password THEN
        :status := 401;
        HTP.PRINT('{"success":false,"message":"Invalid email or password"}');
        RETURN;
    END IF;

    v_role := LOWER(v_user.role);

    -- Build shop info for traders
    IF v_role = ''trader'' THEN
        BEGIN
            SELECT JSON_OBJECT(
                ''shop_id'' VALUE s.shop_id,
                ''shop_name'' VALUE s.shop_name,
                ''shop_type'' VALUE s.shop_type,
                ''status'' VALUE s.status
            ).to_string INTO v_shop
            FROM Shop s 
            WHERE s.user_id = v_user.user_id;
        EXCEPTION
            WHEN OTHERS THEN
                v_shop := NULL;
        END;
    END IF;

    -- Return success response
    :status := 200;
    HTP.PRINT(JSON_OBJECT(
        ''success'' VALUE TRUE,
        ''message'' VALUE ''Login successful'',
        ''user_id'' VALUE v_user.user_id,
        ''name'' VALUE v_user.first_name || '' '' || NVL(v_user.last_name, ''''),
        ''email'' VALUE v_user.email,
        ''role'' VALUE v_role,
        ''extra'' VALUE v_shop
    ).to_string);

EXCEPTION
    WHEN OTHERS THEN
        :status := 500;
        HTP.PRINT(''{"success":false,"message":"Login failed"}'');
END;
    ]'
  );
  COMMIT;
END;
/

-- ============================================
-- REGISTER HANDLER
-- ============================================
BEGIN
  ORDS.DEFINE_HANDLER(
    p_module_name    => 'cobblecart_api',
    p_pattern        => 'register',
    p_method         => 'POST',
    p_source_type    => 'plsql/block',
    p_source         => q'[
DECLARE
    v_name VARCHAR2(120);
    v_email VARCHAR2(100);
    v_password VARCHAR2(255);
    v_role VARCHAR2(20);
    v_shop_name VARCHAR2(100);
    v_shop_type VARCHAR2(50);
    v_user_id NUMBER;
    v_shop_id NUMBER;
    v_count NUMBER;
BEGIN
    v_name := :name;
    v_email := :email;
    v_password := :password;
    v_role := UPPER(NVL(:role, ''CUSTOMER''));
    v_shop_name := :shop_name;
    v_shop_type := :shop_type;

    -- Check if email exists
    SELECT COUNT(*) INTO v_count FROM Users WHERE email = v_email;
    IF v_count > 0 THEN
        :status := 409;
        HTP.PRINT(''{"success":false,"message":"Email already registered"}'');
        RETURN;
    END IF;

    -- Create user
    v_user_id := seq_user.NEXTVAL;
    INSERT INTO Users (user_id, first_name, last_name, email, username, password, role, created_date)
    VALUES (v_user_id, SUBSTR(v_name, 1, INSTR(v_name, '' '')-1), SUBSTR(v_name, INSTR(v_name, '' '')+1), v_email, SUBSTR(v_email, 1, 50), v_password, v_role, SYSDATE);

    -- Create role-specific records
    IF v_role = ''CUSTOMER'' THEN
        INSERT INTO Customer (user_id, loyalty_points) VALUES (v_user_id, 0);
    ELSIF v_role = ''TRADER'' THEN
        INSERT INTO Trader (user_id, business_name) VALUES (v_user_id, v_shop_name);
        v_shop_id := seq_shop.NEXTVAL;
        INSERT INTO Shop (shop_id, user_id, shop_name, shop_type, status) 
        VALUES (v_shop_id, v_user_id, v_shop_name, v_shop_type, ''ACTIVE'');
    ELSIF v_role = ''ADMIN'' THEN
        INSERT INTO Admin (user_id) VALUES (v_user_id);
    END IF;

    COMMIT;
    :status := 200;
    HTP.PRINT(JSON_OBJECT(''success'' VALUE TRUE, ''message'' VALUE ''Account created'', ''user_id'' VALUE v_user_id).to_string);

EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        :status := 500;
        HTP.PRINT(''{"success":false,"message":"Registration failed"}'');
END;
    ]'
  );
  COMMIT;
END;
/

-- ============================================
-- PLACE ORDER HANDLER
-- ============================================
BEGIN
  ORDS.DEFINE_HANDLER(
    p_module_name    => 'cobblecart_api',
    p_pattern        => 'place-order',
    p_method         => 'POST',
    p_source_type    => 'plsql/block',
    p_source         => q'[
DECLARE
    v_customer_id NUMBER;
    v_slot_id NUMBER;
    v_payment VARCHAR2(50);
    v_items CLOB;
    v_total NUMBER := 0;
    v_order_id NUMBER;
    v_product_id NUMBER;
    v_qty NUMBER;
    v_price NUMBER;
    v_subtotal NUMBER;
BEGIN
    v_customer_id := :customer_id;
    v_slot_id := :slot_id;
    v_payment := :payment_method;
    v_items := :items;

    IF v_customer_id IS NULL OR v_slot_id IS NULL THEN
        :status := 400;
        HTP.PRINT(''{"success":false,"message":"Customer and slot required"}'');
        RETURN;
    END IF;

    v_order_id := seq_order.NEXTVAL;

    -- Insert order
    INSERT INTO Customer_Order (order_id, user_id, slot_id, order_date, total_amount, status)
    VALUES (v_order_id, v_customer_id, v_slot_id, SYSDATE, v_total, ''PLACED'');

    COMMIT;
    :status := 200;
    HTP.PRINT(JSON_OBJECT(''success'' VALUE TRUE, ''message'' VALUE ''Order placed'', ''order_id'' VALUE v_order_id).to_string);

EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        :status := 500;
        HTP.PRINT(''{"success":false,"message":"Order failed"}'');
END;
    ]'
  );
  COMMIT;
END;
/

-- ============================================
-- LOGOUT HANDLER
-- ============================================
BEGIN
  ORDS.DEFINE_HANDLER(
    p_module_name    => 'cobblecart_api',
    p_pattern        => 'logout',
    p_method         => 'POST',
    p_source_type    => 'plsql/block',
    p_source         => q'[
BEGIN
    :status := 200;
    HTP.PRINT(''{"success":true,"message":"Logged out successfully"}'');
END;
    ]'
  );
  COMMIT;
END;
/

COMMIT;
DBMS_OUTPUT.PUT_LINE('ORDS REST services created successfully');
