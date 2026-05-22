<?php
// PayPal redirects here when the customer cancels at the sandbox.
// Marks the order CANCELLED and restores stock so the items go back to inventory.

require_once __DIR__ . '/config.php';

header_remove('Content-Type');
header('Content-Type: text/html; charset=UTF-8');

$paypal = require __DIR__ . '/paypal-config.local.php';
$orderId = (int)($_REQUEST['custom'] ?? $_REQUEST['cm'] ?? $_REQUEST['order_id'] ?? 0);

if ($orderId > 0) {
    try {
        $stmt = oci_execute_stmt(
            'SELECT STATUS FROM CUSTOMER_ORDER WHERE ORDER_ID = :order_id',
            ['order_id' => $orderId]
        );
        $order = oci_fetch_assoc_one($stmt);
        if ($order && $order['STATUS'] === 'PENDING_PAYMENT') {
            // Restore stock for each order item
            $stmt = oci_execute_stmt(
                'SELECT PRODUCT_ID, QUANTITY FROM ORDER_ITEM WHERE ORDER_ID = :order_id',
                ['order_id' => $orderId],
                false
            );
            while ($row = oci_fetch_assoc($stmt)) {
                oci_execute_stmt(
                    'UPDATE PRODUCT SET STOCK_QUANTITY = STOCK_QUANTITY + :q WHERE PRODUCT_ID = :pid',
                    ['q' => (int)$row['QUANTITY'], 'pid' => (int)$row['PRODUCT_ID']],
                    false
                );
            }
            oci_execute_stmt(
                'UPDATE CUSTOMER_ORDER SET STATUS = :s WHERE ORDER_ID = :oid',
                ['s' => 'CANCELLED', 'oid' => $orderId],
                false
            );
            db_commit();
        }
    } catch (Throwable $e) {
        db_rollback();
        error_log('paypal-cancel.php: ' . $e->getMessage());
    }
}

header('Location: ' . $paypal['site_base_url'] . '/frontend/customer/cart.html?cancelled=' . $orderId);
exit;
