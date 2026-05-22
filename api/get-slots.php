<?php
// Returns upcoming collection slots that are at least 24 hours in the future,
// on Wed/Thu/Fri only (the DB trigger enforces this anyway), with how many
// orders are already booked into each slot.
require 'config.php';

try {
    $slotStart = collection_slot_start_sql('cs.COLLECTION_DATE', 'cs.TIME_SLOT');
    $slotLabel = collection_slot_normalized_sql('cs.TIME_SLOT');
    $stmt = oci_execute_stmt(
        "SELECT cs.SLOT_ID,
                TO_CHAR(cs.COLLECTION_DATE, 'YYYY-MM-DD') AS COLLECTION_DATE,
                TRIM(TO_CHAR(cs.COLLECTION_DATE, 'Day')) || ' ' ||
                    TO_CHAR(cs.COLLECTION_DATE, 'DD') || ' ' ||
                    TRIM(TO_CHAR(cs.COLLECTION_DATE, 'Month')) || ' ' ||
                    TO_CHAR(cs.COLLECTION_DATE, 'YYYY') AS DATE_LABEL,
                TRIM(TO_CHAR(cs.COLLECTION_DATE, 'Day')) AS DAY_NAME,
                $slotLabel AS TIME_SLOT,
                LEAST(NVL(cs.MAX_ORDERS, 20), 20) AS MAX_ORDERS,
                NVL((SELECT COUNT(*) FROM CUSTOMER_ORDER WHERE SLOT_ID = cs.SLOT_ID), 0) AS TAKEN
         FROM COLLECTION_SLOT cs
         WHERE $slotStart >= SYSDATE + 1
         AND TRIM(TO_CHAR(cs.COLLECTION_DATE, 'DY', 'NLS_DATE_LANGUAGE=ENGLISH')) IN ('WED','THU','FRI')
         AND $slotLabel IS NOT NULL
         ORDER BY cs.COLLECTION_DATE,
                  CASE $slotLabel
                    WHEN '10:00-13:00' THEN 1
                    WHEN '13:00-16:00' THEN 2
                    WHEN '16:00-19:00' THEN 3
                    ELSE 4
                  END,
                  cs.SLOT_ID",
        []
    );
    json_response(['success' => true, 'slots' => oci_fetch_assoc_all($stmt)]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
