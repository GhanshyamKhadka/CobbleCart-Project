<?php
require 'config.php';
require_role('trader');
$data = input_json();
require_fields($data, ['product_type_id', 'name', 'description', 'price', 'quantity_per_item', 'stock_quantity', 'min_order', 'max_order']);

$shopId = current_shop_id();
if (!$shopId) {
    json_response(['success' => false, 'message' => 'Trader shop not found'], 403);
}

$price = (float)$data['price'];
$stock = (int)$data['stock_quantity'];
$min = (int)$data['min_order'];
$max = (int)$data['max_order'];
$offerPercent = (isset($data['offer_percent']) && trim((string)$data['offer_percent']) !== '')
    ? (float)$data['offer_percent']
    : 0.0;
if ($price <= 0 || $stock < 0 || $min < 1 || $max < $min) {
    json_response(['success' => false, 'message' => 'Invalid price, stock or order quantity values'], 400);
}
if ($offerPercent < 0 || $offerPercent >= 100) {
    json_response(['success' => false, 'message' => 'Offer percent must be between 0 and 99.99'], 400);
}

$imageUrl = null;
if (!empty($_FILES['product_image']['tmp_name']) && is_uploaded_file($_FILES['product_image']['tmp_name'])) {
    $uploadDir = realpath(__DIR__ . '/../frontend/images/products');
    if ($uploadDir === false) {
        json_response(['success' => false, 'message' => 'Product image directory not found at frontend/images/products'], 500);
    }

    $originalName = basename($_FILES['product_image']['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($extension, $allowed, true)) {
        json_response(['success' => false, 'message' => 'Only JPG, PNG and WEBP images are allowed'], 400);
    }

    $filename = uniqid('prod_', true) . '.' . $extension;
    $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $target)) {
        json_response(['success' => false, 'message' => 'Unable to save product image'], 500);
    }
    $imageUrl = 'images/products/' . $filename;
}

$productId = oracle_nextval('seq_product');
oci_execute_stmt(
    'INSERT INTO PRODUCT (PRODUCT_ID, SHOP_ID, PRODUCT_TYPE_ID, NAME, DESCRIPTION, PRICE, OFFER_PERCENT, QUANTITY_PER_ITEM, STOCK_QUANTITY, MIN_ORDER, MAX_ORDER, ALLERGY_INFO, PRODUCT_IMAGE, APPROVAL_STATUS, CREATED_AT) VALUES (:product_id, :shop_id, :product_type_id, :name, :description, :price, :offer_percent, :quantity_per_item, :stock_quantity, :min_order, :max_order, :allergy_info, :product_image, :approval_status, SYSDATE)',
    [
        'product_id' => $productId,
        'shop_id' => $shopId,
        'product_type_id' => (int)$data['product_type_id'],
        'name' => trim($data['name']),
        'description' => trim($data['description']),
        'price' => $price,
        'offer_percent' => $offerPercent,
        'quantity_per_item' => trim($data['quantity_per_item']),
        'stock_quantity' => $stock,
        'min_order' => $min,
        'max_order' => $max,
        'allergy_info' => trim($data['allergy_info'] ?? 'None'),
        'product_image' => $imageUrl,
        'approval_status' => 'PENDING'
    ]
);
json_response(['success' => true, 'message' => 'Product sent to admin for approval', 'product_id' => $productId]);
?>
