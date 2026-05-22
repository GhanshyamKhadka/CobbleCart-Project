<?php
// Public product browsing. No authentication required.
// Routes:
//   GET /backend/products              (A2-01, A2-02, A2-03, B4-01)
//   GET /backend/products/{id}         (A1-01..A1-07, B4-02..B4-06)
//   GET /backend/products/{id}/reviews (A4-02, B4-05)
//   GET /backend/categories            (A1-05, A2-01)
//   GET /backend/shops                 (A2-04)
//   GET /backend/shops/{id}            (A2-04)

class PublicProductController
{
    public static function index(): void
    {
        $products = Product::search($_GET, current_user_role(), current_shop_id());
        respond_ok(['products' => $products]);
    }

    public static function show(int $productId): void
    {
        $product = Product::findById($productId);
        if (!$product) {
            respond_not_found('Product');
        }
        $product['AVERAGE_RATING'] = Review::averageRating($productId);
        respond_ok(['product' => $product]);
    }

    public static function reviews(int $productId): void
    {
        respond_ok([
            'reviews'        => Review::listForProduct($productId),
            'average_rating' => Review::averageRating($productId),
        ]);
    }

    public static function categories(): void
    {
        $rows = db_fetch_all(db_execute(
            'SELECT PRODUCT_TYPE_ID, TYPE_NAME FROM PRODUCT_TYPE ORDER BY TYPE_NAME'
        ));
        respond_ok(['categories' => $rows]);
    }

    public static function shops(): void
    {
        respond_ok(['shops' => Shop::listAll('APPROVED')]);
    }

    public static function showShop(int $shopId): void
    {
        $shop = Shop::findById($shopId);
        if (!$shop) {
            respond_not_found('Shop');
        }
        $shop['PRODUCTS'] = Product::search(['shop_id' => $shopId], null, null);
        respond_ok(['shop' => $shop]);
    }
}
