<?php
// Returns every trader + their shop + a few KPIs for the admin
// trader-management page. Admin-only.
require 'config.php';
require_role('admin');

try {
    $stmt = oci_execute_stmt(
        "SELECT u.USER_ID,
                u.FIRST_NAME || ' ' || NVL(u.LAST_NAME,'') AS NAME,
                u.EMAIL,
                u.CREATED_DATE,
                s.SHOP_ID,
                s.SHOP_NAME,
                s.SHOP_TYPE,
                s.STATUS AS SHOP_STATUS,
                (SELECT COUNT(*) FROM PRODUCT  p WHERE p.SHOP_ID = s.SHOP_ID) AS PRODUCT_COUNT,
                (SELECT COUNT(*) FROM PRODUCT  p WHERE p.SHOP_ID = s.SHOP_ID AND p.APPROVAL_STATUS = 'PENDING') AS PENDING_COUNT,
                (SELECT COUNT(DISTINCT oi.ORDER_ID)
                 FROM ORDER_ITEM oi JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
                 WHERE p.SHOP_ID = s.SHOP_ID) AS ORDER_COUNT,
                (SELECT NVL(SUM(oi.SUBTOTAL), 0)
                 FROM ORDER_ITEM oi
                 JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
                 JOIN CUSTOMER_ORDER o ON o.ORDER_ID = oi.ORDER_ID
                 WHERE p.SHOP_ID = s.SHOP_ID AND o.STATUS = 'PAID') AS GROSS_REVENUE
         FROM USERS u
         LEFT JOIN SHOP s ON s.USER_ID = u.USER_ID
         WHERE u.ROLE = 'TRADER'
         ORDER BY u.USER_ID"
    );
    json_response(['success' => true, 'traders' => oci_fetch_assoc_all($stmt)]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
