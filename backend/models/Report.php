<?php
// Report model. Covers C4-01..C4-03 (trader dashboard) and D3-01, D4-01 (admin reports).

class Report
{
    // C4-01: sales rollup for a single shop.
    public static function shopSales(int $shopId, ?string $period = null): array
    {
        $whereDate = '';
        if ($period === 'daily') {
            $whereDate = " AND o.ORDER_DATE >= TRUNC(SYSDATE)";
        } elseif ($period === 'monthly') {
            $whereDate = " AND o.ORDER_DATE >= TRUNC(SYSDATE, 'MM')";
        }

        $sql = "SELECT COUNT(DISTINCT o.ORDER_ID) AS ORDERS,
                       NVL(SUM(oi.SUBTOTAL), 0) AS REVENUE,
                       NVL(SUM(oi.QUANTITY), 0) AS UNITS_SOLD
                FROM CUSTOMER_ORDER o
                JOIN ORDER_ITEM oi ON o.ORDER_ID = oi.ORDER_ID
                JOIN PRODUCT p ON oi.PRODUCT_ID = p.PRODUCT_ID
                WHERE p.SHOP_ID = :sid $whereDate";
        return db_fetch_one(db_execute($sql, ['sid' => $shopId])) ?? [];
    }

    // C4-03: stock report for a shop.
    public static function shopStock(int $shopId): array
    {
        return db_fetch_all(db_execute(
            'SELECT PRODUCT_ID, NAME, PRICE, STOCK_QUANTITY, APPROVAL_STATUS
             FROM PRODUCT WHERE SHOP_ID = :sid ORDER BY STOCK_QUANTITY ASC',
            ['sid' => $shopId]
        ));
    }

    // D3-01: platform-wide performance.
    public static function platformOverview(): array
    {
        $users     = db_fetch_one(db_execute('SELECT COUNT(*) AS C FROM USERS'));
        $traders   = db_fetch_one(db_execute("SELECT COUNT(*) AS C FROM USERS WHERE ROLE = 'TRADER'"));
        $customers = db_fetch_one(db_execute("SELECT COUNT(*) AS C FROM USERS WHERE ROLE = 'CUSTOMER'"));
        $orders    = db_fetch_one(db_execute('SELECT COUNT(*) AS C, NVL(SUM(TOTAL_AMOUNT), 0) AS R FROM CUSTOMER_ORDER'));
        $products  = db_fetch_one(db_execute('SELECT COUNT(*) AS C FROM PRODUCT'));

        return [
            'users'     => (int)($users['C']     ?? 0),
            'traders'   => (int)($traders['C']   ?? 0),
            'customers' => (int)($customers['C'] ?? 0),
            'orders'    => (int)($orders['C']    ?? 0),
            'revenue'   => (float)($orders['R']  ?? 0),
            'products'  => (int)($products['C']  ?? 0),
        ];
    }

    // D4-01: payment audit log.
    public static function paymentAudit(?string $status = null): array
    {
        $sql = 'SELECT pay.PAYMENT_ID, pay.ORDER_ID, pay.PAYMENT_METHOD, pay.AMOUNT, pay.PAYMENT_STATUS,
                       o.ORDER_DATE, o.USER_ID
                FROM PAYMENT pay
                JOIN CUSTOMER_ORDER o ON pay.ORDER_ID = o.ORDER_ID';
        $params = [];
        if ($status) {
            $sql .= ' WHERE pay.PAYMENT_STATUS = :st';
            $params['st'] = strtoupper($status);
        }
        $sql .= ' ORDER BY pay.PAYMENT_ID DESC';
        return db_fetch_all(db_execute($sql, $params));
    }
}
