-- ============================================
-- COBBLECART COMPLETE ORACLE SCHEMA
-- Run this in SQL Developer or SQL*Plus
-- ============================================

-- DROP TABLES 
DROP TABLE Review CASCADE CONSTRAINTS;
DROP TABLE Favourite CASCADE CONSTRAINTS;
DROP TABLE Basket_Item CASCADE CONSTRAINTS;
DROP TABLE Payment CASCADE CONSTRAINTS;
DROP TABLE Order_Item CASCADE CONSTRAINTS;
DROP TABLE Customer_Order CASCADE CONSTRAINTS;
DROP TABLE Stock_Alert CASCADE CONSTRAINTS;
DROP TABLE Product CASCADE CONSTRAINTS;
DROP TABLE Product_Type CASCADE CONSTRAINTS;
DROP TABLE Shop CASCADE CONSTRAINTS;
DROP TABLE Admin CASCADE CONSTRAINTS;
DROP TABLE Trader CASCADE CONSTRAINTS;
DROP TABLE Customer CASCADE CONSTRAINTS;
DROP TABLE Collection_Slot CASCADE CONSTRAINTS;
DROP TABLE Users CASCADE CONSTRAINTS;

-- DROP SEQUENCES

DROP SEQUENCE seq_user;
DROP SEQUENCE seq_shop;
DROP SEQUENCE seq_product_type;
DROP SEQUENCE seq_product;
DROP SEQUENCE seq_slot;
DROP SEQUENCE seq_order;
DROP SEQUENCE seq_order_item;
DROP SEQUENCE seq_payment;
DROP SEQUENCE seq_stock_alert;
DROP SEQUENCE seq_Basket_Item;
DROP SEQUENCE seq_favourite;
DROP SEQUENCE seq_review;

-- SEQUENCES

CREATE SEQUENCE seq_user START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_shop START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_product_type START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_product START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_slot START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_order START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_order_item START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_payment START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_stock_alert START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_basket_item START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_favourite START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_review START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

-- TABLES

CREATE TABLE Users (
    user_id NUMBER PRIMARY KEY,
    first_name VARCHAR2(50) NOT NULL,
    last_name VARCHAR2(50),
    email VARCHAR2(100) UNIQUE,
    username VARCHAR2(50) UNIQUE,
    password VARCHAR2(255),
    profile_image VARCHAR2(255),
    role VARCHAR2(20),
    created_date DATE DEFAULT SYSDATE
);

CREATE TABLE Customer (
    user_id NUMBER PRIMARY KEY REFERENCES Users(user_id),
    loyalty_points NUMBER DEFAULT 0
);

CREATE TABLE Trader (
    user_id NUMBER PRIMARY KEY REFERENCES Users(user_id),
    business_name VARCHAR2(100)
);

CREATE TABLE Admin (
    user_id NUMBER PRIMARY KEY REFERENCES Users(user_id)
);

CREATE TABLE Shop (
    shop_id NUMBER PRIMARY KEY,
    user_id NUMBER REFERENCES Trader(user_id),
    shop_name VARCHAR2(100),
    shop_type VARCHAR2(50),
    status VARCHAR2(20)
);

CREATE TABLE Product_Type (
    product_type_id NUMBER PRIMARY KEY,
    type_name VARCHAR2(100)
);

CREATE TABLE Product (
    product_id NUMBER PRIMARY KEY,
    shop_id NUMBER REFERENCES Shop(shop_id),
    product_type_id NUMBER REFERENCES Product_Type(product_type_id),
    name VARCHAR2(100),
    description VARCHAR2(255),
    price NUMBER(10,2),
    offer_percent NUMBER(5,2) DEFAULT 0,
    quantity_per_item VARCHAR2(50),
    stock_quantity NUMBER,
    min_order NUMBER,
    max_order NUMBER,
    allergy_info VARCHAR2(255),
    product_image VARCHAR2(255),
    approval_status VARCHAR2(20) DEFAULT 'APPROVED',
    created_at DATE DEFAULT SYSDATE
);

