<?php
require 'config.php';
require_role('admin');
$data = input_json();
require_fields($data, ['name', 'email', 'password', 'shop_name', 'shop_type']);
[$firstName, $lastName] = split_full_name(trim($data['name']));
$email = trim($data['email']);
$password = $data['password'];
$shopName = trim($data['shop_name']);
$shopType = trim($data['shop_type']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    json_response(['success' => false, 'message' => 'Valid email and password of at least 8 characters are required'], 400);
}

try {
    $stmt = oci_execute_stmt('SELECT USER_ID FROM USERS WHERE EMAIL = :email', ['email' => $email]);
    if (oci_fetch_assoc_one($stmt)) {
        json_response(['success' => false, 'message' => 'Email already registered'], 409);
    }

    $userId = oracle_nextval('seq_user');
    $shopId = oracle_nextval('seq_shop');

    oci_execute_stmt(
        'INSERT INTO USERS (USER_ID, FIRST_NAME, LAST_NAME, EMAIL, USERNAME, PASSWORD, ROLE, CREATED_DATE) VALUES (:user_id, :first_name, :last_name, :email, :username, :password, :role, SYSDATE)',
        [
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'username' => substr($email, 0, 50),
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'TRADER'
        ],
        false
    );

    oci_execute_stmt('INSERT INTO TRADER (USER_ID, BUSINESS_NAME) VALUES (:user_id, :business_name)', ['user_id' => $userId, 'business_name' => $shopName], false);
    oci_execute_stmt('INSERT INTO SHOP (SHOP_ID, USER_ID, SHOP_NAME, SHOP_TYPE, STATUS) VALUES (:shop_id, :user_id, :shop_name, :shop_type, :status)', [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'shop_name' => $shopName,
        'shop_type' => $shopType,
        'status' => 'ACTIVE'
    ], false);

    db_commit();
    json_response(['success' => true, 'message' => 'Trader added successfully', 'user_id' => $userId, 'shop_id' => $shopId]);
} catch (Throwable $e) {
    db_rollback();
    json_response(['success' => false, 'message' => 'Could not add trader: ' . $e->getMessage()], 500);
}
?>