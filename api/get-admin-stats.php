<?php
// Aggregate dashboard KPIs for the admin home page.
require 'config.php';
require_role('admin');

try {
    $stmt = oci_execute_stmt("
        SELECT
            (SELECT COUNT(*) FROM USERS WHERE ROLE = 'TRADER')                                 AS TOTAL_TRADERS,
            (SELECT COUNT(*) FROM USERS WHERE ROLE = 'CUSTOMER')                               AS TOTAL_CUSTOMERS,
            (SELECT COUNT(*) FROM CUSTOMER_ORDER WHERE TRUNC(ORDER_DATE) = TRUNC(SYSDATE))     AS ORDERS_TODAY,
            (SELECT NVL(SUM(TOTAL_AMOUNT), 0) FROM CUSTOMER_ORDER
              WHERE ORDER_DATE >= SYSDATE - 7 AND STATUS = 'PAID')                              AS REVENUE_THIS_WEEK,
            (SELECT COUNT(*) FROM SHOP WHERE STATUS = 'ACTIVE')                                AS ACTIVE_SHOPS,
            (SELECT COUNT(*) FROM PRODUCT WHERE APPROVAL_STATUS = 'PENDING')                   AS PENDING_PRODUCTS
        FROM DUAL
    ");
    $row = oci_fetch_assoc_one($stmt) ?: [];
    json_response(['success' => true, 'stats' => $row]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
