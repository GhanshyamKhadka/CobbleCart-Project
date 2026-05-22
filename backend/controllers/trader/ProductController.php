<?php
// Trader product management. Covers C3-01..C3-04.
// Routes:
//   GET    /backend/trader/products
//   POST   /backend/trader/products
//   PUT    /backend/trader/products/{id}
//   DELETE /backend/trader/products/{id}

class TraderProductController
{
    public static function index(): void
    {
        require_trader();
        respond_ok(['products' => Product::listForShop(current_shop_id())]);
    }

    public static function create(): void
    {
        require_trader();
        $data = input_data();
        require_fields($data, ['name', 'price', 'stock_quantity']);
        $productId = Product::create(current_shop_id(), $data);
        respond_ok(['product_id' => $productId], 'Product submitted for approval');
    }

    public static function update(int $productId): void
    {
        require_trader();
        $data = input_data();
        require_fields($data, ['name', 'price', 'stock_quantity']);
        $ok = Product::update($productId, current_shop_id(), $data);
        if (!$ok) {
            respond_error('Product not found in your shop', 404);
        }
        respond_ok(null, 'Product updated');
    }

    public static function delete(int $productId): void
    {
        require_trader();
        $ok = Product::delete($productId, current_shop_id());
        if (!$ok) {
            respond_error('Product not found in your shop', 404);
        }
        respond_ok(null, 'Product deleted');
    }
}
