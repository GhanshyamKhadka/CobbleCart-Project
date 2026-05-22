<?php
// Trader shop profile + dashboard. Covers C2-01, C4-01..C4-03.
// Routes:
//   GET  /backend/trader/shop
//   PUT  /backend/trader/shop
//   GET  /backend/trader/orders
//   GET  /backend/trader/reports/sales?period=daily|monthly
//   GET  /backend/trader/reports/stock

class TraderShopController
{
    public static function showShop(): void
    {
        require_trader();
        $shop = Shop::findById(current_shop_id());
        if (!$shop) respond_not_found('Shop');
        respond_ok(['shop' => $shop]);
    }

    public static function updateShop(): void
    {
        require_trader();
        $data = input_data();
        require_fields($data, ['shop_name']);
        Shop::update(current_shop_id(), $data);
        respond_ok(null, 'Shop updated');
    }

    public static function orders(): void
    {
        require_trader();
        respond_ok(['orders' => Order::listForShop(current_shop_id())]);
    }

    public static function sales(): void
    {
        require_trader();
        $period = $_GET['period'] ?? null;
        respond_ok(['sales' => Report::shopSales(current_shop_id(), $period), 'period' => $period]);
    }

    public static function stock(): void
    {
        require_trader();
        respond_ok(['stock' => Report::shopStock(current_shop_id())]);
    }
}
