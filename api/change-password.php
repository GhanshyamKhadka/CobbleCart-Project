<?php
require 'config.php';
$data = input_json();
$userId = (int)($data['user_id'] ?? current_user_id() ?? 0);
$current = $data['current_password'] ?? '';
$new = $data['new_password'] ?? '';
if ($userId <= 0 || strlen($new) < 8) json_response(['success' => false, 'message' => 'New password must be at least 8 characters'], 400);

try {
    $stmt = oci_execute_stmt('SELECT PASSWORD FROM USERS WHERE USER_ID = :user_id', ['user_id' => $userId]);
    $user = oci_fetch_assoc_one($stmt);
    if (!$user || ($current !== '' && !password_verify($current, $user['PASSWORD']))) {
        json_response(['success' => false, 'message' => 'Current password is incorrect'], 401);
    }

    oci_execute_stmt('UPDATE USERS SET PASSWORD = :password WHERE USER_ID = :user_id', [
        'password' => password_hash($new, PASSWORD_DEFAULT),
        'user_id' => $userId
    ]);
    json_response(['success' => true, 'message' => 'Password changed successfully']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Password change failed: ' . $e->getMessage()], 500);
}
?>