CREATE TABLE Stock_Alert (
    alert_id NUMBER PRIMARY KEY,
    product_id NUMBER NOT NULL REFERENCES Product(product_id),
    shop_id NUMBER NOT NULL REFERENCES Shop(shop_id),
    trader_user_id NUMBER NOT NULL REFERENCES Trader(user_id),
    current_stock NUMBER,
    threshold_stock NUMBER DEFAULT 10,
    alert_status VARCHAR2(20) DEFAULT 'OPEN',
    alert_message VARCHAR2(255),
    created_at DATE DEFAULT SYSDATE,
    resolved_at DATE,
    CONSTRAINT chk_stock_alert_status CHECK (alert_status IN ('OPEN', 'RESOLVED'))
);

CREATE TABLE Collection_Slot (
    slot_id NUMBER PRIMARY KEY,
    collection_date DATE,
    time_slot VARCHAR2(20),
    max_orders NUMBER DEFAULT 20
);

CREATE TABLE Customer_Order (
    order_id NUMBER PRIMARY KEY,
    user_id NUMBER REFERENCES Customer(user_id),
    slot_id NUMBER REFERENCES Collection_Slot(slot_id),
    order_date DATE DEFAULT SYSDATE,
    total_amount NUMBER(10,2),
    status VARCHAR2(20)
);

CREATE TABLE Order_Item (
    order_item_id NUMBER PRIMARY KEY,
    order_id NUMBER REFERENCES Customer_Order(order_id),
    product_id NUMBER REFERENCES Product(product_id),
    quantity NUMBER,
    subtotal NUMBER(10,2)
);

CREATE TABLE Payment (
    payment_id NUMBER PRIMARY KEY,
    order_id NUMBER REFERENCES Customer_Order(order_id),
    payment_method VARCHAR2(50),
    amount NUMBER(10,2),
    payment_status VARCHAR2(20)
);

CREATE TABLE Basket (
    basket_id NUMBER PRIMARY KEY,
    user_id NUMBER REFERENCES Customer(user_id),
    created_date DATE DEFAULT SYSDATE
);

CREATE TABLE Basket_Item (
    basket_item_id NUMBER PRIMARY KEY,
    basket_id NUMBER REFERENCES Basket(basket_id),
    product_id NUMBER REFERENCES Product(product_id),
    quantity NUMBER
);

CREATE TABLE Favourite (
    favourite_id NUMBER PRIMARY KEY,
    user_id NUMBER REFERENCES Customer(user_id),
    product_id NUMBER REFERENCES Product(product_id)
);

CREATE TABLE Review (
    review_id NUMBER PRIMARY KEY,
    user_id NUMBER REFERENCES Customer(user_id),
    product_id NUMBER REFERENCES Product(product_id),
    rating NUMBER CHECK (rating BETWEEN 1 AND 5),
    comments VARCHAR2(255)
);

-- TRIGGERS

CREATE OR REPLACE TRIGGER trg_users BEFORE INSERT ON Users FOR EACH ROW
BEGIN IF :NEW.user_id IS NULL THEN :NEW.user_id := seq_user.NEXTVAL; END IF; END;
/

CREATE OR REPLACE TRIGGER trg_shop BEFORE INSERT ON Shop FOR EACH ROW
BEGIN IF :NEW.shop_id IS NULL THEN :NEW.shop_id := seq_shop.NEXTVAL; END IF; END;
/

CREATE OR REPLACE TRIGGER trg_product_type BEFORE INSERT ON Product_Type FOR EACH ROW
BEGIN IF :NEW.product_type_id IS NULL THEN :NEW.product_type_id := seq_product_type.NEXTVAL; END IF; END;
/

CREATE OR REPLACE TRIGGER trg_product BEFORE INSERT ON Product FOR EACH ROW
BEGIN IF :NEW.product_id IS NULL THEN :NEW.product_id := seq_product.NEXTVAL; END IF; END;
/

CREATE OR REPLACE TRIGGER trg_slot BEFORE INSERT ON Collection_Slot FOR EACH ROW
BEGIN IF :NEW.slot_id IS NULL THEN :NEW.slot_id := seq_slot.NEXTVAL; END IF; END;
/

CREATE OR REPLACE TRIGGER trg_order BEFORE INSERT ON Customer_Order FOR EACH ROW
BEGIN IF :NEW.order_id IS NULL THEN :NEW.order_id := seq_order.NEXTVAL; END IF; END;
/

