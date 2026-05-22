<?php
require 'config.php';
$data = input_json();
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    json_response(['success' => false, 'message' => 'Enter a valid email and password'], 400);
}

try {
    $stmt = oci_execute_stmt('SELECT USER_ID, FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, ROLE, PROFILE_IMAGE FROM USERS WHERE LOWER(EMAIL) = LOWER(:email)', ['email' => $email]);
    $user = oci_fetch_assoc_one($stmt);

    if (!$user) {
        json_response(['success' => false, 'message' => 'Incorrect email or password'], 401);
    }

    // Password check. New accounts store a bcrypt hash; the seed SQL inserts
    // plain text ("pass"). Accept both, and transparently upgrade any legacy
    // plain-text password to bcrypt on the first successful login — so
    // re-running CobbleCart Tables.sql never locks anyone out.
    $stored      = (string)$user['PASSWORD'];
    $isBcrypt    = (bool)preg_match('/^\$2[aby]\$/', $stored);
    $passwordOk  = $isBcrypt
        ? password_verify($password, $stored)
        : hash_equals($stored, $password);   // legacy plain-text compare

    if (!$passwordOk) {
        json_response(['success' => false, 'message' => 'Incorrect email or password'], 401);
    }

    // Auto-upgrade a legacy plain-text password to a bcrypt hash
    if (!$isBcrypt) {
        try {
            oci_execute_stmt(
                'UPDATE USERS SET PASSWORD = :pw WHERE USER_ID = :p_user_id',
                ['pw' => password_hash($password, PASSWORD_DEFAULT), 'p_user_id' => (int)$user['USER_ID']]
            );
        } catch (Throwable $ignore) {
            // Non-fatal: login still succeeds even if the upgrade write fails.
        }
    }

    session_regenerate_id(true);
    $role = strtolower($user['ROLE']);
    $_SESSION['user_id'] = (int)$user['USER_ID'];
    $_SESSION['role'] = $role;
    $_SESSION['name'] = trim($user['FIRST_NAME'] . ' ' . $user['LAST_NAME']);
    $_SESSION['email'] = $user['EMAIL'];
    $_SESSION['profile_image'] = $user['PROFILE_IMAGE'] ?? null;

    $extra = [];
    if ($role === 'trader') {
        $shopStmt = oci_execute_stmt('SELECT SHOP_ID, SHOP_NAME, SHOP_TYPE, STATUS FROM SHOP WHERE USER_ID = :user_id', ['user_id' => $_SESSION['user_id']]);
        $extra = oci_fetch_assoc_one($shopStmt) ?: [];
        $_SESSION['shop_id'] = isset($extra['SHOP_ID']) ? (int)$extra['SHOP_ID'] : null;
        $_SESSION['shop_name'] = $extra['SHOP_NAME'] ?? null;
        $_SESSION['shop_type'] = $extra['SHOP_TYPE'] ?? null;
        $_SESSION['shop_status'] = $extra['STATUS'] ?? null;
    }

    json_response([
        'success' => true,
        'message' => 'Login successful',
        'user_id' => $_SESSION['user_id'],
        'name' => $_SESSION['name'],
        'email' => $user['EMAIL'],
        'role' => $role,
        'profile_image' => $_SESSION['profile_image'] ?? null,
        'extra' => $extra
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Login failed: ' . $e->getMessage()], 500);
}
?>