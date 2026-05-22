<?php
require 'config.php';
require_login();
$user = [
    'user_id' => current_user_id(),
    'name' => $_SESSION['name'] ?? null,
    'email' => $_SESSION['email'] ?? null,
    'role' => current_user_role(),
    'profile_image' => $_SESSION['profile_image'] ?? null,
];
if (current_user_role() === 'trader') {
    $user['shop'] = [
        'shop_id' => current_shop_id(),
        'shop_name' => $_SESSION['shop_name'] ?? null,
        'shop_type' => $_SESSION['shop_type'] ?? null,
        'status' => $_SESSION['shop_status'] ?? null,
    ];
}
json_response(['success' => true, 'user' => $user]);
?>