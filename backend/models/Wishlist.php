<?php
// Wishlist (Favourite) model. Covers A3-02, A3-04, B6-01.

class Wishlist
{
    public static function add(int $userId, int $productId): void
    {
        $existing = db_fetch_one(db_execute(
            'SELECT FAVOURITE_ID FROM FAVOURITE WHERE USER_ID = :uid AND PRODUCT_ID = :pid',
            ['uid' => $userId, 'pid' => $productId]
        ));
        if ($existing) {
            return;
        }
        db_execute(
            'INSERT INTO FAVOURITE (FAVOURITE_ID, USER_ID, PRODUCT_ID)
             VALUES (seq_favourite.NEXTVAL, :uid, :pid)',
            ['uid' => $userId, 'pid' => $productId]
        );
    }

    public static function remove(int $userId, int $productId): void
    {
        db_execute(
            'DELETE FROM FAVOURITE WHERE USER_ID = :uid AND PRODUCT_ID = :pid',
            ['uid' => $userId, 'pid' => $productId]
        );
    }

    public static function list(int $userId): array
    {
        return db_fetch_all(db_execute(
            'SELECT f.FAVOURITE_ID, f.PRODUCT_ID,
                    p.NAME, p.PRICE, p.PRODUCT_IMAGE, p.STOCK_QUANTITY, p.APPROVAL_STATUS,
                    s.SHOP_NAME
             FROM FAVOURITE f
             JOIN PRODUCT p ON f.PRODUCT_ID = p.PRODUCT_ID
             JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
             WHERE f.USER_ID = :uid
             ORDER BY f.FAVOURITE_ID DESC',
            ['uid' => $userId]
        ));
    }
}
