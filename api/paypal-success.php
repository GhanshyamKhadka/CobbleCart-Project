<?php
// PayPal sandbox returns here after EACH per-shop payment.
//
// 1. `custom` carries the PAYOUT_ID just paid → mark that ORDER_PAYOUT row PAID.
// 2. If more PENDING payouts exist for the same order → redirect back to
//    paypal-redirect.php to pay the next shop directly.
// 3. If this was the last payout (or ?finalize=1 was passed) → mark the order
//    PAID overall, insert one PAYMENT row for the order total, send receipt
//    emails, redirect to invoice.html.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/send-email.php';

header_remove('Content-Type');
header('Content-Type: text/html; charset=UTF-8');

$paypalCfgPath = __DIR__ . '/paypal-config.local.php';
if (!is_file($paypalCfgPath)) {
    http_response_code(500);
    echo 'paypal-config.local.php is missing.';
    exit;
}
$paypal = require $paypalCfgPath;

// `custom` is either a numeric payout id (per-trader mode) or "ORDER:N"
// (platform-receiver mode). Detect both.
$customRaw = (string)($_REQUEST['custom'] ?? $_REQUEST['cm'] ?? '');
$platformMode = false;
$payoutId  = 0;
if (preg_match('/^ORDER:(\d+)$/', $customRaw, $m)) {
    $platformMode = true;
    $orderIdParam = (int)$m[1];
} else {
    $payoutId = (int)$customRaw;
}
$paymentStatus = strtoupper(trim((string)($_REQUEST['st'] ?? $_REQUEST['payment_status'] ?? '')));
$amountPaid    = (float)($_REQUEST['amt'] ?? $_REQUEST['mc_gross'] ?? 0);
$payerId       = trim((string)($_REQUEST['PayerID'] ?? ''));
$finalize      = !empty($_REQUEST['finalize']) || $platformMode;
if (!isset($orderIdParam)) $orderIdParam = (int)($_REQUEST['order_id'] ?? 0);

// PayPal sandbox "Return to Merchant" strips `custom`. Fall back to the
// payout id we stashed in the session before redirecting to PayPal.
if ($payoutId <= 0 && !empty($_SESSION['paypal_pending_payout_id'])) {
    $payoutId = (int)$_SESSION['paypal_pending_payout_id'];
}

