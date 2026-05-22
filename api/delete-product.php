<?php
require 'config.php';
require_login();
$data = input_json();
$productId = (int)($data['product_id'] ?? $_GET['product_id'] ?? 0);
if ($productId <= 0) json_response(['success' => false, 'message' => 'product_id is required'], 400);

$role = current_user_role();
if ($role === 'trader') {
    $stmt = oci_execute_stmt('SELECT SHOP_ID FROM PRODUCT WHERE PRODUCT_ID = :product_id', ['product_id' => $productId]);
    $product = oci_fetch_assoc_one($stmt);
    if (!$product || $product['SHOP_ID'] !== current_shop_id()) {
        json_response(['success' => false, 'message' => 'Product belongs to another trader or does not exist'], 403);
    }
}

try {
    oci_execute_stmt('UPDATE PRODUCT SET APPROVAL_STATUS = :status, STOCK_QUANTITY = 0 WHERE PRODUCT_ID = :product_id', ['status' => 'REJECTED', 'product_id' => $productId]);
    json_response(['success' => true, 'message' => 'Product removed from customer view']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Product removal failed: ' . $e->getMessage()], 500);
}
?>