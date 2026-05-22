-- CobbleCart trader stock alerts.
-- Run as COBBLECART to add/refresh the low-stock alert table and trigger.
--
-- A product creates/updates an OPEN alert when stock_quantity is below 10.
-- The alert is marked RESOLVED automatically when stock is restored to 10+.

SET SERVEROUTPUT ON SIZE UNLIMITED
WHENEVER SQLERROR CONTINUE

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_sequences WHERE sequence_name = 'SEQ_STOCK_ALERT';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'CREATE SEQUENCE seq_stock_alert START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE';
    END IF;
END;
/

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'STOCK_ALERT';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
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
            )
        ]';
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_stock_alert
BEFORE INSERT ON Stock_Alert
FOR EACH ROW
BEGIN
    IF :NEW.alert_id IS NULL THEN
        :NEW.alert_id := seq_stock_alert.NEXTVAL;
    END IF;
END;
/

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

INSERT INTO STOCK_ALERT (
    alert_id, product_id, shop_id, trader_user_id,
    current_stock, threshold_stock, alert_status, alert_message,
    created_at, resolved_at
)
SELECT NULL, p.product_id, p.shop_id, s.user_id,
       NVL(p.stock_quantity, 0), 10, 'OPEN',
       'Low stock alert for ' || p.name || ': ' || NVL(p.stock_quantity, 0) || ' units left',
       SYSDATE, NULL
FROM Product p
JOIN Shop s ON s.shop_id = p.shop_id
WHERE NVL(p.stock_quantity, 0) < 10
AND NOT EXISTS (
    SELECT 1
    FROM Stock_Alert sa
    WHERE sa.product_id = p.product_id
      AND sa.alert_status = 'OPEN'
);

COMMIT;

PROMPT Stock alert table and trigger are ready.
