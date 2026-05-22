<?php
// Customer cart + wishlist. Covers A3-01, A3-02, A3-04, B5-*, B6-01.
// Routes:
//   GET    /backend/customer/cart                       (B5-04)
//   POST   /backend/customer/cart                       (A3-01, B5-01)
//   PUT    /backend/customer/cart/{product_id}          (B5-02)
//   DELETE /backend/customer/cart/{product_id}          (A3-04, B5-03)
//   GET    /backend/customer/wishlist                   (B6-01)
//   POST   /backend/customer/wishlist                   (A3-02)
//   DELETE /backend/customer/wishlist/{product_id}      (A3-04)

class CustomerCartController
{
    public static function showCart(): void
    {
        require_customer();
        respond_ok(Cart::getCart(current_user_id()));
    }

    public static function addToCart(): void
    {
        require_customer();
        $data = input_data();
        require_fields($data, ['product_id']);
        $qty = max(1, (int)($data['quantity'] ?? 1));
        Cart::addItem(current_user_id(), (int)$data['product_id'], $qty);
        respond_ok(Cart::getCart(current_user_id()), 'Added to cart');
    }

    public static function updateCart(int $productId): void
    {
        require_customer();
        $data = input_data();
        $qty  = (int)($data['quantity'] ?? 0);
        Cart::updateQuantity(current_user_id(), $productId, $qty);
        respond_ok(Cart::getCart(current_user_id()), 'Cart updated');
    }

    public static function removeFromCart(int $productId): void
    {
        require_customer();
        Cart::removeItem(current_user_id(), $productId);
        respond_ok(Cart::getCart(current_user_id()), 'Item removed');
    }

    public static function listWishlist(): void
    {
        require_customer();
        respond_ok(['wishlist' => Wishlist::list(current_user_id())]);
    }

    public static function addToWishlist(): void
    {
        require_customer();
        $data = input_data();
        require_fields($data, ['product_id']);
        Wishlist::add(current_user_id(), (int)$data['product_id']);
        respond_ok(['wishlist' => Wishlist::list(current_user_id())], 'Added to wishlist');
    }

    public static function removeFromWishlist(int $productId): void
    {
        require_customer();
        Wishlist::remove(current_user_id(), $productId);
        respond_ok(['wishlist' => Wishlist::list(current_user_id())], 'Removed from wishlist');
    }
}
