<?php
require 'config.php';
$data = input_json();
require_role('customer');
$customerId = current_user_id();
$items = $data['items'] ?? [];
$slotId = (int)($data['slot_id'] ?? $data['slot'] ?? 0);
$payment = trim($data['payment_method'] ?? 'PAYPAL');

if ($customerId <= 0 || !$items || $slotId <= 0) {
    json_response(['success' => false, 'message' => 'Customer, cart items and collection slot are required'], 400);
}

try {
    $slotStart = collection_slot_start_sql('cs.COLLECTION_DATE', 'cs.TIME_SLOT');
    $slotLabel = collection_slot_normalized_sql('cs.TIME_SLOT');
    $slotStmt = oci_execute_stmt(
        "SELECT cs.SLOT_ID,
                $slotLabel AS TIME_SLOT,
                LEAST(NVL(cs.MAX_ORDERS, 20), 20) AS MAX_ORDERS,
                CASE WHEN TRIM(TO_CHAR(cs.COLLECTION_DATE, 'DY', 'NLS_DATE_LANGUAGE=ENGLISH')) IN ('WED','THU','FRI') THEN 1 ELSE 0 END AS IS_ALLOWED_DAY,
                CASE WHEN $slotStart >= SYSDATE + 1 THEN 1 ELSE 0 END AS HAS_NOTICE,
                NVL((SELECT COUNT(*) FROM CUSTOMER_ORDER WHERE SLOT_ID = cs.SLOT_ID), 0) AS TAKEN
         FROM COLLECTION_SLOT cs
         WHERE cs.SLOT_ID = :slot_id
         FOR UPDATE",
        ['slot_id' => $slotId],
        false
    );
    $slot = oci_fetch_assoc_one($slotStmt);
    if (!$slot || !$slot['TIME_SLOT']) {
        throw new Exception('Please choose a valid collection slot.');
    }
    if ((int)$slot['IS_ALLOWED_DAY'] !== 1) {
        throw new Exception('Collection is only available Wednesday, Thursday, and Friday.');
    }
    if ((int)$slot['HAS_NOTICE'] !== 1) {
        throw new Exception('Collection slot must be at least 24 hours after placing the order.');
    }
    if ((int)$slot['TAKEN'] >= (int)$slot['MAX_ORDERS']) {
        throw new Exception('This collection slot is full.');
    }

    $total = 0;
    $preparedItems = [];

    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? $item['id'] ?? 0);
        $qty = max(1, (int)($item['quantity'] ?? $item['qty'] ?? 1));
        // Enforce global per-product purchase limit: max 20 units per order item
        if ($qty > 20) {
            throw new Exception('Cannot purchase more than 20 units of a single product');
        }
        if ($productId <= 0) {
            throw new Exception('Invalid product identifier');
        }

        $stmt = oci_execute_stmt(
            'SELECT PRODUCT_ID, PRICE, NVL(OFFER_PERCENT, 0) AS OFFER_PERCENT,
                    CASE
                        WHEN NVL(OFFER_PERCENT, 0) > 0 THEN ROUND(PRICE * (100 - OFFER_PERCENT) / 100, 2)
                        ELSE PRICE
                    END AS UNIT_PRICE,
                    STOCK_QUANTITY
             FROM PRODUCT
             WHERE PRODUCT_ID = :product_id
             FOR UPDATE',
            ['product_id' => $productId],
            false
        );
        $product = oci_fetch_assoc_one($stmt);
        if (!$product || (int)$product['STOCK_QUANTITY'] < $qty) {
            throw new Exception('Product unavailable or insufficient stock');
        }

        $unitPrice = (float)$product['UNIT_PRICE'];
        $subtotal = $qty * $unitPrice;
        $total += $subtotal;
        $preparedItems[] = ['product_id' => $productId, 'quantity' => $qty, 'price' => $unitPrice, 'subtotal' => $subtotal];
    }

    $orderId = oracle_nextval('seq_order');
    oci_execute_stmt(
        'INSERT INTO CUSTOMER_ORDER (ORDER_ID, USER_ID, SLOT_ID, ORDER_DATE, TOTAL_AMOUNT, STATUS) VALUES (:order_id, :user_id, :slot_id, SYSDATE, :total_amount, :status)',
        [
            'order_id'     => $orderId,
            'user_id'      => $customerId,
            'slot_id'      => $slotId,
            'total_amount' => $total,
            'status'       => 'PENDING_PAYMENT',
        ],
        false
    );

    foreach ($preparedItems as $item) {
        $orderItemId = oracle_nextval('seq_order_item');
        oci_execute_stmt(
            'INSERT INTO ORDER_ITEM (ORDER_ITEM_ID, ORDER_ID, PRODUCT_ID, QUANTITY, SUBTOTAL) VALUES (:order_item_id, :order_id, :product_id, :quantity, :subtotal)',
            [
                'order_item_id' => $orderItemId,
                'order_id'      => $orderId,
                'product_id'    => $item['product_id'],
                'quantity'      => $item['quantity'],
                'subtotal'      => $item['subtotal'],
            ],
            false
        );

        // Decrement stock here in PHP — this is the SINGLE source of truth.
        // We do NOT rely on a DB trigger because re-running CobbleCart Tables.sql
        // drops triggers; PHP code always survives. (The matching restore is in
        // paypal-cancel.php, which adds the quantity back if payment is abandoned.)
        oci_execute_stmt(
            'UPDATE PRODUCT SET STOCK_QUANTITY = STOCK_QUANTITY - :quantity WHERE PRODUCT_ID = :product_id',
            ['quantity' => $item['quantity'], 'product_id' => $item['product_id']],
            false
        );
    }

    // Pre-create one ORDER_PAYOUT row per distinct shop in the cart.
    // The customer will be redirected through PayPal ONCE PER SHOP — each payment
    // lands directly in that trader's PayPal account (the email here is the
    // trader's USERS.EMAIL, which is their sandbox PayPal email).
    $splitStmt = oci_execute_stmt(
        'SELECT s.SHOP_ID, s.USER_ID AS TRADER_USER_ID, tu.EMAIL AS TRADER_EMAIL,
                SUM(oi.SUBTOTAL) AS GROSS
         FROM ORDER_ITEM oi
         JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
         JOIN SHOP s    ON s.SHOP_ID    = p.SHOP_ID
         JOIN USERS tu  ON tu.USER_ID   = s.USER_ID
         WHERE oi.ORDER_ID = :order_id
         GROUP BY s.SHOP_ID, s.USER_ID, tu.EMAIL
         ORDER BY s.SHOP_ID',
        ['order_id' => $orderId],
        false
    );
    while ($row = oci_fetch_assoc($splitStmt)) {
        oci_execute_stmt(
            'INSERT INTO ORDER_PAYOUT
                (payout_id, order_id, shop_id, trader_user_id,
                 gross_amount, payout_status, paypal_email)
             VALUES (:pid, :oid, :sid, :tuid, :amt, :st, :email)',
            [
                'pid'   => oracle_nextval('seq_payout'),
                'oid'   => $orderId,
                'sid'   => (int)$row['SHOP_ID'],
                'tuid'  => (int)$row['TRADER_USER_ID'],
                'amt'   => round((float)$row['GROSS'], 2),
                'st'    => 'PENDING',
                'email' => $row['TRADER_EMAIL'],
            ],
            false
        );
    }

    db_commit();
    unset($_SESSION['cart']);

    // Frontend will now redirect to paypal-redirect.php?order_id={ORDER_ID}.
    // paypal-redirect.php will route the customer through PayPal once per shop,
    // sending each shop's portion directly to that trader's PayPal sandbox account.
    json_response([
        'success'       => true,
        'message'       => 'Order created. Redirecting to PayPal...',
        'order_id'      => $orderId,
        'total'         => round($total, 2),
        'redirect_path' => 'api/paypal-redirect.php?order_id=' . $orderId,
    ]);
} catch (Throwable $e) {
    db_rollback();
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
