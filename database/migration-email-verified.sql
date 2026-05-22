-- ============================================
-- MIGRATION: add Users.email_verified column
-- ============================================
-- Fixes the schema bug where trigger
-- trg_check_customer_before_order references a column
-- that was never defined. Run this once against your
-- COBBLEUSER schema. Idempotent — safe to re-run.
--
-- Usage (SQL*Plus):
--   sqlplus COBBLEUSER/Oracle#12345@//localhost/FREEPDB1 @migration-email-verified.sql
-- ============================================

SET SERVEROUTPUT ON

-- 1. Add the column if it isn't already there
DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'USERS' AND COLUMN_NAME = 'EMAIL_VERIFIED';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE Users ADD email_verified VARCHAR2(3) DEFAULT ''NO''';
        DBMS_OUTPUT.PUT_LINE('Added Users.email_verified column.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('Users.email_verified already exists, skipping.');
    END IF;
END;
/

-- 2. Backfill existing seed users so they can place orders
UPDATE Users SET email_verified = 'YES' WHERE email_verified IS NULL OR email_verified = 'NO';
COMMIT;

-- 3. Recompile the trigger that referenced the column
ALTER TRIGGER trg_check_customer_before_order COMPILE;

-- 4. Show status
SELECT object_name, status
FROM USER_OBJECTS
WHERE object_name = 'TRG_CHECK_CUSTOMER_BEFORE_ORDER';

EXIT
