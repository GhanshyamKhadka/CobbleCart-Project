<?php
// User model — wraps the USERS table plus its role-specific child rows
// (CUSTOMER / TRADER / ADMIN). Covers requirements B1, B2, B3, C1, C2, D1.

class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = db_execute(
            'SELECT USER_ID, FIRST_NAME, LAST_NAME, EMAIL, USERNAME, PASSWORD, ROLE
             FROM USERS WHERE EMAIL = :email',
            ['email' => $email]
        );
        return db_fetch_one($stmt);
    }

    public static function findById(int $userId): ?array
    {
        $stmt = db_execute(
            'SELECT USER_ID, FIRST_NAME, LAST_NAME, EMAIL, USERNAME, ROLE, CREATED_DATE
             FROM USERS WHERE USER_ID = :id',
            ['id' => $userId]
        );
        return db_fetch_one($stmt);
    }

    // B1-01, B1-02: customer registration.
    public static function createCustomer(array $data): int
    {
        $userId = db_nextval('seq_user');
        $hash   = password_hash_strong($data['password']);
        db_execute(
            'INSERT INTO USERS (USER_ID, FIRST_NAME, LAST_NAME, EMAIL, USERNAME, PASSWORD, ROLE)
             VALUES (:uid, :fn, :ln, :em, :un, :pw, :role)',
            [
                'uid'  => $userId,
                'fn'   => $data['first_name'],
                'ln'   => $data['last_name'] ?? '',
                'em'   => $data['email'],
                'un'   => $data['username'] ?? $data['email'],
                'pw'   => $hash,
                'role' => 'CUSTOMER',
            ]
        );
        db_execute('INSERT INTO CUSTOMER (USER_ID) VALUES (:uid)', ['uid' => $userId]);
        return $userId;
    }

    // C1-01, C1-02, C1-05: trader registration (created PENDING until admin approves).
    public static function createTrader(array $data): int
    {
        $userId = db_nextval('seq_user');
        $hash   = password_hash_strong($data['password']);
        db_execute(
            'INSERT INTO USERS (USER_ID, FIRST_NAME, LAST_NAME, EMAIL, USERNAME, PASSWORD, ROLE)
             VALUES (:uid, :fn, :ln, :em, :un, :pw, :role)',
            [
                'uid'  => $userId,
                'fn'   => $data['first_name'],
                'ln'   => $data['last_name'] ?? '',
                'em'   => $data['email'],
                'un'   => $data['username'] ?? $data['email'],
                'pw'   => $hash,
                'role' => 'TRADER',
            ]
        );
        db_execute(
            'INSERT INTO TRADER (USER_ID, BUSINESS_NAME) VALUES (:uid, :bn)',
            ['uid' => $userId, 'bn' => $data['business_name'] ?? '']
        );
        return $userId;
    }

    // B3-02, C2-01: profile update.
    public static function updateProfile(int $userId, array $data): void
    {
        db_execute(
            'UPDATE USERS SET FIRST_NAME = :fn, LAST_NAME = :ln, EMAIL = :em
             WHERE USER_ID = :uid',
            [
                'fn'  => $data['first_name'],
                'ln'  => $data['last_name'] ?? '',
                'em'  => $data['email'],
                'uid' => $userId,
            ]
        );
    }

    // B3-03: change password.
    public static function changePassword(int $userId, string $newPassword): void
    {
        $hash = password_hash_strong($newPassword);
        db_execute(
            'UPDATE USERS SET PASSWORD = :pw WHERE USER_ID = :uid',
            ['pw' => $hash, 'uid' => $userId]
        );
    }

    // D2-01: admin listing of all users.
    public static function listAll(?string $role = null): array
    {
        $sql = 'SELECT USER_ID, FIRST_NAME, LAST_NAME, EMAIL, ROLE, CREATED_DATE FROM USERS';
        $params = [];
        if ($role) {
            $sql .= ' WHERE UPPER(ROLE) = UPPER(:role)';
            $params['role'] = $role;
        }
        $sql .= ' ORDER BY USER_ID DESC';
        return db_fetch_all(db_execute($sql, $params));
    }
}
