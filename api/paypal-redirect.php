<?php
// Routes the customer through PayPal once per shop in their order.
// For each PENDING ORDER_PAYOUT row, we build a PayPal form whose `business`
// is THAT shop's trader's PayPal email, so the funds land directly in their
// sandbox account. The customer is bounced through PayPal N times for an
// N-shop basket; the chaining is handled by paypal-success.php.

require_once __DIR__ . '/config.php';

header_remove('Content-Type');
header('Content-Type: text/html; charset=UTF-8');

require_role('customer');

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    echo 'Missing order_id.';
    exit;
}

$paypalCfgPath = __DIR__ . '/paypal-config.local.php';
if (!is_file($paypalCfgPath)) {
    http_response_code(500);
    echo 'paypal-config.local.php is missing.';
    exit;
}
$paypal = require $paypalCfgPath;

try {
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
    if ((int)$order['USER_ID'] !== (int)current_user_id()) {
        http_response_code(403);
        echo 'You can only pay for your own orders.';
        exit;
    }
    if ($order['STATUS'] !== 'PENDING_PAYMENT') {
        // Already paid (or cancelled).
        header('Location: ' . $paypal['site_base_url'] . '/frontend/customer/invoice.html?order_id=' . $orderId);
        exit;
    }

    // ---- PLATFORM-RECEIVER MODE ---------------------------------------------
    // If the operator turned this on (paypal-config.local.php), all of the
    // order's money goes to admin@cobblecart.com in ONE transaction. The
    // per-trader split is recorded in ORDER_PAYOUT for later manual
    // settlement. Use this when PayPal sandbox keeps rejecting per-trader
    // direct payments with "merchant can't be completed".
    if (!empty($paypal['use_platform_receiver'])) {
        $platformEmail = $paypal['business_email'];
        $platformTotal = number_format((float)$order['TOTAL_AMOUNT'], 2, '.', '');

        $_SESSION['paypal_pending_order_id']  = $orderId;
        $_SESSION['paypal_pending_payout_id'] = 0;

        $returnUrl = $paypal['site_base_url'] . '/api/paypal-success.php';
        $cancelUrl = $paypal['site_base_url'] . '/api/paypal-cancel.php?order_id=' . $orderId;

        $platformFields = [
            'cmd'           => '_xclick',
            'business'      => $platformEmail,
            'item_name'     => 'CobbleCart order #' . $orderId,
            'item_number'   => 'ORDER-' . $orderId,
            'amount'        => $platformTotal,
            'currency_code' => $paypal['currency'],
            'quantity'      => '1',
            'custom'        => 'ORDER:' . $orderId,        // paypal-success.php reads this
            'return'        => $returnUrl,
            'cancel_return' => $cancelUrl,
            'rm'            => '1',
            'no_shipping'   => '1',
        ];
        ?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Redirecting to PayPal</title>
<style>body{font-family:system-ui,sans-serif;text-align:center;padding:60px 20px;color:#333}
.spinner{display:inline-block;width:40px;height:40px;border:4px solid #eee;border-top-color:#f0a020;border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}</style></head>
<body>
  <div class="spinner"></div>
  <h2>Redirecting you to PayPal…</h2>
  <p>Order #<?= $orderId ?> &middot; <?= htmlspecialchars($paypal['currency']) ?> <?= htmlspecialchars($platformTotal) ?></p>
  <p style="font-size:12.5px;color:#666;">Payment goes to <strong><?= htmlspecialchars($platformEmail) ?></strong>. CobbleCart will settle each trader's portion separately.</p>
  <form id="paypal-form" action="<?= htmlspecialchars($paypal['sandbox_url']) ?>" method="post">
    <?php foreach ($platformFields as $n => $v): ?>
      <input type="hidden" name="<?= htmlspecialchars($n) ?>" value="<?= htmlspecialchars($v) ?>">
    <?php endforeach; ?>
  </form>
  <script>document.getElementById('paypal-form').submit();</script>
</body></html><?php
        exit;
    }

    // ---- DEFAULT: per-trader direct payment mode ----------------------------
    // Find the next ORDER_PAYOUT that hasn't been paid yet.
    $stmt = oci_execute_stmt(
        "SELECT op.PAYOUT_ID, op.SHOP_ID, op.GROSS_AMOUNT, op.PAYPAL_EMAIL,
                s.SHOP_NAME,
                (SELECT COUNT(*) FROM ORDER_PAYOUT WHERE ORDER_ID = :oid1)                       AS TOTAL_PAYOUTS,
                (SELECT COUNT(*) FROM ORDER_PAYOUT WHERE ORDER_ID = :oid2 AND PAYOUT_STATUS = 'PAID') AS PAID_PAYOUTS
         FROM ORDER_PAYOUT op
         JOIN SHOP s ON s.SHOP_ID = op.SHOP_ID
         WHERE op.ORDER_ID = :oid3 AND op.PAYOUT_STATUS = 'PENDING'
         ORDER BY op.PAYOUT_ID
         FETCH FIRST 1 ROWS ONLY",
        ['oid1' => $orderId, 'oid2' => $orderId, 'oid3' => $orderId]
    );
    $nextPayout = oci_fetch_assoc_one($stmt);

    // No pending payouts means everything is paid — finalize and jump to invoice.
    if (!$nextPayout) {
        header('Location: ' . $paypal['site_base_url'] . '/api/paypal-success.php?order_id=' . $orderId . '&finalize=1');
        exit;
    }

    $payoutId  = (int)$nextPayout['PAYOUT_ID'];
    $shopId    = (int)$nextPayout['SHOP_ID'];
    $shopName  = $nextPayout['SHOP_NAME'];
    $amount    = number_format((float)$nextPayout['GROSS_AMOUNT'], 2, '.', '');
    $sellerEm  = $nextPayout['PAYPAL_EMAIL'];
    $totalP    = (int)$nextPayout['TOTAL_PAYOUTS'];
    $paidP     = (int)$nextPayout['PAID_PAYOUTS'];
    $thisStep  = $paidP + 1;

    // What this shop's items are, so the customer sees what they're paying for
    $itemsStmt = oci_execute_stmt(
        'SELECT p.NAME, oi.QUANTITY, oi.SUBTOTAL
         FROM ORDER_ITEM oi
         JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
         WHERE oi.ORDER_ID = :oid AND p.SHOP_ID = :sid',
        ['oid' => $orderId, 'sid' => $shopId]
    );
    $items = oci_fetch_assoc_all($itemsStmt);

    // Remember which order/payout the user is paying so the "Return to Merchant"
    // fallback (which strips `custom`) can still find the right rows.
    $_SESSION['paypal_pending_order_id']  = $orderId;
    $_SESSION['paypal_pending_payout_id'] = $payoutId;

    $returnUrl = $paypal['site_base_url'] . '/api/paypal-success.php';
    $cancelUrl = $paypal['site_base_url'] . '/api/paypal-cancel.php?order_id=' . $orderId;

    $itemName = sprintf('CobbleCart #%d — %s (step %d of %d)', $orderId, $shopName, $thisStep, $totalP);

    $fields = [
        'cmd'           => '_xclick',
        'business'      => $sellerEm,                 // <- THIS shop's trader, not the platform
        'item_name'     => $itemName,
        'item_number'   => 'PAYOUT-' . $payoutId,
        'amount'        => $amount,
        'currency_code' => $paypal['currency'],
        'quantity'      => '1',
        'custom'        => (string)$payoutId,          // paypal-success.php reads this
        'return'        => $returnUrl,
        'cancel_return' => $cancelUrl,
        'rm'            => '1',
        'no_shipping'   => '1',
    ];
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Order lookup failed: ' . htmlspecialchars($e->getMessage());
    exit;
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Redirecting to PayPal — CobbleCart</title>
  <style>
    body { font-family: system-ui, sans-serif; text-align: center; padding: 60px 20px; color: #333; }
    .spinner { display: inline-block; width: 40px; height: 40px; border: 4px solid #eee; border-top-color: #f0a020; border-radius: 50%; animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .step-pill { display: inline-block; background: #fff8ee; border: 1px solid #f0d99a; color: #8a6512; padding: 6px 14px; border-radius: 999px; font-size: 12px; margin-bottom: 12px; letter-spacing: 0.5px; }
    .summary { display: inline-block; text-align: left; margin-top: 18px; background: #fafafa; border-radius: 8px; padding: 16px 24px; font-size: 14px; min-width: 320px; }
    .summary .row { display: flex; justify-content: space-between; gap: 30px; padding: 3px 0; }
    .total-line { margin-top: 8px; padding-top: 8px; border-top: 1px solid #ddd; font-weight: 600; }
    .recipient { font-size: 12.5px; color: #666; margin-top: 12px; }
    button { margin-top: 16px; padding: 10px 20px; background: #f0a020; color: #fff; border: 0; border-radius: 6px; cursor: pointer; font-size: 14px; }
  </style>
</head>
<body>
  <div class="spinner"></div>
  <div class="step-pill">PAYMENT <?= $thisStep ?> OF <?= $totalP ?></div>
  <h2>Paying <?= htmlspecialchars($shopName) ?></h2>
  <p>Order #<?= $orderId ?> &middot; <?= htmlspecialchars($paypal['currency']) ?> <?= htmlspecialchars($amount) ?></p>

  <div class="summary">
    <?php foreach ($items as $i): ?>
      <div class="row">
        <span><?= htmlspecialchars($i['NAME']) ?> &times; <?= (int)$i['QUANTITY'] ?></span>
        <span><?= htmlspecialchars($paypal['currency']) ?> <?= number_format((float)$i['SUBTOTAL'], 2) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="row total-line">
      <span>To <?= htmlspecialchars($shopName) ?></span>
      <span><?= htmlspecialchars($paypal['currency']) ?> <?= htmlspecialchars($amount) ?></span>
    </div>
  </div>
  <div class="recipient">Funds will be sent directly to <strong><?= htmlspecialchars($sellerEm) ?></strong></div>

  <form id="paypal-form" action="<?= htmlspecialchars($paypal['sandbox_url']) ?>" method="post">
    <?php foreach ($fields as $name => $value): ?>
      <input type="hidden" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($value) ?>">
    <?php endforeach; ?>
    <noscript>
      <p>JavaScript is disabled. Click below to continue to PayPal:</p>
      <button type="submit">Continue to PayPal</button>
    </noscript>
  </form>

  <script>
    document.getElementById('paypal-form').submit();
  </script>
</body>
</html>
