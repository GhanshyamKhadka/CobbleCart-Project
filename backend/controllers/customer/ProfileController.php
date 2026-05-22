<?php
// Customer profile management. Covers B3-01, B3-02.
// Routes:
//   GET  /backend/customer/profile
//   PUT  /backend/customer/profile

class CustomerProfileController
{
    public static function show(): void
    {
        require_customer();
        $user = User::findById(current_user_id());
        if (!$user) respond_not_found('User');
        respond_ok(['profile' => $user]);
    }

    public static function update(): void
    {
        require_customer();
        $data = input_data();
        require_fields($data, ['first_name', 'email']);
        $data['email'] = validate_email_or_fail($data['email']);
        User::updateProfile(current_user_id(), $data);
        respond_ok(null, 'Profile updated');
    }
}
