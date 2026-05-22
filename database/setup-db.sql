-- ============================================
-- CobbleCart — one-shot DB setup (targets the COBBLECART schema
-- so what you do via the website appears in the same APEX workspace).
-- ============================================
-- Run from PowerShell using OS authentication (no service name needed):
--
--   sqlplus / as sysdba "@c:\Users\ASUS\OneDrive\Desktop\cobblecart\cobblecart\database\setup-db.sql"
--
-- This script:
--   1. Auto-detects your PDB (Oracle 23c Free => FREEPDB1, XE => XEPDB1)
--   2. Opens that PDB and saves state (auto-open after restarts)
--   3. Resets COBBLECART's password to Oracle#12345@ (the password api/config.php uses)
--   4. Grants COBBLECART what it needs
--   5. Adds the missing Users.email_verified column if needed
--   6. Backfills existing user rows to email_verified='YES'
--   7. Recompiles the trigger that referenced the missing column (if present)
-- Idempotent — safe to re-run.
-- ============================================

SET SERVEROUTPUT ON SIZE UNLIMITED
SET FEEDBACK OFF
SET VERIFY OFF
WHENEVER SQLERROR CONTINUE

VARIABLE pdb_name VARCHAR2(128)

-- 1. Find + open the user PDB
DECLARE
    v_pdb VARCHAR2(128);
    v_mode VARCHAR2(20);
BEGIN
    SELECT name, open_mode INTO v_pdb, v_mode
    FROM v$pdbs
    WHERE name <> 'PDB$SEED'
    AND ROWNUM = 1
    ORDER BY name;

    DBMS_OUTPUT.PUT_LINE('Found PDB: ' || v_pdb || '  (current mode: ' || v_mode || ')');

    IF v_mode <> 'READ WRITE' THEN
        EXECUTE IMMEDIATE 'ALTER PLUGGABLE DATABASE ' || v_pdb || ' OPEN';
        DBMS_OUTPUT.PUT_LINE('Opened ' || v_pdb || '.');
    END IF;

    EXECUTE IMMEDIATE 'ALTER PLUGGABLE DATABASE ' || v_pdb || ' SAVE STATE';
    DBMS_OUTPUT.PUT_LINE(v_pdb || ' will now auto-open after every Oracle restart.');

    :pdb_name := v_pdb;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        DBMS_OUTPUT.PUT_LINE('!! No PDBs found.');
END;
/

COLUMN pdb_name NEW_VALUE pdb_name_str NOPRINT
SELECT :pdb_name AS pdb_name FROM DUAL;
ALTER SESSION SET CONTAINER = &pdb_name_str;

-- 2. Ensure COBBLECART exists with the password api/config.php expects
DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM dba_users WHERE username = 'COBBLECART';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'CREATE USER COBBLECART IDENTIFIED BY "Oracle#12345@"';
        DBMS_OUTPUT.PUT_LINE('Created COBBLECART.');
    ELSE
        EXECUTE IMMEDIATE 'ALTER USER COBBLECART IDENTIFIED BY "Oracle#12345@" ACCOUNT UNLOCK';
        DBMS_OUTPUT.PUT_LINE('COBBLECART password reset to Oracle#12345@, account unlocked.');
    END IF;
END;
/

GRANT CONNECT, RESOURCE TO COBBLECART;
ALTER USER COBBLECART QUOTA UNLIMITED ON USERS;

-- 3. Make sure Users.email_verified exists
DECLARE
    v_has_table NUMBER;
    v_has_col NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_has_table
    FROM dba_tables WHERE owner = 'COBBLECART' AND table_name = 'USERS';

    IF v_has_table = 0 THEN
        DBMS_OUTPUT.PUT_LINE('COBBLECART.Users does not exist yet. Run oracle-complete-schema.sql as COBBLECART next.');
        RETURN;
    END IF;

    SELECT COUNT(*) INTO v_has_col
    FROM dba_tab_columns
    WHERE owner = 'COBBLECART' AND table_name = 'USERS' AND column_name = 'EMAIL_VERIFIED';

    IF v_has_col = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE COBBLECART.Users ADD email_verified VARCHAR2(3) DEFAULT ''NO''';
        DBMS_OUTPUT.PUT_LINE('Added COBBLECART.Users.email_verified.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('COBBLECART.Users.email_verified already exists.');
    END IF;

    EXECUTE IMMEDIATE 'UPDATE COBBLECART.Users SET email_verified = ''YES''
                       WHERE email_verified IS NULL OR email_verified = ''NO''';
    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Backfilled existing rows to email_verified=YES.');
END;
/

