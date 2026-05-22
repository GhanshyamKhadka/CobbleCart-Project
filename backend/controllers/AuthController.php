<?php
// Shared authentication controller. Used by customer, trader, and admin flows.
// Routes (B1, B2, C1, D1):
//   POST /backend/auth/register-customer
//   POST /backend/auth/register-trader
//   POST /backend/auth/login
//   POST /backend/auth/logout
//   GET  /backend/auth/me

class AuthController
{
    // B1-01..B1-02: customer registration.
    public static function registerCustomer(): void
    {
        require_method('POST');
        $data = input_data();
        require_fields($data, ['first_name', 'email', 'password']);
        $data['email']    = validate_email_or_fail($data['email']);
        $data['password'] = validate_password_or_fail($data['password']);

        if (User::findByEmail($data['email'])) {
            respond_error('An account with this email already exists', 409);
        }
        $userId = User::createCustomer($data);
        respond_ok(['user_id' => $userId], 'Registration successful');
    }

    // C1-01, C1-02, C1-05: trader registration (requires shop details, pending admin approval).
    public static function registerTrader(): void
    {
        require_method('POST');
        $data = input_data();
        require_fields($data, ['first_name', 'email', 'password', 'shop_name']);
        $data['email']    = validate_email_or_fail($data['email']);
        $data['password'] = validate_password_or_fail($data['password']);

        if (User::findByEmail($data['email'])) {
            respond_error('An account with this email already exists', 409);
        }

        try {
            $userId = User::createTrader($data);
            $shopId = Shop::create($userId, [
                'shop_name' => $data['shop_name'],
                'shop_type' => $data['shop_type'] ?? '',
            ]);
            respond_ok(
                ['user_id' => $userId, 'shop_id' => $shopId, 'status' => 'PENDING'],
                'Trader account created — awaiting admin approval'
            );
        } catch (Throwable $e) {
            db_rollback();
            respond_error('Trader registration failed: ' . $e->getMessage(), 500);
        }
    }

    // B2-01..B2-02, C1-03..C1-04, D1-01: login. Single endpoint, role-aware response.
    public static function login(): void
    {
        require_method('POST');
        $data     = input_data();
        $email    = validate_email_or_fail($data['email'] ?? '');
        $password = $data['password'] ?? '';
        if ($password === '') {
            respond_error('Password is required', 400);
        }

        $user = User::findByEmail($email);
        if (!$user || !password_verify($password, $user['PASSWORD'])) {
            respond_error('Incorrect email or password', 401);
        }

        $extra = [];
        $role  = strtolower($user['ROLE']);
        if ($role === 'trader') {
            $shop = Shop::findByUser((int)$user['USER_ID']);
            if ($shop) {
                $extra['shop_id']     = (int)$shop['SHOP_ID'];
                $extra['shop_name']   = $shop['SHOP_NAME'];
                $extra['shop_type']   = $shop['SHOP_TYPE'];
                $extra['shop_status'] = $shop['STATUS'];
                // C1-05: traders whose shop hasn't been approved cannot log in to trade yet.
                if ($shop['STATUS'] !== 'APPROVED') {
                    respond_error('Your trader account is awaiting admin approval', 403);
                }
            }
        }

        login_session($user, $extra);
        respond_ok([
            'user_id' => (int)$user['USER_ID'],
            'name'    => trim(($user['FIRST_NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? '')),
            'email'   => $user['EMAIL'],
            'role'    => $role,
            'extra'   => $extra,
        ], 'Login successful');
    }

    public static function logout(): void
    {
        require_method('POST');
        logout_session();
        respond_ok(null, 'Logged out');
    }

    public static function me(): void
    {
        require_login();
        respond_ok(current_user());
    }

    // B3-03: change password for the logged-in user.
    public static function changePassword(): void
    {
        require_login();
        require_method('POST');
        $data = input_data();
        require_fields($data, ['current_password', 'new_password']);

        $userId  = current_user_id();
        $current = User::findByEmail($_SESSION['email']);
        if (!$current || !password_verify($data['current_password'], $current['PASSWORD'])) {
            respond_error('Current password is incorrect', 401);
        }
        validate_password_or_fail($data['new_password']);
        User::changePassword($userId, $data['new_password']);
        respond_ok(null, 'Password updated');
    }
}