CREATE OR REPLACE TRIGGER trg_order_item BEFORE INSERT ON Order_Item FOR EACH ROW
BEGIN IF :NEW.order_item_id IS NULL THEN :NEW.order_item_id := seq_order_item.NEXTVAL; END IF; END;
/

CREATE OR REPLACE TRIGGER trg_payment BEFORE INSERT ON Payment FOR EACH ROW
BEGIN IF :NEW.payment_id IS NULL THEN :NEW.payment_id := seq_payment.NEXTVAL; END IF; END;
/

CREATE OR REPLACE TRIGGER trg_stock_alert BEFORE INSERT ON Stock_Alert FOR EACH ROW
BEGIN IF :NEW.alert_id IS NULL THEN :NEW.alert_id := seq_stock_alert.NEXTVAL; END IF; END;
/

CREATE OR REPLACE TRIGGER trg_basket_item BEFORE INSERT ON Basket_Item FOR EACH ROW
BEGIN IF :NEW.basket_item_id IS NULL THEN :NEW.basket_item_id := seq_basket_item.NEXTVAL; END IF; END;
/

CREATE OR REPLACE TRIGGER trg_favourite BEFORE INSERT ON Favourite FOR EACH ROW
BEGIN IF :NEW.favourite_id IS NULL THEN :NEW.favourite_id := seq_favourite.NEXTVAL; END IF; END;
/

CREATE OR REPLACE TRIGGER trg_review BEFORE INSERT ON Review FOR EACH ROW
BEGIN IF :NEW.review_id IS NULL THEN :NEW.review_id := seq_review.NEXTVAL; END IF; END;
/

-- ============================================
-- BUSINESS LOGIC TRIGGERS
-- ============================================

-- 1) Stock decrement is handled in PHP (api/place-order.php), NOT a trigger.
--    A trigger here would double-decrement, because place-order.php already
--    runs an explicit UPDATE PRODUCT SET STOCK_QUANTITY = STOCK_QUANTITY - qty.
--    Keeping it in PHP also means it survives re-running this schema file.
--    (Trigger UPDATE_STOCK_AFTER_ORDER intentionally removed.)

-- 2) Check Stock Never Goes Negative
CREATE OR REPLACE TRIGGER TRG_STOCK_CHECK
BEFORE UPDATE ON PRODUCT
FOR EACH ROW
BEGIN
    IF :NEW.STOCK_QUANTITY < 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'Insufficient stock for this product.');
    END IF;
END;
/

-- 2b) Create/resolve trader stock alerts when stock drops below 10 units.
CREATE OR REPLACE TRIGGER TRG_PRODUCT_STOCK_ALERT
AFTER INSERT OR UPDATE OF STOCK_QUANTITY ON PRODUCT
FOR EACH ROW
DECLARE
    v_threshold CONSTANT NUMBER := 10;
    v_trader_user_id SHOP.USER_ID%TYPE;
    v_open_count NUMBER;
BEGIN
    SELECT USER_ID
    INTO v_trader_user_id
    FROM SHOP
    WHERE SHOP_ID = :NEW.SHOP_ID;

    IF NVL(:NEW.STOCK_QUANTITY, 0) < v_threshold THEN
        SELECT COUNT(*)
        INTO v_open_count
        FROM STOCK_ALERT
        WHERE PRODUCT_ID = :NEW.PRODUCT_ID
          AND ALERT_STATUS = 'OPEN';

        IF v_open_count = 0 THEN
            INSERT INTO STOCK_ALERT (
                alert_id, product_id, shop_id, trader_user_id,
                current_stock, threshold_stock, alert_status, alert_message,
                created_at, resolved_at
            ) VALUES (
                NULL, :NEW.PRODUCT_ID, :NEW.SHOP_ID, v_trader_user_id,
                NVL(:NEW.STOCK_QUANTITY, 0), v_threshold, 'OPEN',
                'Low stock alert for ' || :NEW.NAME || ': ' || NVL(:NEW.STOCK_QUANTITY, 0) || ' units left',
                SYSDATE, NULL
            );
        ELSE
            UPDATE STOCK_ALERT
            SET current_stock = NVL(:NEW.STOCK_QUANTITY, 0),
                threshold_stock = v_threshold,
                alert_message = 'Low stock alert for ' || :NEW.NAME || ': ' || NVL(:NEW.STOCK_QUANTITY, 0) || ' units left'
            WHERE PRODUCT_ID = :NEW.PRODUCT_ID
              AND ALERT_STATUS = 'OPEN';
        END IF;
    ELSE
        UPDATE STOCK_ALERT
        SET current_stock = :NEW.STOCK_QUANTITY,
            alert_status = 'RESOLVED',
            resolved_at = SYSDATE,
            alert_message = 'Stock restored for ' || :NEW.NAME || ': ' || :NEW.STOCK_QUANTITY || ' units available'
        WHERE PRODUCT_ID = :NEW.PRODUCT_ID
          AND ALERT_STATUS = 'OPEN';
    END IF;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        NULL;
