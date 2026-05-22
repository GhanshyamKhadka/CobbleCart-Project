<?php
require 'config.php';
$cart = $_SESSION['cart'] ?? [];
if (!$cart) json_response(['success' => true, 'items' => [], 'total' => 0]);
$ids = array_keys($cart);
$placeholders = implode(',', array_map(fn($i) => ':p' . $i, array_keys($ids)));
$params = [];
foreach ($ids as $i => $id) {
    $params['p' . $i] = $id;
}
$stmt = oci_execute_stmt("SELECT p.PRODUCT_ID, p.NAME, p.PRICE, NVL(p.OFFER_PERCENT, 0) AS OFFER_PERCENT,
                                 CASE
                                     WHEN NVL(p.OFFER_PERCENT, 0) > 0 THEN ROUND(p.PRICE * (100 - p.OFFER_PERCENT) / 100, 2)
                                     ELSE p.PRICE
                                 END AS OFFER_PRICE,
                                 s.SHOP_NAME
                          FROM PRODUCT p
                          JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
                          WHERE p.PRODUCT_ID IN ($placeholders)", $params);
$items = [];
$total = 0;
foreach (oci_fetch_assoc_all($stmt) as $row) {
    $qty = (int)$cart[$row['PRODUCT_ID']];
    $unitPrice = (float)$row['OFFER_PRICE'];
    $subtotal = $qty * $unitPrice;
    $total += $subtotal;
    $items[] = [
        'product_id' => $row['PRODUCT_ID'],
        'name' => $row['NAME'],
        'price' => $unitPrice,
        'original_price' => (float)$row['PRICE'],
        'offer_percent' => (float)$row['OFFER_PERCENT'],
        'shop_name' => $row['SHOP_NAME'],
        'quantity' => $qty,
        'subtotal' => $subtotal
    ];
}
json_response(['success' => true, 'items' => $items, 'total' => $total]);
?>
