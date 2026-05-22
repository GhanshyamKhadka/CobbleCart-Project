<?php
require 'config.php';
require_once 'send-email.php';

const VERIFICATION_TTL_SECONDS = 900;   // 15 minutes
const RESEND_COOLDOWN_SECONDS  = 60;    // throttle re-sends

$data = input_json();
$name = trim($data['name'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';
$role = in_array(strtolower($data['role'] ?? 'customer'), ['customer', 'trader', 'admin'], true)
    ? strtoupper($data['role'])
    : 'CUSTOMER';
$shopName = trim($data['shop_name'] ?? '');
$shopType = trim($data['shop_type'] ?? '');

$profileImage = save_uploaded_image('profile_photo', __DIR__ . '/../frontend/images/avatars');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    json_response(['success' => false, 'message' => 'Name, valid email and password of at least 8 characters are required'], 400);
}
if ($role === 'TRADER' && ($shopName === '' || $shopType === '')) {
    json_response(['success' => false, 'message' => 'Shop name and shop type are required for trader registration'], 400);
}

try {
    $stmt = oci_execute_stmt('SELECT USER_ID FROM USERS WHERE LOWER(EMAIL) = :email', ['email' => $email]);
    if (oci_fetch_assoc_one($stmt)) {
        json_response(['success' => false, 'message' => 'Email already registered'], 409);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}

[$firstName, $lastName] = split_full_name($name);
$pendingDir = __DIR__ . DIRECTORY_SEPARATOR . 'pending-verifications';
if (!is_dir($pendingDir)) {
    mkdir($pendingDir, 0775, true);
}
$pendingFile = $pendingDir . DIRECTORY_SEPARATOR . hash('sha256', $email) . '.json';

$existing = null;
if (is_file($pendingFile)) {
    $stored = json_decode((string)file_get_contents($pendingFile), true);
    if (is_array($stored) && ($stored['expires_at'] ?? 0) > time()) {
        $existing = $stored;
    }
}
if ($existing && ($existing['last_sent_at'] ?? 0) > time() - RESEND_COOLDOWN_SECONDS) {
    json_response([
        'success' => true,
        'message' => 'A verification code was just sent. Please check your inbox or wait a minute before requesting another.',
        'throttled' => true
    ]);
}

$code = random_int(100000, 999999);
$verification = [
    'code' => (string)$code,
    'expires_at' => time() + VERIFICATION_TTL_SECONDS,
    'last_sent_at' => time(),
    'data' => [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'username' => substr($email, 0, 50),
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'shop_name' => $shopName,
        'shop_type' => $shopType,
        'profile_image' => $profileImage,
    ],
];

if (file_put_contents($pendingFile, json_encode($verification), LOCK_EX) === false) {
    json_response(['success' => false, 'message' => 'Server could not store the pending verification'], 500);
}
$_SESSION['verification_email'] = $email;   // hint for verify.html

$subject = 'CobbleCart email verification';
$body = "Hi $firstName,\n\nYour CobbleCart verification code is: $code\n\nIt expires in 15 minutes. If you didn't request this, please ignore this email.\n\n— CobbleCart";

if (!sendEmail($email, $firstName, $subject, $body)) {
    json_response([
        'success' => false,
        'message' => 'We could not send the verification email. Check api/mail-config.local.php credentials, then try again.'
    ], 500);
}

json_response([
    'success' => true,
    'message' => 'Verification code sent. Please check your email and enter the 6-digit code.'
]);
