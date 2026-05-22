<?php
// Centralized route table. The front controller (public/index.php) dispatches here.
// Format: [METHOD, PATH-PATTERN, HANDLER]. Patterns use {id} as an integer placeholder.

return [
    // ---- Auth (B1, B2, C1, D1, B3-03) ----
    ['POST',   '/auth/register-customer',  ['AuthController',         'registerCustomer']],
    ['POST',   '/auth/register-trader',    ['AuthController',         'registerTrader']],
    ['POST',   '/auth/login',              ['AuthController',         'login']],
    ['POST',   '/auth/logout',             ['AuthController',         'logout']],
    ['GET',    '/auth/me',                 ['AuthController',         'me']],
    ['POST',   '/auth/change-password',    ['AuthController',         'changePassword']],

    // ---- Public catalog (A1, A2, A4-02, B4) ----
    ['GET',    '/products',                ['PublicProductController', 'index']],
    ['GET',    '/products/{id}',           ['PublicProductController', 'show']],
    ['GET',    '/products/{id}/reviews',   ['PublicProductController', 'reviews']],
    ['GET',    '/categories',              ['PublicProductController', 'categories']],
    ['GET',    '/shops',                   ['PublicProductController', 'shops']],
    ['GET',    '/shops/{id}',              ['PublicProductController', 'showShop']],

    // ---- Customer (B3, B5, B6, B7, B8, B9, A3) ----
    ['GET',    '/customer/profile',        ['CustomerProfileController', 'show']],
    ['PUT',    '/customer/profile',        ['CustomerProfileController', 'update']],
    ['GET',    '/customer/cart',           ['CustomerCartController',    'showCart']],
    ['POST',   '/customer/cart',           ['CustomerCartController',    'addToCart']],
    ['PUT',    '/customer/cart/{id}',      ['CustomerCartController',    'updateCart']],
    ['DELETE', '/customer/cart/{id}',      ['CustomerCartController',    'removeFromCart']],
    ['GET',    '/customer/wishlist',       ['CustomerCartController',    'listWishlist']],
    ['POST',   '/customer/wishlist',       ['CustomerCartController',    'addToWishlist']],
    ['DELETE', '/customer/wishlist/{id}',  ['CustomerCartController',    'removeFromWishlist']],
    ['GET',    '/customer/orders',         ['CustomerOrderController',   'index']],
    ['POST',   '/customer/orders',         ['CustomerOrderController',   'place']],
    ['GET',    '/customer/orders/{id}',    ['CustomerOrderController',   'show']],
    ['POST',   '/customer/orders/{id}/cancel', ['CustomerOrderController', 'cancel']],
    ['GET',    '/customer/slots',          ['CustomerOrderController',   'slots']],
    ['POST',   '/customer/reviews',        ['CustomerOrderController',   'createReview']],

    // ---- Trader (C2, C3, C4) ----
    ['GET',    '/trader/shop',             ['TraderShopController',    'showShop']],
    ['PUT',    '/trader/shop',             ['TraderShopController',    'updateShop']],
    ['GET',    '/trader/products',         ['TraderProductController', 'index']],
    ['POST',   '/trader/products',         ['TraderProductController', 'create']],
    ['PUT',    '/trader/products/{id}',    ['TraderProductController', 'update']],
    ['DELETE', '/trader/products/{id}',    ['TraderProductController', 'delete']],
    ['GET',    '/trader/orders',           ['TraderShopController',    'orders']],
    ['GET',    '/trader/reports/sales',    ['TraderShopController',    'sales']],
    ['GET',    '/trader/reports/stock',    ['TraderShopController',    'stock']],

    // ---- Admin (D1, D2, D3, D4) ----
    ['GET',    '/admin/users',                 ['AdminController', 'listUsers']],
    ['GET',    '/admin/shops',                 ['AdminController', 'listShops']],
    ['POST',   '/admin/shops/{id}/approve',    ['AdminController', 'approveShop']],
    ['POST',   '/admin/shops/{id}/suspend',    ['AdminController', 'suspendShop']],
    ['GET',    '/admin/products',              ['AdminController', 'listProducts']],
    ['POST',   '/admin/products/{id}/approve', ['AdminController', 'approveProduct']],
    ['POST',   '/admin/products/{id}/reject',  ['AdminController', 'rejectProduct']],
    ['GET',    '/admin/reports/overview',      ['AdminController', 'overviewReport']],
    ['GET',    '/admin/reports/payments',      ['AdminController', 'paymentsReport']],
];
