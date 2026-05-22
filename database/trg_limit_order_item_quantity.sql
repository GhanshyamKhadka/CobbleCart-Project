-- Trigger: Enforce a hard maximum of 20 units per ORDER_ITEM row
-- Apply with SQL*Plus / SQLcl: @trg_limit_order_item_quantity.sql

CREATE OR REPLACE TRIGGER TRG_LIMIT_ORDER_ITEM_QUANTITY
BEFORE INSERT OR UPDATE ON ORDER_ITEM
FOR EACH ROW
BEGIN
    IF :NEW.QUANTITY IS NOT NULL AND :NEW.QUANTITY > 20 THEN
        RAISE_APPLICATION_ERROR(-20020, 'Cannot order more than 20 units of a single product');
    END IF;
END;
/