END;
/

-- 3) Auto Calculate Subtotal
CREATE OR REPLACE TRIGGER TRG_CALC_SUBTOTAL
BEFORE INSERT OR UPDATE ON ORDER_ITEM
FOR EACH ROW
DECLARE
    V_PRICE PRODUCT.PRICE%TYPE;
BEGIN
    SELECT CASE
               WHEN NVL(OFFER_PERCENT, 0) > 0 THEN ROUND(PRICE * (100 - OFFER_PERCENT) / 100, 2)
               ELSE PRICE
           END
    INTO V_PRICE
    FROM PRODUCT
    WHERE PRODUCT_ID = :NEW.PRODUCT_ID;

    :NEW.SUBTOTAL := V_PRICE * :NEW.QUANTITY;
END;
/

-- 4) Check Stock, Min Order, Max Order BEFORE Ordering
CREATE OR REPLACE TRIGGER TRG_CHECK_PRODUCT_RULES
BEFORE INSERT ON ORDER_ITEM
FOR EACH ROW
DECLARE
    V_STOCK PRODUCT.STOCK_QUANTITY%TYPE;
    V_MIN   PRODUCT.MIN_ORDER%TYPE;
    V_MAX   PRODUCT.MAX_ORDER%TYPE;
BEGIN
    SELECT STOCK_QUANTITY, MIN_ORDER, MAX_ORDER
    INTO V_STOCK, V_MIN, V_MAX
    FROM PRODUCT
    WHERE PRODUCT_ID = :NEW.PRODUCT_ID;

    IF :NEW.QUANTITY > V_STOCK THEN
        RAISE_APPLICATION_ERROR(-20001, 'Not enough stock');
    END IF;

    IF :NEW.QUANTITY < V_MIN OR :NEW.QUANTITY > V_MAX THEN
        RAISE_APPLICATION_ERROR(-20002, 'Order quantity outside allowed range');
    END IF;
END;
/

-- 4b) Enforce maximum 20 units per order item (global business rule)
CREATE OR REPLACE TRIGGER TRG_LIMIT_ORDER_ITEM_QUANTITY
BEFORE INSERT OR UPDATE ON ORDER_ITEM
FOR EACH ROW
BEGIN
    IF :NEW.QUANTITY IS NOT NULL AND :NEW.QUANTITY > 20 THEN
        RAISE_APPLICATION_ERROR(-20020, 'Cannot order more than 20 units of a single product');
    END IF;
END;
/

-- 5) Limit Orders Per Collection Slot
CREATE OR REPLACE TRIGGER TRG_LIMIT_SLOT_ORDERS
BEFORE INSERT ON CUSTOMER_ORDER
FOR EACH ROW
DECLARE
    V_COUNT NUMBER;
    V_MAX   NUMBER;
BEGIN
    SELECT LEAST(NVL(MAX_ORDERS, 20), 20) INTO V_MAX
    FROM COLLECTION_SLOT
    WHERE SLOT_ID = :NEW.SLOT_ID;

    SELECT COUNT(*) INTO V_COUNT
    FROM CUSTOMER_ORDER
    WHERE SLOT_ID = :NEW.SLOT_ID;

    IF V_COUNT >= V_MAX THEN
        RAISE_APPLICATION_ERROR(-20003, 'This collection slot is full');
    END IF;
END;
/

-- 6) Enforce 24 Hours Rule
CREATE OR REPLACE TRIGGER TRG_CHECK_24HRS_RULE
BEFORE INSERT ON CUSTOMER_ORDER
FOR EACH ROW
DECLARE
    V_SLOT_START DATE;
