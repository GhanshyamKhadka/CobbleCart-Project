<?php
require 'config.php';
$data = input_json();
$userId = (int)($data['user_id'] ?? current_user_id() ?? 0);
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
if ($userId <= 0 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'User, name and valid email are required'], 400);
}
[$firstName, $lastName] = split_full_name($name);
$profileImage = save_uploaded_image('profile_photo', __DIR__ . '/../frontend/images/avatars');

try {
    $sets = ['FIRST_NAME = :first_name', 'LAST_NAME = :last_name', 'EMAIL = :email'];
    $params = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'user_id' => $userId,
    ];
    if ($profileImage !== null) {
        $sets[] = 'PROFILE_IMAGE = :profile_image';
        $params['profile_image'] = $profileImage;
    }

    oci_execute_stmt('UPDATE USERS SET ' . implode(', ', $sets) . ' WHERE USER_ID = :user_id', $params);

    if ($userId === current_user_id()) {
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        if ($profileImage !== null) {
            $_SESSION['profile_image'] = $profileImage;
        }
    }

    json_response(['success' => true, 'message' => 'Profile updated successfully', 'profile_image' => $profileImage]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Profile update failed: ' . $e->getMessage()], 500);
}
?>