<?php
require 'config.php';

$data = input_json();
$email = strtolower(trim($data['email'] ?? ''));
$code = trim((string)($data['code'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($code) !== 6 || !ctype_digit($code)) {
    json_response(['success' => false, 'message' => 'Valid email and 6-digit code are required'], 400);
}

$pendingDir = __DIR__ . DIRECTORY_SEPARATOR . 'pending-verifications';
$pendingFile = $pendingDir . DIRECTORY_SEPARATOR . hash('sha256', $email) . '.json';

if (!is_file($pendingFile)) {
    json_response(['success' => false, 'message' => 'No pending verification for this email. Please register again.'], 400);
}

$stored = json_decode((string)file_get_contents($pendingFile), true);
if (!is_array($stored) || ($stored['expires_at'] ?? 0) < time()) {
    @unlink($pendingFile);
    json_response(['success' => false, 'message' => 'Verification code has expired. Please register again.'], 400);
}
if (!hash_equals((string)$stored['code'], $code)) {
    json_response(['success' => false, 'message' => 'Invalid verification code'], 400);
}

$userData = $stored['data'];

try {
    $userId = oracle_nextval('seq_user');
    oci_execute_stmt(
        'INSERT INTO USERS (USER_ID, FIRST_NAME, LAST_NAME, EMAIL, USERNAME, PASSWORD, ROLE, CREATED_DATE, EMAIL_VERIFIED, PROFILE_IMAGE)
         VALUES (:user_id, :first_name, :last_name, :email, :username, :password, :role, SYSDATE, :verified, :profile_image)',
        [
            'user_id'       => $userId,
            'first_name'    => $userData['first_name'],
            'last_name'     => $userData['last_name'],
            'email'         => $userData['email'],
            'username'      => $userData['username'],
            'password'      => $userData['password'],
            'role'          => $userData['role'],
            'verified'      => 'YES',
            'profile_image' => $userData['profile_image'] ?? null,
        ],
        false
    );

    if ($userData['role'] === 'CUSTOMER') {
        oci_execute_stmt(
            'INSERT INTO CUSTOMER (USER_ID, LOYALTY_POINTS) VALUES (:user_id, 0)',
            ['user_id' => $userId],
            false
        );
    } elseif ($userData['role'] === 'TRADER') {
        oci_execute_stmt(
            'INSERT INTO TRADER (USER_ID, BUSINESS_NAME) VALUES (:user_id, :business_name)',
            ['user_id' => $userId, 'business_name' => $userData['shop_name']],
            false
        );
        $shopId = oracle_nextval('seq_shop');
        oci_execute_stmt(
            'INSERT INTO SHOP (SHOP_ID, USER_ID, SHOP_NAME, SHOP_TYPE, STATUS)
             VALUES (:shop_id, :user_id, :shop_name, :shop_type, :status)',
            [
                'shop_id'   => $shopId,
                'user_id'   => $userId,
                'shop_name' => $userData['shop_name'],
                'shop_type' => $userData['shop_type'],
                'status'    => 'PENDING',
            ],
            false
        );
    } elseif ($userData['role'] === 'ADMIN') {
        oci_execute_stmt('INSERT INTO ADMIN (USER_ID) VALUES (:user_id)', ['user_id' => $userId], false);
    }

    db_commit();
    @unlink($pendingFile);
    unset($_SESSION['verification_email']);

    json_response([
        'success' => true,
        'message' => 'Account created successfully',
        'user_id' => $userId,
        'role'    => $userData['role'],
    ]);
} catch (Throwable $e) {
    db_rollback();
    json_response(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()], 500);
}