BEGIN
    SELECT COLLECTION_DATE
           + CASE
               WHEN REGEXP_REPLACE(UPPER(TIME_SLOT), '[^0-9APM:-]', '') IN ('10:00-13:00','10-13','10:00AM-01:00PM','10:00AM-1:00PM') THEN 10 / 24
               WHEN REGEXP_REPLACE(UPPER(TIME_SLOT), '[^0-9APM:-]', '') IN ('13:00-16:00','13-16','01:00PM-04:00PM','1:00PM-4:00PM') THEN 13 / 24
               WHEN REGEXP_REPLACE(UPPER(TIME_SLOT), '[^0-9APM:-]', '') IN ('16:00-19:00','16-19','04:00PM-07:00PM','4:00PM-7:00PM') THEN 16 / 24
             END
    INTO V_SLOT_START
    FROM COLLECTION_SLOT
    WHERE SLOT_ID = :NEW.SLOT_ID;

    IF V_SLOT_START IS NULL OR V_SLOT_START < SYSDATE + 1 THEN
        RAISE_APPLICATION_ERROR(-20004, 'Collection must be at least 24 hours later');
    END IF;
END;
/

-- 7) Limit Maximum 10 Shops. System supports max 10 shops.
CREATE OR REPLACE TRIGGER trg_limit_shops
BEFORE INSERT ON Shop
FOR EACH ROW
DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM Shop;

    IF v_count >= 10 THEN
        RAISE_APPLICATION_ERROR(-20010,'Maximum 10 shops allowed');
    END IF;
END;
/

-- 8) Allow Collection Slots Only on Wed/Thu/Fri
CREATE OR REPLACE TRIGGER trg_check_collection_day
BEFORE INSERT OR UPDATE ON Collection_Slot
FOR EACH ROW
DECLARE
    v_compact VARCHAR2(40);
BEGIN
    IF TO_CHAR(:NEW.collection_date,'DY','NLS_DATE_LANGUAGE=ENGLISH')
       NOT IN ('WED','THU','FRI') THEN
        RAISE_APPLICATION_ERROR(-20011,'Collection only Wed/Thu/Fri');
    END IF;

    v_compact := REGEXP_REPLACE(UPPER(:NEW.time_slot), '[^0-9APM:-]', '');
    IF v_compact IN ('10:00-13:00','10-13','10:00AM-01:00PM','10:00AM-1:00PM') THEN
        :NEW.time_slot := '10:00-13:00';
    ELSIF v_compact IN ('13:00-16:00','13-16','01:00PM-04:00PM','1:00PM-4:00PM') THEN
        :NEW.time_slot := '13:00-16:00';
    ELSIF v_compact IN ('16:00-19:00','16-19','04:00PM-07:00PM','4:00PM-7:00PM') THEN
        :NEW.time_slot := '16:00-19:00';
    ELSE
        RAISE_APPLICATION_ERROR(-20014,'Invalid collection time slot');
    END IF;

    :NEW.max_orders := 20;
END;
/

-- 9) Only Verified Customers Can Place Orders
CREATE OR REPLACE TRIGGER trg_check_customer_before_order
BEFORE INSERT ON Customer_Order
FOR EACH ROW
DECLARE
    v_role Users.role%TYPE;
    v_verified Users.email_verified%TYPE;
BEGIN
    SELECT role, email_verified
    INTO v_role, v_verified
    FROM Users
    WHERE user_id = :NEW.user_id;

    IF v_role <> 'CUSTOMER' THEN
        RAISE_APPLICATION_ERROR(-20012,'Only customers can place orders');
    END IF;

    IF v_verified <> 'YES' THEN
        RAISE_APPLICATION_ERROR(-20013,'Email not verified');
    END IF;
END;
/

-- 10) Payment Status Auto-updates Order Status
CREATE OR REPLACE TRIGGER trg_payment_updates_order
AFTER UPDATE OF payment_status ON Payment
FOR EACH ROW
BEGIN
    IF :NEW.payment_status = 'PAID' THEN
        UPDATE Customer_Order
        SET status = 'PAID'
        WHERE order_id = :NEW.order_id;
    END IF;
END;
/

