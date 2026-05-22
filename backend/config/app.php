<?php
// Global application settings used by the backend layer.

return [
    'name'           => 'CobbleCart Backend',
    'version'        => '1.0.0',
    'session_name'   => 'COBBLECART_SID',
    'password_algo'  => PASSWORD_BCRYPT,
    'password_cost'  => 12,

    // Account roles. Used by middleware/auth.php and controllers/*.
    'roles' => [
        'customer' => 'CUSTOMER',
        'trader'   => 'TRADER',
        'admin'    => 'ADMIN',
    ],

    // Order lifecycle states (B7, B9).
    'order_status' => ['PENDING', 'CONFIRMED', 'PROCESSING', 'READY', 'COMPLETED', 'CANCELLED'],

    // Product approval states. New trader products require admin approval (C1-05).
    'product_approval' => ['PENDING', 'APPROVED', 'REJECTED'],

    'cors' => [
        'allow_origin'  => '*',
        'allow_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'allow_headers' => 'Content-Type, Authorization',
    ],
];
