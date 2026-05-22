-- CobbleCart pilot collection slot rules.
-- Run as COBBLECART after the main schema if an existing database needs syncing.
--
-- Rules:
--   * Collection days: Wednesday, Thursday, Friday only
--   * Time windows: 10:00-13:00, 13:00-16:00, 16:00-19:00
--   * Minimum notice: slot start must be at least 24 hours after ordering
--   * Capacity: 20 orders per slot

SET SERVEROUTPUT ON SIZE UNLIMITED
WHENEVER SQLERROR CONTINUE

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

UPDATE Collection_Slot
SET time_slot =
    CASE
        WHEN REGEXP_REPLACE(UPPER(time_slot), '[^0-9APM:-]', '') IN ('10:00-13:00','10-13','10:00AM-01:00PM','10:00AM-1:00PM') THEN '10:00-13:00'
        WHEN REGEXP_REPLACE(UPPER(time_slot), '[^0-9APM:-]', '') IN ('13:00-16:00','13-16','01:00PM-04:00PM','1:00PM-4:00PM') THEN '13:00-16:00'
        WHEN REGEXP_REPLACE(UPPER(time_slot), '[^0-9APM:-]', '') IN ('16:00-19:00','16-19','04:00PM-07:00PM','4:00PM-7:00PM') THEN '16:00-19:00'
        ELSE time_slot
    END,
    max_orders = 20;

DELETE FROM Collection_Slot
WHERE slot_id IN (
    SELECT slot_id
    FROM (
        SELECT cs.slot_id,
               COUNT(co.order_id) AS order_count,
               ROW_NUMBER() OVER (
                   PARTITION BY TRUNC(cs.collection_date), cs.time_slot
                   ORDER BY CASE WHEN COUNT(co.order_id) > 0 THEN 0 ELSE 1 END, cs.slot_id
               ) AS rn
        FROM Collection_Slot cs
        LEFT JOIN Customer_Order co ON co.slot_id = cs.slot_id
        GROUP BY cs.slot_id, TRUNC(cs.collection_date), cs.time_slot
    )
    WHERE rn > 1
    AND order_count = 0
);

DECLARE
    v_date DATE;
    v_count NUMBER;
    v_hour NUMBER;
BEGIN
    FOR i IN 0..55 LOOP
        v_date := TRUNC(SYSDATE) + i;
        IF TO_CHAR(v_date, 'DY', 'NLS_DATE_LANGUAGE=ENGLISH') IN ('WED','THU','FRI') THEN
            FOR slot_rec IN (
                SELECT '10:00-13:00' AS time_slot, 10 AS start_hour FROM dual UNION ALL
                SELECT '13:00-16:00', 13 FROM dual UNION ALL
                SELECT '16:00-19:00', 16 FROM dual
            ) LOOP
                v_hour := slot_rec.start_hour;
                IF v_date + (v_hour / 24) >= SYSDATE + 1 THEN
                    SELECT COUNT(*) INTO v_count
                    FROM Collection_Slot
                    WHERE TRUNC(collection_date) = v_date
                    AND time_slot = slot_rec.time_slot;

                    IF v_count = 0 THEN
                        INSERT INTO Collection_Slot (slot_id, collection_date, time_slot, max_orders)
                        VALUES (NULL, v_date, slot_rec.time_slot, 20);
                    END IF;
                END IF;
            END LOOP;
        END IF;
    END LOOP;
END;
/

COMMIT;

PROMPT Collection slot pilot rules applied.
