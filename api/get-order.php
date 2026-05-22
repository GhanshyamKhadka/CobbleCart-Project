<?php
// Returns a single order with its items and slot info for the invoice page.
// The customer can only see their own orders; admins can see any.
require 'config.php';
require_login();

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    json_response(['success' => false, 'message' => 'order_id is required'], 400);
}

try {
    $stmt = oci_execute_stmt(
        'SELECT co.ORDER_ID, co.USER_ID, co.ORDER_DATE, co.TOTAL_AMOUNT, co.STATUS,
                u.FIRST_NAME, u.LAST_NAME, u.EMAIL,
                cs.COLLECTION_DATE, cs.TIME_SLOT,
                pay.PAYMENT_METHOD, pay.PAYMENT_STATUS
         FROM CUSTOMER_ORDER co
         JOIN USERS u ON u.USER_ID = co.USER_ID
         LEFT JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = co.SLOT_ID
         LEFT JOIN PAYMENT pay ON pay.ORDER_ID = co.ORDER_ID
         WHERE co.ORDER_ID = :order_id',
        ['order_id' => $orderId]
    );
    $order = oci_fetch_assoc_one($stmt);
    if (!$order) {
        json_response(['success' => false, 'message' => 'Order not found'], 404);
    }

    $role = current_user_role();
    if ($role !== 'admin' && (int)$order['USER_ID'] !== (int)current_user_id()) {
        json_response(['success' => false, 'message' => 'Not authorized to view this order'], 403);
    }

    $stmt = oci_execute_stmt(
        'SELECT oi.ORDER_ITEM_ID, oi.PRODUCT_ID, oi.QUANTITY, oi.SUBTOTAL,
                CASE WHEN oi.QUANTITY > 0 THEN ROUND(oi.SUBTOTAL / oi.QUANTITY, 2) ELSE 0 END AS UNIT_PRICE,
                p.PRICE AS CURRENT_PRICE, NVL(p.OFFER_PERCENT, 0) AS CURRENT_OFFER_PERCENT,
                p.NAME AS PRODUCT_NAME,
                s.SHOP_ID, s.SHOP_NAME
         FROM ORDER_ITEM oi
         JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
         JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
         WHERE oi.ORDER_ID = :order_id
         ORDER BY s.SHOP_ID, oi.ORDER_ITEM_ID',
        ['order_id' => $orderId]
    );
    $items = oci_fetch_assoc_all($stmt);

    // Per-trader payout split (the "money split" view). Present only on PAID orders.
    $payoutStmt = oci_execute_stmt(
        'SELECT op.SHOP_ID, op.GROSS_AMOUNT, op.PAYOUT_STATUS, op.PAYPAL_EMAIL,
                s.SHOP_NAME
         FROM ORDER_PAYOUT op
         JOIN SHOP s ON s.SHOP_ID = op.SHOP_ID
         WHERE op.ORDER_ID = :order_id
         ORDER BY op.SHOP_ID',
        ['order_id' => $orderId]
    );
    $payouts = oci_fetch_assoc_all($payoutStmt);

    json_response([
        'success' => true,
        'order'   => $order,
        'items'   => $items,
        'payouts' => $payouts,
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