-- USERS 
INSERT INTO Users VALUES (NULL,'Admin','Main','admin@mail.com','admin','pass','ADMIN',SYSDATE);

INSERT INTO Users VALUES (NULL,'John','Butcher','butcher@mail.com','butcher','pass','TRADER',SYSDATE);
INSERT INTO Users VALUES (NULL,'Green','Grocer','green@mail.com','green','pass','TRADER',SYSDATE);
INSERT INTO Users VALUES (NULL,'Fish','Seller','fish@mail.com','fish','pass','TRADER',SYSDATE);
INSERT INTO Users VALUES (NULL,'Bake','House','bake@mail.com','bake','pass','TRADER',SYSDATE);
INSERT INTO Users VALUES (NULL,'Deli','Shop','deli@mail.com','deli','pass','TRADER',SYSDATE);

INSERT INTO Users VALUES (NULL,'Ram','Customer','ram@mail.com','ram','pass','CUSTOMER',SYSDATE);

-- ROLES 
INSERT INTO Admin VALUES (1);

INSERT INTO Trader VALUES (2,'Butcher Business');
INSERT INTO Trader VALUES (3,'Greengrocer Business');
INSERT INTO Trader VALUES (4,'Fish Business');
INSERT INTO Trader VALUES (5,'Bakery Business');
INSERT INTO Trader VALUES (6,'Deli Business');

INSERT INTO Customer VALUES (7,0);

-- SHOPS (5 TYPES) 
INSERT INTO Shop VALUES (NULL,2,'Butcher Shop','Butcher','ACTIVE');
INSERT INTO Shop VALUES (NULL,3,'Greengrocer Shop','Greengrocer','ACTIVE');
INSERT INTO Shop VALUES (NULL,4,'Fishmonger Shop','Fishmonger','ACTIVE');
INSERT INTO Shop VALUES (NULL,5,'Bakery Shop','Bakery','ACTIVE');
INSERT INTO Shop VALUES (NULL,6,'Delicatessen Shop','Delicatessen','ACTIVE');

-- PRODUCT TYPES 
INSERT INTO Product_Type VALUES (NULL,'Meat');
INSERT INTO Product_Type VALUES (NULL,'Vegetables');
INSERT INTO Product_Type VALUES (NULL,'Fish');
INSERT INTO Product_Type VALUES (NULL,'Bakery');
INSERT INTO Product_Type VALUES (NULL,'Deli');

-- PRODUCTS (UNIQUE PER SHOP) 
INSERT INTO Product VALUES (NULL,1,1,'Beef','Fresh beef',500,0,'1kg',50,1,10,'None', NULL, 'APPROVED', SYSDATE);
INSERT INTO Product VALUES (NULL,2,2,'Carrot','Organic carrot',100,0,'1kg',100,1,20,'None', NULL, 'APPROVED', SYSDATE);
INSERT INTO Product VALUES (NULL,3,3,'Salmon','Fresh fish',800,0,'1kg',30,1,5,'Fish', NULL, 'APPROVED', SYSDATE);
INSERT INTO Product VALUES (NULL,4,4,'Bread','Whole bread',50,0,'1 loaf',200,1,10,'Gluten', NULL, 'APPROVED', SYSDATE);
INSERT INTO Product VALUES (NULL,5,5,'Cheese','Cheddar cheese',300,0,'500g',40,1,5,'Dairy', NULL, 'APPROVED', SYSDATE);

-- COLLECTION SLOTS
INSERT INTO Collection_Slot VALUES (NULL, NEXT_DAY(SYSDATE,'WEDNESDAY'),'10:00-13:00',20);
INSERT INTO Collection_Slot VALUES (NULL, NEXT_DAY(SYSDATE,'WEDNESDAY'),'13:00-16:00',20);
INSERT INTO Collection_Slot VALUES (NULL, NEXT_DAY(SYSDATE,'WEDNESDAY'),'16:00-19:00',20);

-- ORDER + PAYMENT
INSERT INTO Customer_Order VALUES (NULL,7,1,SYSDATE,1500,'PLACED');

INSERT INTO Order_Item VALUES (NULL,1,1,2,1000);
INSERT INTO Order_Item VALUES (NULL,1,2,5,500);

INSERT INTO Payment VALUES (NULL,1,'PAYPAL',1500,'PAID');

COMMIT;
