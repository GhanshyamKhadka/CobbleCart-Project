<?php
// Shop model. Covers requirements A1-07, A2-04, C1, C2, D1-04.

class Shop
{
    public static function create(int $userId, array $data): int
    {
        $shopId = db_nextval('seq_shop');
        db_execute(
            'INSERT INTO SHOP (SHOP_ID, USER_ID, SHOP_NAME, SHOP_TYPE, STATUS)
             VALUES (:sid, :uid, :nm, :tp, :st)',
            [
                'sid' => $shopId,
                'uid' => $userId,
                'nm'  => $data['shop_name'],
                'tp'  => $data['shop_type'] ?? '',
                'st'  => 'PENDING',
            ]
        );
        return $shopId;
    }

    public static function findByUser(int $userId): ?array
    {
        return db_fetch_one(db_execute(
            'SELECT SHOP_ID, USER_ID, SHOP_NAME, SHOP_TYPE, STATUS
             FROM SHOP WHERE USER_ID = :uid',
            ['uid' => $userId]
        ));
    }

    public static function findById(int $shopId): ?array
    {
        return db_fetch_one(db_execute(
            'SELECT SHOP_ID, USER_ID, SHOP_NAME, SHOP_TYPE, STATUS
             FROM SHOP WHERE SHOP_ID = :sid',
            ['sid' => $shopId]
        ));
    }

    public static function listAll(?string $status = null): array
    {
        $sql = 'SELECT s.SHOP_ID, s.SHOP_NAME, s.SHOP_TYPE, s.STATUS,
                       u.USER_ID, u.FIRST_NAME, u.LAST_NAME, u.EMAIL
                FROM SHOP s JOIN USERS u ON s.USER_ID = u.USER_ID';
        $params = [];
        if ($status) {
            $sql .= ' WHERE s.STATUS = :st';
            $params['st'] = strtoupper($status);
        }
        $sql .= ' ORDER BY s.SHOP_ID DESC';
        return db_fetch_all(db_execute($sql, $params));
    }

    // C2-01: trader updates own shop.
    public static function update(int $shopId, array $data): void
    {
        db_execute(
            'UPDATE SHOP SET SHOP_NAME = :nm, SHOP_TYPE = :tp WHERE SHOP_ID = :sid',
            [
                'nm'  => $data['shop_name'],
                'tp'  => $data['shop_type'] ?? '',
                'sid' => $shopId,
            ]
        );
    }

    // C1-05, D1-03: admin sets shop status (APPROVED / SUSPENDED / PENDING).
    public static function setStatus(int $shopId, string $status): void
    {
        db_execute(
            'UPDATE SHOP SET STATUS = :st WHERE SHOP_ID = :sid',
            ['st' => strtoupper($status), 'sid' => $shopId]
        );
    }
}
