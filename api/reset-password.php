<?php
require 'config.php';
$data = input_json();
$email = trim($data['email'] ?? '');
$newPassword = $data['new_password'] ?? $data['password'] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($newPassword) < 8) {
    json_response(['success' => false, 'message' => 'Valid email and new password of at least 8 characters are required'], 400);
}

try {
    oci_execute_stmt('UPDATE USERS SET PASSWORD = :password WHERE EMAIL = :email', [
        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        'email' => $email
    ]);
    json_response(['success' => true, 'message' => 'Password reset successfully']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Password reset failed: ' . $e->getMessage()], 500);
}
?>