<?php
require 'config.php';
try {
    $data = input_json();
    require_fields($data, ['product_id']);
    require_login();

    $userId = current_user_id();
    $productId = (int)$data['product_id'];

    $checkSql = 'SELECT FAVOURITE_ID FROM Favourite WHERE USER_ID = :uid AND PRODUCT_ID = :pid';
    $checkStmt = oci_parse($conn, $checkSql);
    if ($checkStmt === false) {
        $error = oci_error($conn);
        throw new Exception($error['message'] ?? 'Unable to parse favorite check');
    }

    if (!oci_bind_by_name($checkStmt, ':uid', $userId, -1) || !oci_bind_by_name($checkStmt, ':pid', $productId, -1)) {
        $error = oci_error($checkStmt);
        throw new Exception($error['message'] ?? 'Unable to bind favorite check variables');
    }

    if (!oci_execute($checkStmt)) {
        $error = oci_error($checkStmt);
        throw new Exception($error['message'] ?? 'Unable to execute favorite check');
    }

    $existing = oci_fetch_assoc($checkStmt);
    if (!$existing) {
        $insertSql = 'INSERT INTO Favourite (USER_ID, PRODUCT_ID) VALUES (:uid, :pid)';
        $insertStmt = oci_parse($conn, $insertSql);
        if ($insertStmt === false) {
            $error = oci_error($conn);
            throw new Exception($error['message'] ?? 'Unable to parse favorite insert');
        }

        if (!oci_bind_by_name($insertStmt, ':uid', $userId, -1) || !oci_bind_by_name($insertStmt, ':pid', $productId, -1)) {
            $error = oci_error($insertStmt);
            throw new Exception($error['message'] ?? 'Unable to bind favorite insert variables');
        }

        if (!oci_execute($insertStmt, OCI_COMMIT_ON_SUCCESS)) {
            $error = oci_error($insertStmt);
            throw new Exception($error['message'] ?? 'Unable to insert favorite');
        }
    }

    json_response(['success' => true, 'message' => 'Added to favorites']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Could not add favorite: ' . $e->getMessage()], 500);
}