try {
    // Resolve the order we're processing. Three paths get us here:
    //   (a) Normal PayPal return — custom = payout_id → look up order via payout.
    //   (b) `finalize=1` redirect from paypal-redirect.php when no payouts remain.
    //   (c) Fallback PayerID-only return — use session.
    $orderId = 0;
    $payout  = null;

    if ($payoutId > 0) {
        $stmt = oci_execute_stmt(
            'SELECT PAYOUT_ID, ORDER_ID, PAYOUT_STATUS, GROSS_AMOUNT, PAYPAL_EMAIL
             FROM ORDER_PAYOUT WHERE PAYOUT_ID = :pid',
            ['pid' => $payoutId]
        );
        $payout = oci_fetch_assoc_one($stmt);
        if ($payout) $orderId = (int)$payout['ORDER_ID'];
    }
    if ($orderId <= 0 && $orderIdParam > 0)  $orderId = $orderIdParam;
    if ($orderId <= 0 && !empty($_SESSION['paypal_pending_order_id'])) {
        $orderId = (int)$_SESSION['paypal_pending_order_id'];
    }
    if ($orderId <= 0) {
        http_response_code(400);
        echo 'Missing order id in PayPal return. Please retry from your basket.';
        exit;
    }

    // Load the order header for status checks + session restore
    $stmt = oci_execute_stmt(
        'SELECT ORDER_ID, USER_ID, TOTAL_AMOUNT, STATUS FROM CUSTOMER_ORDER WHERE ORDER_ID = :order_id',
        ['order_id' => $orderId]
    );
    $order = oci_fetch_assoc_one($stmt);
    if (!$order) {
        http_response_code(404);
        echo 'Order not found.';
        exit;
    }

    // Restore the customer session (cookie may have been lost crossing PayPal).
    $sessStmt = oci_execute_stmt(
        'SELECT USER_ID, ROLE, FIRST_NAME, LAST_NAME, EMAIL FROM USERS WHERE USER_ID = :owner_id',
        ['owner_id' => (int)$order['USER_ID']]
    );
    $sessUser = oci_fetch_assoc_one($sessStmt);
    if ($sessUser) {
        $_SESSION['user_id'] = (int)$sessUser['USER_ID'];
        $_SESSION['role']    = strtolower($sessUser['ROLE'] ?? 'customer');
        $_SESSION['name']    = trim(($sessUser['FIRST_NAME'] ?? '') . ' ' . ($sessUser['LAST_NAME'] ?? ''));
        $_SESSION['email']   = $sessUser['EMAIL'] ?? '';
    }

    // Already-paid order: jump straight to invoice.
    if ($order['STATUS'] === 'PAID') {
        unset($_SESSION['paypal_pending_order_id'], $_SESSION['paypal_pending_payout_id']);
        header('Location: ' . $paypal['site_base_url'] . '/frontend/customer/invoice.html?order_id=' . $orderId);
        exit;
    }
    if ($order['STATUS'] !== 'PENDING_PAYMENT') {
        http_response_code(409);
        echo 'Order is in state ' . htmlspecialchars($order['STATUS']) . ', cannot mark as paid.';
        exit;
    }

    // === Step 1: mark payouts paid (idempotently) =============================
    if ($platformMode) {
        // One platform-level payment covers the whole order — flip every
        // payout for this order to PAID at once.
        oci_execute_stmt(
            "UPDATE ORDER_PAYOUT SET PAYOUT_STATUS = 'PAID'
             WHERE ORDER_ID = :oid AND PAYOUT_STATUS = 'PENDING'",
            ['oid' => $orderId],
            false
        );
        db_commit();
    } elseif ($payout && $payout['PAYOUT_STATUS'] === 'PENDING') {
        oci_execute_stmt(
            "UPDATE ORDER_PAYOUT SET PAYOUT_STATUS = 'PAID' WHERE PAYOUT_ID = :pid",
            ['pid' => (int)$payout['PAYOUT_ID']],
            false
        );
        db_commit();
    }

    // === Step 2: are there more payouts left for this order? ==================
    $stmt = oci_execute_stmt(
        "SELECT COUNT(*) AS PENDING_LEFT FROM ORDER_PAYOUT
         WHERE ORDER_ID = :oid AND PAYOUT_STATUS = 'PENDING'",
        ['oid' => $orderId]
    );
    $pendingLeft = (int)(oci_fetch_assoc_one($stmt)['PENDING_LEFT'] ?? 0);

    if ($pendingLeft > 0 && !$finalize) {
        // Hand the customer to PayPal again for the next shop.
        unset($_SESSION['paypal_pending_payout_id']);
        header('Location: ' . $paypal['site_base_url'] . '/api/paypal-redirect.php?order_id=' . $orderId);
        exit;
    }

    // === Step 3: all shops paid → finalize the order ==========================
    $expectedTotal = round((float)$order['TOTAL_AMOUNT'], 2);
    $paymentId = oracle_nextval('seq_payment');
    oci_execute_stmt(
        'INSERT INTO PAYMENT (PAYMENT_ID, ORDER_ID, PAYMENT_METHOD, AMOUNT, PAYMENT_STATUS)
         VALUES (:payment_id, :order_id, :method, :amount, :status)',
        [
            'payment_id' => $paymentId,
            'order_id'   => $orderId,
            'method'     => 'PAYPAL',
            'amount'     => $expectedTotal,
            'status'     => 'PAID',
        ],
        false
    );
    oci_execute_stmt(
        "UPDATE CUSTOMER_ORDER SET STATUS = 'PAID' WHERE ORDER_ID = :order_id",
        ['order_id' => $orderId],
        false
    );
    db_commit();

    // Pull everything needed for the receipt emails (one query, then we split per shop in PHP)
    $stmt = oci_execute_stmt(
        'SELECT u.FIRST_NAME AS CUST_FIRST, u.LAST_NAME AS CUST_LAST, u.EMAIL AS CUST_EMAIL,
                co.ORDER_ID, co.ORDER_DATE, co.TOTAL_AMOUNT,
                cs.COLLECTION_DATE, cs.TIME_SLOT,
                oi.QUANTITY, oi.SUBTOTAL,
                p.NAME AS PRODUCT_NAME,
                s.SHOP_ID, s.SHOP_NAME, s.USER_ID AS TRADER_USER_ID,
                tu.EMAIL AS TRADER_EMAIL, tu.FIRST_NAME AS TRADER_FIRST
         FROM CUSTOMER_ORDER co
         JOIN USERS u             ON u.USER_ID = co.USER_ID
         LEFT JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = co.SLOT_ID
         JOIN ORDER_ITEM oi       ON oi.ORDER_ID = co.ORDER_ID
         JOIN PRODUCT p           ON p.PRODUCT_ID = oi.PRODUCT_ID
         JOIN SHOP s              ON s.SHOP_ID = p.SHOP_ID
         JOIN USERS tu            ON tu.USER_ID = s.USER_ID
         WHERE co.ORDER_ID = :order_id
         ORDER BY s.SHOP_ID, oi.ORDER_ITEM_ID',
        ['order_id' => $orderId]
    );
    $rows = oci_fetch_assoc_all($stmt);

    $customerEmail = $rows[0]['CUST_EMAIL'] ?? null;
    $customerName  = trim(($rows[0]['CUST_FIRST'] ?? '') . ' ' . ($rows[0]['CUST_LAST'] ?? ''));
    $collectionInfo = '';
    if (!empty($rows[0]['COLLECTION_DATE'])) {
        $collectionInfo = "\nCollection: " . $rows[0]['COLLECTION_DATE']
            . ' (' . ($rows[0]['TIME_SLOT'] ?? '') . ')';
    }

    // ONE combined receipt to the customer (multi-shop summary)
    $customerLines = ["Hi $customerName,", '', "Thanks for your order with CobbleCart!", '', "Order #$orderId"];
    foreach ($rows as $r) {
        $customerLines[] = sprintf('  %s x %d  —  %s %s  (%s)',
            $r['PRODUCT_NAME'], (int)$r['QUANTITY'],
            $paypal['currency'], number_format((float)$r['SUBTOTAL'], 2),
            $r['SHOP_NAME']);
    }
    $customerLines[] = '';
    $customerLines[] = sprintf('Total paid: %s %s', $paypal['currency'], number_format($expectedTotal, 2));
    $customerLines[] = $collectionInfo;
    $customerLines[] = '';
    $customerLines[] = '— CobbleCart';
    if ($customerEmail) {
        sendEmail($customerEmail, $customerName, "CobbleCart order #$orderId — receipt", implode("\n", $customerLines));
    }

    // One filtered receipt to each trader (their shop's items only). Funds
    // already landed directly in their PayPal sandbox via the per-shop redirects.
    $byShop = [];
    foreach ($rows as $r) {
        $sid = (int)$r['SHOP_ID'];
        if (!isset($byShop[$sid])) {
            $byShop[$sid] = [
                'shop_name'    => $r['SHOP_NAME'],
                'trader_email' => $r['TRADER_EMAIL'],
                'trader_first' => $r['TRADER_FIRST'],
                'items'        => [],
                'subtotal'     => 0,
            ];
        }
        $byShop[$sid]['items'][] = $r;
        $byShop[$sid]['subtotal'] += (float)$r['SUBTOTAL'];
    }
    foreach ($byShop as $sid => $shop) {
        if (empty($shop['trader_email'])) continue;
        $lines = [
            'Hi ' . $shop['trader_first'] . ',',
            '',
            "A customer just placed order #$orderId at CobbleCart that includes products from your shop, " . $shop['shop_name'] . ".",
            '',
            'Your items in this order:',
        ];
        foreach ($shop['items'] as $r) {
            $lines[] = sprintf('  %s x %d  —  %s %s',
                $r['PRODUCT_NAME'], (int)$r['QUANTITY'],
                $paypal['currency'], number_format((float)$r['SUBTOTAL'], 2));
        }
        $lines[] = '';
        $lines[] = sprintf('Total received in your PayPal: %s %s',
            $paypal['currency'], number_format($shop['subtotal'], 2));
        $lines[] = '';
        $lines[] = "Customer: $customerName <$customerEmail>";
        if ($collectionInfo) $lines[] = trim($collectionInfo);
        $lines[] = '';
        $lines[] = '— CobbleCart';

        sendEmail($shop['trader_email'], $shop['trader_first'],
            "CobbleCart order #$orderId — your shop's items", implode("\n", $lines));
    }

    unset($_SESSION['paypal_pending_order_id'], $_SESSION['paypal_pending_payout_id']);
    header('Location: ' . $paypal['site_base_url'] . '/frontend/customer/invoice.html?order_id=' . $orderId);
    exit;
} catch (Throwable $e) {
    db_rollback();
    http_response_code(500);
    echo 'Payment processing failed: ' . htmlspecialchars($e->getMessage());
    exit;
}
