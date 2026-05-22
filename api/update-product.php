<?php
// Update an existing product. Accepts EITHER multipart/form-data (so the
// trader's edit form can upload a new image) OR a JSON body.
require 'config.php';
require_login();

// --- gather input from whichever transport was used ------------------------
$isMultipart = !empty($_POST) || !empty($_FILES);
$data = $isMultipart ? $_POST : input_json();

$productId = (int)($data['product_id'] ?? 0);
if ($productId <= 0) {
    json_response(['success' => false, 'message' => 'product_id is required'], 400);
}

$role = current_user_role();

// --- ownership check: a trader may only touch their own shop's products ----
$stmt = oci_execute_stmt('SELECT SHOP_ID FROM PRODUCT WHERE PRODUCT_ID = :product_id', ['product_id' => $productId]);
$existing = oci_fetch_assoc_one($stmt);
if (!$existing) {
    json_response(['success' => false, 'message' => 'Product not found'], 404);
}
if ($role === 'trader' && (int)$existing['SHOP_ID'] !== (int)current_shop_id()) {
    json_response(['success' => false, 'message' => 'That product belongs to another trader'], 403);
}
if ($role !== 'trader' && $role !== 'admin') {
    json_response(['success' => false, 'message' => 'Only traders or admins can edit products'], 403);
}

// --- editable columns ------------------------------------------------------
$allowed = [
    'product_type_id'   => 'PRODUCT_TYPE_ID',
    'name'              => 'NAME',
    'description'       => 'DESCRIPTION',
    'price'             => 'PRICE',
    'offer_percent'     => 'OFFER_PERCENT',
    'quantity_per_item' => 'QUANTITY_PER_ITEM',
    'stock_quantity'    => 'STOCK_QUANTITY',
    'min_order'         => 'MIN_ORDER',
    'max_order'         => 'MAX_ORDER',
    'allergy_info'      => 'ALLERGY_INFO',
];
if ($role === 'admin') {
    $allowed['approval_status'] = 'APPROVAL_STATUS';
}

$sets   = [];
$params = [];
foreach ($allowed as $field => $column) {
    if (array_key_exists($field, $data) && $data[$field] !== '') {
        $sets[] = "$column = :$field";
        // numeric coercion for the obvious numeric columns
        if (in_array($field, ['product_type_id', 'stock_quantity', 'min_order', 'max_order'], true)) {
            $params[$field] = (int)$data[$field];
        } elseif ($field === 'price' || $field === 'offer_percent') {
            $params[$field] = (float)$data[$field];
        } else {
            $params[$field] = trim((string)$data[$field]);
        }
    }
}

// --- optional new image (traders AND admins may replace the image) ---------
if (!empty($_FILES['product_image']['tmp_name']) && is_uploaded_file($_FILES['product_image']['tmp_name'])) {
    $uploadDir = realpath(__DIR__ . '/../frontend/images/products');
    if ($uploadDir === false) {
        json_response(['success' => false, 'message' => 'Product image directory not found'], 500);
    }
    $ext = strtolower(pathinfo(basename($_FILES['product_image']['name']), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        json_response(['success' => false, 'message' => 'Only JPG, PNG and WEBP images are allowed'], 400);
    }
    $filename = uniqid('prod_', true) . '.' . $ext;
    if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $filename)) {
        json_response(['success' => false, 'message' => 'Unable to save the uploaded image'], 500);
    }
    $sets[] = 'PRODUCT_IMAGE = :product_image';
    $params['product_image'] = 'images/products/' . $filename;
}

if (!$sets) {
    json_response(['success' => false, 'message' => 'No fields to update'], 400);
}

// --- basic numeric sanity --------------------------------------------------
if (isset($params['price']) && $params['price'] <= 0) {
    json_response(['success' => false, 'message' => 'Price must be greater than 0'], 400);
}
if (isset($params['offer_percent']) && ($params['offer_percent'] < 0 || $params['offer_percent'] >= 100)) {
    json_response(['success' => false, 'message' => 'Offer percent must be between 0 and 99.99'], 400);
}
if (isset($params['min_order'], $params['max_order']) && $params['max_order'] < $params['min_order']) {
    json_response(['success' => false, 'message' => 'Max order cannot be less than min order'], 400);
}

$params['product_id'] = $productId;

try {
    oci_execute_stmt('UPDATE PRODUCT SET ' . implode(', ', $sets) . ' WHERE PRODUCT_ID = :product_id', $params);
    json_response(['success' => true, 'message' => 'Product updated successfully']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Product update failed: ' . $e->getMessage()], 500);
}
