<?php
require 'config.php';
$where = ['1 = 1'];
$params = [];
$role = current_user_role();
$shopIdParam = !empty($_GET['shop_id']) ? (int)$_GET['shop_id'] : null;
$productIdParam = !empty($_GET['product_id']) ? (int)$_GET['product_id'] : null;

if ($productIdParam) {
    $where[] = 'p.PRODUCT_ID = :product_id';
    $params['product_id'] = $productIdParam;
}
if ($shopIdParam) {
    $where[] = 'p.SHOP_ID = :shop_id';
    $params['shop_id'] = $shopIdParam;
}
if (!empty($_GET['product_type_id'])) {
    $where[] = 'p.PRODUCT_TYPE_ID = :product_type_id';
    $params['product_type_id'] = (int)$_GET['product_type_id'];
}
if (!empty($_GET['search'])) {
    $where[] = '(UPPER(p.NAME) LIKE UPPER(:search) OR UPPER(s.SHOP_NAME) LIKE UPPER(:search))';
    $params['search'] = '%' . $_GET['search'] . '%';
}

if ($role === 'admin' && !empty($_GET['pending'])) {
    $where[] = "p.APPROVAL_STATUS = 'PENDING'";
} elseif ($role === 'trader' && $shopIdParam && current_shop_id() === $shopIdParam) {
    $where[] = "p.APPROVAL_STATUS IN ('APPROVED', 'PENDING')";
} else {
    $where[] = "p.APPROVAL_STATUS = 'APPROVED'";
}

$sql = 'SELECT p.PRODUCT_ID, p.SHOP_ID, p.PRODUCT_TYPE_ID, p.NAME, p.DESCRIPTION, p.PRICE,
               NVL(p.OFFER_PERCENT, 0) AS OFFER_PERCENT,
               CASE
                   WHEN NVL(p.OFFER_PERCENT, 0) > 0 THEN ROUND(p.PRICE * (100 - p.OFFER_PERCENT) / 100, 2)
                   ELSE p.PRICE
               END AS OFFER_PRICE,
               CASE WHEN NVL(p.OFFER_PERCENT, 0) > 0 THEN 1 ELSE 0 END AS HAS_OFFER,
               p.QUANTITY_PER_ITEM, p.STOCK_QUANTITY, p.MIN_ORDER, p.MAX_ORDER, p.ALLERGY_INFO, p.PRODUCT_IMAGE, p.APPROVAL_STATUS, s.SHOP_NAME, s.SHOP_TYPE, pt.TYPE_NAME
        FROM PRODUCT p
        JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
        LEFT JOIN PRODUCT_TYPE pt ON p.PRODUCT_TYPE_ID = pt.PRODUCT_TYPE_ID
        WHERE ' . implode(' AND ', $where) . ' ORDER BY p.PRODUCT_ID DESC';
$stmt = oci_execute_stmt($sql, $params);
json_response(['success' => true, 'products' => oci_fetch_assoc_all($stmt)]);
?>
