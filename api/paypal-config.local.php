<?php
return [
    'sandbox_url'    => 'https://www.sandbox.paypal.com/cgi-bin/webscr',

    // Platform-level PayPal account. Used as the single receiver when
    // `use_platform_receiver` is true (or as a fallback when a trader has no
    // PayPal email on file).
    'business_email' => 'admin@cobblecart.com',

    'currency'       => 'USD',
    'site_base_url'  => 'http://localhost:8000',

    // ---- Receiver strategy --------------------------------------------------
    // false (default): customer pays each TRADER directly, once per shop.
    //                  Best when every trader has a valid sandbox business
    //                  account. PayPal credits each trader directly.
    // true:            customer pays the PLATFORM (admin@cobblecart.com) once
    //                  for the full order amount. The per-trader split is
    //                  recorded in ORDER_PAYOUT for later settlement. Use this
    //                  if PayPal sandbox rejects per-trader payments with
    //                  "merchant can't be completed".
    'use_platform_receiver' => false,
];
