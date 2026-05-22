<?php
// Copy this file to paypal-config.local.php and fill in your sandbox values.
// paypal-config.local.php is gitignored.
return [
    // PayPal sandbox endpoint. For production, swap to https://www.paypal.com/cgi-bin/webscr
    'sandbox_url' => 'https://www.sandbox.paypal.com/cgi-bin/webscr',

    // The MERCHANT (business) sandbox account that COLLECTS the payment.
    // Traders are notified separately by email — they do not receive the
    // PayPal funds in this flow. (Real payouts to traders would be a
    // separate PayPal Payouts API call or manual settlement.)
    'business_email' => 'admin@cobblecart.com',

    // 3-letter currency. Must match your sandbox account's currency.
    'currency' => 'USD',

    // Base URL of the website (used to build return/cancel URLs PayPal
    // redirects back to). No trailing slash.
    'site_base_url' => 'http://localhost:8000',
];