-- 3a. Make sure USERS has PROFILE_IMAGE
DECLARE
    v_has_col NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_has_col
    FROM dba_tab_columns
    WHERE owner = 'COBBLECART' AND table_name = 'USERS' AND column_name = 'PROFILE_IMAGE';

    IF v_has_col = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE COBBLECART.Users ADD profile_image VARCHAR2(255)';
        DBMS_OUTPUT.PUT_LINE('Added COBBLECART.Users.profile_image.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('COBBLECART.Users.profile_image already exists.');
    END IF;
END;
/

-- 3b. Make sure PRODUCT has product_image, approval_status, created_at, offer_percent
DECLARE
    v_has_table NUMBER;
    PROCEDURE ensure_col(p_col VARCHAR2, p_ddl VARCHAR2) IS
        v_n NUMBER;
    BEGIN
        SELECT COUNT(*) INTO v_n FROM dba_tab_columns
        WHERE owner = 'COBBLECART' AND table_name = 'PRODUCT' AND column_name = p_col;
        IF v_n = 0 THEN
            EXECUTE IMMEDIATE 'ALTER TABLE COBBLECART.Product ADD ' || p_ddl;
            DBMS_OUTPUT.PUT_LINE('Added COBBLECART.Product.' || p_col || '.');
        ELSE
            DBMS_OUTPUT.PUT_LINE('COBBLECART.Product.' || p_col || ' already exists.');
        END IF;
    END;
BEGIN
    SELECT COUNT(*) INTO v_has_table
    FROM dba_tables WHERE owner = 'COBBLECART' AND table_name = 'PRODUCT';

    IF v_has_table = 0 THEN
        DBMS_OUTPUT.PUT_LINE('COBBLECART.Product does not exist yet. Skipping product migration.');
        RETURN;
    END IF;

    ensure_col('PRODUCT_IMAGE',   'product_image VARCHAR2(255)');
    ensure_col('APPROVAL_STATUS', 'approval_status VARCHAR2(20) DEFAULT ''APPROVED''');
    ensure_col('CREATED_AT',      'created_at DATE DEFAULT SYSDATE');
    ensure_col('OFFER_PERCENT',   'offer_percent NUMBER(5,2) DEFAULT 0');

    -- Backfill: rows existing before the column was added have NULL approval_status,
    -- which would hide them from customers (filter is WHERE approval_status='APPROVED').
    EXECUTE IMMEDIATE 'UPDATE COBBLECART.Product SET approval_status = ''APPROVED''
                       WHERE approval_status IS NULL';
    EXECUTE IMMEDIATE 'UPDATE COBBLECART.Product SET offer_percent = 0
                       WHERE offer_percent IS NULL';
    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Backfilled COBBLECART.Product approval and offer defaults on null rows.');
END;
/

-- 3c. Recalculate order item subtotals from the current product offer price.
DECLARE
    v_product_table NUMBER;
    v_order_item_table NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_product_table FROM dba_tables
    WHERE owner = 'COBBLECART' AND table_name = 'PRODUCT';
    SELECT COUNT(*) INTO v_order_item_table FROM dba_tables
    WHERE owner = 'COBBLECART' AND table_name = 'ORDER_ITEM';

    IF v_product_table = 0 OR v_order_item_table = 0 THEN
        DBMS_OUTPUT.PUT_LINE('Skipping subtotal trigger rebuild; product or order_item table is missing.');
        RETURN;
    END IF;

    EXECUTE IMMEDIATE q'[
        CREATE OR REPLACE TRIGGER COBBLECART.TRG_CALC_SUBTOTAL
        BEFORE INSERT OR UPDATE ON COBBLECART.ORDER_ITEM
        FOR EACH ROW
        DECLARE
            V_PRICE COBBLECART.PRODUCT.PRICE%TYPE;
        BEGIN
            SELECT CASE
                       WHEN NVL(OFFER_PERCENT, 0) > 0 THEN ROUND(PRICE * (100 - OFFER_PERCENT) / 100, 2)
                       ELSE PRICE
                   END
            INTO V_PRICE
            FROM COBBLECART.PRODUCT
            WHERE PRODUCT_ID = :NEW.PRODUCT_ID;

            :NEW.SUBTOTAL := V_PRICE * :NEW.QUANTITY;
        END;
    ]';
    DBMS_OUTPUT.PUT_LINE('Recreated TRG_CALC_SUBTOTAL with product offer pricing.');
END;
/

-- 4. Recreate the customer-check trigger with correct case-insensitive comparison.
--    Old deployed version used 'Customer' (mixed case) and rejected every order.
DECLARE
    v_has_table NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_has_table FROM dba_tables
    WHERE owner = 'COBBLECART' AND table_name = 'CUSTOMER_ORDER';
    IF v_has_table = 0 THEN
        DBMS_OUTPUT.PUT_LINE('COBBLECART.Customer_Order does not exist — skipping trigger rebuild.');
        RETURN;
    END IF;

    EXECUTE IMMEDIATE q'[
        CREATE OR REPLACE TRIGGER COBBLECART.trg_check_customer_before_order
        BEFORE INSERT ON COBBLECART.Customer_Order
        FOR EACH ROW
        DECLARE
            v_role     COBBLECART.Users.role%TYPE;
            v_verified COBBLECART.Users.email_verified%TYPE;
        BEGIN
            SELECT role, email_verified
            INTO v_role, v_verified
            FROM COBBLECART.Users
            WHERE user_id = :NEW.user_id;

            IF UPPER(v_role) <> 'CUSTOMER' THEN
                RAISE_APPLICATION_ERROR(-20012, 'Only customers can place orders');
            END IF;
            IF UPPER(v_verified) <> 'YES' THEN
                RAISE_APPLICATION_ERROR(-20013, 'Email not verified');
            END IF;
        END;
    ]';
    DBMS_OUTPUT.PUT_LINE('Recreated trg_check_customer_before_order with case-insensitive role check.');
END;
/

-- 5. Final report
PROMPT
PROMPT ============================================
PROMPT  Final status
PROMPT ============================================
SET FEEDBACK ON

SELECT name AS pdb_name, open_mode FROM v$pdbs WHERE name <> 'PDB$SEED';
SELECT username, account_status FROM dba_users WHERE username = 'COBBLECART';

PROMPT
PROMPT api/config.php should use:
PROMPT   user       = COBBLECART
PROMPT   password   = Oracle#12345@
PROMPT   connection = localhost/<the PDB name shown above>
PROMPT

EXIT
