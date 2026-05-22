-- CobbleCart product offers.
-- Run as COBBLECART to add per-product customer discounts.

SET SERVEROUTPUT ON

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count
    FROM user_tab_columns
    WHERE table_name = 'PRODUCT'
      AND column_name = 'OFFER_PERCENT';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE Product ADD offer_percent NUMBER(5,2) DEFAULT 0';
        DBMS_OUTPUT.PUT_LINE('Added Product.offer_percent.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('Product.offer_percent already exists.');
    END IF;

    EXECUTE IMMEDIATE 'UPDATE Product SET offer_percent = 0 WHERE offer_percent IS NULL';
    COMMIT;
END;
/

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

PROMPT Product offers migration complete.
