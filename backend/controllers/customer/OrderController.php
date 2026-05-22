<?php
// Customer orders + reviews. Covers B7-*, B8-01, B9-*, A4-01, A4-02.
// Routes:
//   GET  /backend/customer/orders                    (B7-08)
//   GET  /backend/customer/orders/{id}               (B7-07, B9-01, B9-02)
//   POST /backend/customer/orders                    (B7-01..B7-05)
//   POST /backend/customer/orders/{id}/cancel        (B7-06)
//   GET  /backend/customer/slots                     (B7-01)
//   POST /backend/customer/reviews                   (A4-01, A4-02, B8-01)

class CustomerOrderController
{
    public static function index(): void
    {
        require_customer();
        respond_ok(['orders' => Order::listForUser(current_user_id())]);
    }

    public static function show(int $orderId): void
    {
        require_customer();
        $order = Order::findById($orderId);
        if (!$order || (int)$order['USER_ID'] !== current_user_id()) {
            respond_not_found('Order');
        }
        respond_ok(['order' => $order]);
    }

    public static function place(): void
    {
        require_customer();
        $data = input_data();
        require_fields($data, ['payment_method']);

        try {
            $result = Order::placeFromCart(current_user_id(), $data);
            respond_ok($result, 'Order placed successfully');
        } catch (RuntimeException $e) {
            respond_error($e->getMessage(), 400);
        } catch (Throwable $e) {
            db_rollback();
            respond_error('Failed to place order: ' . $e->getMessage(), 500);
        }
    }

    public static function cancel(int $orderId): void
    {
        require_customer();
        $cancelled = Order::cancel($orderId, current_user_id());
        if (!$cancelled) {
            respond_error('Order cannot be cancelled', 400);
        }
        respond_ok(null, 'Order cancelled');
    }

    public static function slots(): void
    {
        $rows = db_fetch_all(db_execute(
            "SELECT SLOT_ID, COLLECTION_DATE, TIME_SLOT, MAX_ORDERS
             FROM COLLECTION_SLOT
             WHERE COLLECTION_DATE >= TRUNC(SYSDATE)
             ORDER BY COLLECTION_DATE, TIME_SLOT"
        ));
        respond_ok(['slots' => $rows]);
    }

    public static function createReview(): void
    {
        require_customer();
        $data = input_data();
        require_fields($data, ['product_id', 'rating']);
        try {
            $id = Review::create(
                current_user_id(),
                (int)$data['product_id'],
                (int)$data['rating'],
                (string)($data['comments'] ?? '')
            );
            respond_ok(['review_id' => $id], 'Review submitted');
        } catch (InvalidArgumentException $e) {
            respond_error($e->getMessage(), 400);
        }
    }
}
