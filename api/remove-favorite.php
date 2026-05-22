<?php
require 'config.php';
try {
    require_login();

    $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($productId <= 0) {
        json_response(['success' => false, 'message' => 'product_id is required'], 400);
    }

    $sql = 'DELETE FROM Favourite WHERE USER_ID = :uid AND PRODUCT_ID = :pid';
    $stmt = oci_parse($conn, $sql);
    if ($stmt === false) {
        $error = oci_error($conn);
        throw new Exception($error['message'] ?? 'Unable to parse favorite delete');
    }

    $uid = current_user_id();
    if (!oci_bind_by_name($stmt, ':uid', $uid, -1) || !oci_bind_by_name($stmt, ':pid', $productId, -1)) {
        $error = oci_error($stmt);
        throw new Exception($error['message'] ?? 'Unable to bind favorite delete variables');
    }

    if (!oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $error = oci_error($stmt);
        throw new Exception($error['message'] ?? 'Unable to delete favorite');
    }

    json_response(['success' => true, 'message' => 'Removed from favorites']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Could not remove favorite: ' . $e->getMessage()], 500);
}
