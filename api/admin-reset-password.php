<?php
// Admin-only: set a new password for any user. Returns the new password in
// plain text so the admin can communicate it to the user; the DB only ever
// stores the bcrypt hash.
require 'config.php';
require_role('admin');

$data = input_json();
$targetUserId = (int)($data['user_id'] ?? 0);
$newPassword  = isset($data['new_password']) ? (string)$data['new_password'] : '';

if ($targetUserId <= 0) {
    json_response(['success' => false, 'message' => 'user_id is required'], 400);
}

// If admin didn't supply a password, generate a random readable one.
if ($newPassword === '') {
    $alphabet  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $generated = '';
    for ($i = 0; $i < 10; $i++) {
        $generated .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $newPassword = $generated;
}
if (strlen($newPassword) < 8) {
    json_response(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
}

try {
    $stmt = oci_execute_stmt(
        'SELECT user_id, first_name, last_name, email FROM USERS WHERE user_id = :p_user_id',
        ['p_user_id' => $targetUserId]
    );
    $target = oci_fetch_assoc_one($stmt);
    if (!$target) {
        json_response(['success' => false, 'message' => 'No such user'], 404);
    }

    oci_execute_stmt(
        'UPDATE USERS SET password = :hashed WHERE user_id = :p_user_id',
        [
            'hashed'    => password_hash($newPassword, PASSWORD_DEFAULT),
            'p_user_id' => $targetUserId,
        ]
    );

    json_response([
        'success'      => true,
        'message'      => 'Password reset for ' . trim(($target['FIRST_NAME'] ?? '') . ' ' . ($target['LAST_NAME'] ?? '')),
        'user_id'      => (int)$target['USER_ID'],
        'email'        => $target['EMAIL'],
        'new_password' => $newPassword,
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
