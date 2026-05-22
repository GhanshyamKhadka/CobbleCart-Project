<?php
require 'config.php';
require_login();
$role   = current_user_role();
$userId = current_user_id();
$shopId = current_shop_id();

// Base query shared by all roles. ITEMS_SUMMARY is a comma-separated list of
// "ProductName xQty" so the profile page can show a one-line preview without
// a second round-trip per order.
$baseSelect = "
    SELECT o.ORDER_ID,
           o.USER_ID,
           o.SLOT_ID,
           TO_CHAR(o.ORDER_DATE, 'YYYY-MM-DD HH24:MI:SS') AS ORDER_DATE,
           o.TOTAL_AMOUNT,
           o.STATUS,
           TO_CHAR(cs.COLLECTION_DATE, 'YYYY-MM-DD') AS COLLECTION_DATE,
           cs.TIME_SLOT,
           (SELECT LISTAGG(p.NAME || ' x' || oi.QUANTITY, ', ')
                          WITHIN GROUP (ORDER BY oi.ORDER_ITEM_ID)
            FROM ORDER_ITEM oi
            JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
            WHERE oi.ORDER_ID = o.ORDER_ID) AS ITEMS_SUMMARY,
           (SELECT NVL(SUM(oi.SUBTOTAL), 0)
            FROM ORDER_ITEM oi
            JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
            JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
            WHERE oi.ORDER_ID = o.ORDER_ID
            AND s.SHOP_ID = :scope_shop_id) AS SHOP_SUBTOTAL
    FROM CUSTOMER_ORDER o
    LEFT JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID";

if ($role === 'customer') {
    $sql = $baseSelect . " WHERE o.USER_ID = :user_id ORDER BY o.ORDER_DATE DESC";
    $stmt = oci_execute_stmt($sql, ['user_id' => $userId, 'scope_shop_id' => -1]);
} elseif ($role === 'trader' && $shopId > 0) {
    // Trader: show only orders that contain at least one product from their shop,
    // and compute SHOP_SUBTOTAL = how much of the order is for their shop only.
    $sql = $baseSelect . "
        WHERE EXISTS (
            SELECT 1 FROM ORDER_ITEM oi2
            JOIN PRODUCT p2 ON p2.PRODUCT_ID = oi2.PRODUCT_ID
            WHERE oi2.ORDER_ID = o.ORDER_ID AND p2.SHOP_ID = :shop_id
        )
        ORDER BY o.ORDER_DATE DESC";
    $stmt = oci_execute_stmt($sql, ['shop_id' => $shopId, 'scope_shop_id' => $shopId]);
} else {
    // Admin sees everything.
    $sql = $baseSelect . " ORDER BY o.ORDER_DATE DESC";
    $stmt = oci_execute_stmt($sql, ['scope_shop_id' => -1]);
}

json_response(['success' => true, 'orders' => oci_fetch_assoc_all($stmt)]);
