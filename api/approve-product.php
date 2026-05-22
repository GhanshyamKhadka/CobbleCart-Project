<?php
require 'config.php';
require_role('admin');
$data = input_json();
require_fields($data, ['product_id', 'action']);

$productId = (int)$data['product_id'];
$action = strtoupper(trim($data['action']));
if (!in_array($action, ['APPROVED', 'REJECTED'], true)) {
    json_response(['success' => false, 'message' => 'Invalid action'], 400);
}

try {
    oci_execute_stmt('UPDATE PRODUCT SET APPROVAL_STATUS = :status WHERE PRODUCT_ID = :product_id', [
        'status' => $action,
        'product_id' => $productId
    ]);
    json_response(['success' => true, 'message' => 'Product ' . strtolower($action) . ' successfully']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Approval update failed: ' . $e->getMessage()], 500);
}
?>