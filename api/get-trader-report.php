<?php
require 'config.php';
require_role(['trader', 'admin']);
$type = $_GET['type'] ?? 'daily';
$shopId = (int)($_GET['shop_id'] ?? 0);
$sort = $_GET['sort'] ?? 'name';
if ($shopId <= 0 && current_user_role() === 'trader') {
    $shopId = current_shop_id();
}
if ($shopId <= 0) json_response(['success' => false, 'message' => 'shop_id is required'], 400);

if ($type === 'daily') {
    $orders = oci_execute_stmt(
        'SELECT o.ORDER_ID,
                u.FIRST_NAME || \' \' || u.LAST_NAME AS CUSTOMER_NAME,
                cs.COLLECTION_DATE,
                cs.TIME_SLOT AS TIME_SLOT,
                o.TOTAL_AMOUNT,
                o.STATUS
         FROM CUSTOMER_ORDER o
         JOIN USERS u ON o.USER_ID = u.USER_ID
         JOIN COLLECTION_SLOT cs ON o.SLOT_ID = cs.SLOT_ID
         WHERE TRUNC(o.ORDER_DATE) = TRUNC(SYSDATE)
           AND EXISTS (
               SELECT 1 FROM ORDER_ITEM oi JOIN PRODUCT p ON oi.PRODUCT_ID = p.PRODUCT_ID WHERE oi.ORDER_ID = o.ORDER_ID AND p.SHOP_ID = :shop_id
           )
         ORDER BY o.ORDER_DATE DESC',
        ['shop_id' => $shopId]
    );

    $stock = oci_execute_stmt('SELECT PRODUCT_ID, NAME, STOCK_QUANTITY FROM PRODUCT WHERE SHOP_ID = :shop_id ORDER BY STOCK_QUANTITY ASC', ['shop_id' => $shopId]);
    json_response(['success' => true, 'orders' => oci_fetch_assoc_all($orders), 'stock' => oci_fetch_assoc_all($stock)]);
}

if ($type === 'weekly') {
    $orders = oci_execute_stmt(
        'SELECT o.ORDER_ID,
                TRUNC(o.ORDER_DATE) AS ORDER_DATE,
                p.NAME AS PRODUCT_NAME,
                oi.QUANTITY,
                oi.SUBTOTAL,
                o.STATUS
         FROM CUSTOMER_ORDER o
         JOIN ORDER_ITEM oi ON o.ORDER_ID = oi.ORDER_ID
         JOIN PRODUCT p ON oi.PRODUCT_ID = p.PRODUCT_ID
         WHERE p.SHOP_ID = :shop_id
           AND o.ORDER_DATE >= SYSDATE - 7
           AND o.STATUS IN (\'PAID\', \'DELIVERED\', \'PLACED\')
         ORDER BY o.ORDER_DATE DESC',
        ['shop_id' => $shopId]
    );

    $summary = oci_execute_stmt(
        'SELECT COUNT(DISTINCT o.ORDER_ID) AS TOTAL_ORDERS, NVL(SUM(oi.SUBTOTAL),0) AS TOTAL_REVENUE
         FROM CUSTOMER_ORDER o
         JOIN ORDER_ITEM oi ON o.ORDER_ID = oi.ORDER_ID
         JOIN PRODUCT p ON oi.PRODUCT_ID = p.PRODUCT_ID
         WHERE p.SHOP_ID = :shop_id
           AND o.ORDER_DATE >= SYSDATE - 7',
        ['shop_id' => $shopId]
    );
    json_response(['success' => true, 'orders' => oci_fetch_assoc_all($orders), 'summary' => oci_fetch_assoc_one($summary)]);
}

$orderBy = $sort === 'orders' ? 'TOTAL_ORDERS DESC' : ($sort === 'income' ? 'TOTAL_REVENUE DESC' : 'PRODUCT_NAME ASC');
$products = oci_execute_stmt(
    "SELECT p.NAME AS PRODUCT_NAME,
            COUNT(DISTINCT o.ORDER_ID) AS TOTAL_ORDERS,
            NVL(SUM(oi.QUANTITY),0) AS TOTAL_QTY,
            NVL(SUM(oi.SUBTOTAL),0) AS TOTAL_REVENUE
     FROM PRODUCT p
     LEFT JOIN ORDER_ITEM oi ON p.PRODUCT_ID = oi.PRODUCT_ID
     LEFT JOIN CUSTOMER_ORDER o ON oi.ORDER_ID = o.ORDER_ID AND TRUNC(o.ORDER_DATE, 'MM') = TRUNC(SYSDATE, 'MM')
     WHERE p.SHOP_ID = :shop_id
     GROUP BY p.PRODUCT_ID, p.NAME
     ORDER BY " . $orderBy,
    ['shop_id' => $shopId]
);
$summary = oci_execute_stmt(
    "SELECT COUNT(DISTINCT o.ORDER_ID) AS TOTAL_ORDERS, NVL(SUM(oi.SUBTOTAL),0) AS TOTAL_REVENUE
     FROM CUSTOMER_ORDER o
     JOIN ORDER_ITEM oi ON o.ORDER_ID = oi.ORDER_ID
     JOIN PRODUCT p ON oi.PRODUCT_ID = p.PRODUCT_ID
     WHERE p.SHOP_ID = :shop_id
       AND TRUNC(o.ORDER_DATE, 'MM') = TRUNC(SYSDATE, 'MM')",
    ['shop_id' => $shopId]
);
json_response(['success' => true, 'products' => oci_fetch_assoc_all($products), 'summary' => oci_fetch_assoc_one($summary)]);
?>