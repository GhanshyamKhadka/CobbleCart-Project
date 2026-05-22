<?php
require 'config.php';
try {
    require_login();
    $userId = current_user_id();

    $sql = 'SELECT f.FAVOURITE_ID, f.PRODUCT_ID,
                   p.NAME, p.PRICE, NVL(p.OFFER_PERCENT, 0) AS OFFER_PERCENT,
                   CASE
                       WHEN NVL(p.OFFER_PERCENT, 0) > 0 THEN ROUND(p.PRICE * (100 - p.OFFER_PERCENT) / 100, 2)
                       ELSE p.PRICE
                   END AS OFFER_PRICE,
                   p.PRODUCT_IMAGE, p.STOCK_QUANTITY, p.APPROVAL_STATUS,
                   s.SHOP_NAME
            FROM Favourite f
            JOIN PRODUCT p ON f.PRODUCT_ID = p.PRODUCT_ID
            JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
            WHERE f.USER_ID = :uid
            ORDER BY f.FAVOURITE_ID DESC';

    $stmt = oci_parse($conn, $sql);
    if ($stmt === false) {
        $error = oci_error($conn);
        throw new Exception($error['message'] ?? 'Unable to parse favorites query');
    }

    if (!oci_bind_by_name($stmt, ':uid', $userId, -1)) {
        $error = oci_error($stmt);
        throw new Exception($error['message'] ?? 'Unable to bind uid');
    }

    if (!oci_execute($stmt)) {
        $error = oci_error($stmt);
        throw new Exception($error['message'] ?? 'Unable to execute favorites query');
    }

    $rows = [];
    while (($row = oci_fetch_assoc($stmt)) !== false) {
        $rows[] = $row;
    }

    json_response(['success' => true, 'wishlist' => $rows]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Could not load favorites: ' . $e->getMessage()], 500);
}
