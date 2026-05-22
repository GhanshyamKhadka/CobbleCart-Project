<?php
// Order model. Covers B7-*, B9-*, C4-01, D4-01.

class Order
{
    // B7: place an order. Wraps the basket -> order_item conversion in a transaction
    // so partial inserts cannot corrupt stock counts (UPDATE_STOCK_AFTER_ORDER trigger fires per item).
    public static function placeFromCart(int $userId, array $payload): array
    {
        $cart = Cart::getCart($userId);
        if (empty($cart['items'])) {
            throw new RuntimeException('Cart is empty');
        }

        $slotId        = isset($payload['slot_id']) ? (int)$payload['slot_id'] : null;
        $paymentMethod = strtoupper($payload['payment_method'] ?? 'PAYPAL'); // E6
        $orderId       = db_nextval('seq_order');

        db_execute(
            'INSERT INTO CUSTOMER_ORDER (ORDER_ID, USER_ID, SLOT_ID, TOTAL_AMOUNT, STATUS)
             VALUES (:oid, :uid, :sid, :tot, :st)',
            [
                'oid' => $orderId,
                'uid' => $userId,
                'sid' => $slotId,
                'tot' => $cart['total'],
                'st'  => 'PENDING',
            ],
            false
        );

        foreach ($cart['items'] as $item) {
            db_execute(
                'INSERT INTO ORDER_ITEM (ORDER_ITEM_ID, ORDER_ID, PRODUCT_ID, QUANTITY, SUBTOTAL)
                 VALUES (seq_order_item.NEXTVAL, :oid, :pid, :q, :sub)',
                [
                    'oid' => $orderId,
                    'pid' => (int)$item['PRODUCT_ID'],
                    'q'   => (int)$item['QUANTITY'],
                    'sub' => (float)$item['LINE_TOTAL'],
                ],
                false
            );
        }

        db_execute(
            'INSERT INTO PAYMENT (PAYMENT_ID, ORDER_ID, PAYMENT_METHOD, AMOUNT, PAYMENT_STATUS)
             VALUES (seq_payment.NEXTVAL, :oid, :pm, :amt, :st)',
            [
                'oid' => $orderId,
                'pm'  => $paymentMethod,
                'amt' => $cart['total'],
                'st'  => 'PENDING',
            ],
            false
        );

        db_commit();
        Cart::clear($userId);

        return ['order_id' => $orderId, 'total' => $cart['total']];
    }

    public static function findById(int $orderId): ?array
    {
        $order = db_fetch_one(db_execute(
            'SELECT o.ORDER_ID, o.USER_ID, o.SLOT_ID, o.ORDER_DATE, o.TOTAL_AMOUNT, o.STATUS,
                    cs.COLLECTION_DATE, cs.TIME_SLOT
             FROM CUSTOMER_ORDER o
             LEFT JOIN COLLECTION_SLOT cs ON o.SLOT_ID = cs.SLOT_ID
             WHERE o.ORDER_ID = :oid',
            ['oid' => $orderId]
        ));
        if (!$order) return null;

        $order['ITEMS'] = db_fetch_all(db_execute(
            'SELECT oi.ORDER_ITEM_ID, oi.PRODUCT_ID, oi.QUANTITY, oi.SUBTOTAL,
                    p.NAME, p.PRODUCT_IMAGE, p.PRICE
             FROM ORDER_ITEM oi JOIN PRODUCT p ON oi.PRODUCT_ID = p.PRODUCT_ID
             WHERE oi.ORDER_ID = :oid',
            ['oid' => $orderId]
        ));
        $order['PAYMENT'] = db_fetch_one(db_execute(
            'SELECT PAYMENT_ID, PAYMENT_METHOD, AMOUNT, PAYMENT_STATUS
             FROM PAYMENT WHERE ORDER_ID = :oid',
            ['oid' => $orderId]
        ));
        return $order;
    }

    // B7-08: order history.
    public static function listForUser(int $userId): array
    {
        return db_fetch_all(db_execute(
            'SELECT ORDER_ID, ORDER_DATE, TOTAL_AMOUNT, STATUS
             FROM CUSTOMER_ORDER WHERE USER_ID = :uid ORDER BY ORDER_ID DESC',
            ['uid' => $userId]
        ));
    }

    // C4-01: orders containing a specific shop's products.
    public static function listForShop(int $shopId): array
    {
        return db_fetch_all(db_execute(
            'SELECT DISTINCT o.ORDER_ID, o.ORDER_DATE, o.STATUS, o.TOTAL_AMOUNT
             FROM CUSTOMER_ORDER o
             JOIN ORDER_ITEM oi ON o.ORDER_ID = oi.ORDER_ID
             JOIN PRODUCT p ON oi.PRODUCT_ID = p.PRODUCT_ID
             WHERE p.SHOP_ID = :sid
             ORDER BY o.ORDER_ID DESC',
            ['sid' => $shopId]
        ));
    }

    // B7-06: cancel.
    public static function cancel(int $orderId, int $userId): bool
    {
        $stmt = db_execute(
            "UPDATE CUSTOMER_ORDER SET STATUS = 'CANCELLED'
             WHERE ORDER_ID = :oid AND USER_ID = :uid AND STATUS IN ('PENDING', 'CONFIRMED')",
            ['oid' => $orderId, 'uid' => $userId]
        );
        return oci_num_rows($stmt) > 0;
    }
}
