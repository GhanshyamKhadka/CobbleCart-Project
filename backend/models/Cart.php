<?php
// Cart (Basket) model. Covers A3-01, A3-04, B5-01..B5-04.

class Cart
{
    // Ensures a basket row exists for the customer, then returns its id.
    public static function ensureBasketForUser(int $userId): int
    {
        $existing = db_fetch_one(db_execute(
            'SELECT BASKET_ID FROM BASKET WHERE USER_ID = :uid',
            ['uid' => $userId]
        ));
        if ($existing) {
            return (int)$existing['BASKET_ID'];
        }
        // schema/oracle-complete-schema.sql does not declare a sequence/trigger
        // for BASKET. We allocate the next id with NVL(MAX,0)+1 inside the same
        // INSERT and then fetch it back. Safe enough for the scaffold; for
        // higher concurrency add `CREATE SEQUENCE seq_basket` and use it here.
        db_execute(
            'INSERT INTO BASKET (BASKET_ID, USER_ID)
             SELECT NVL(MAX(BASKET_ID), 0) + 1, :uid FROM BASKET',
            ['uid' => $userId]
        );
        $row = db_fetch_one(db_execute(
            'SELECT BASKET_ID FROM BASKET WHERE USER_ID = :uid',
            ['uid' => $userId]
        ));
        return (int)$row['BASKET_ID'];
    }

    // B5-01: add item to cart (increments quantity if already present).
    public static function addItem(int $userId, int $productId, int $quantity): void
    {
        $basketId = self::ensureBasketForUser($userId);
        $existing = db_fetch_one(db_execute(
            'SELECT BASKET_ITEM_ID, QUANTITY FROM BASKET_ITEM
             WHERE BASKET_ID = :bid AND PRODUCT_ID = :pid',
            ['bid' => $basketId, 'pid' => $productId]
        ));
        if ($existing) {
            db_execute(
                'UPDATE BASKET_ITEM SET QUANTITY = QUANTITY + :q WHERE BASKET_ITEM_ID = :bid',
                ['q' => $quantity, 'bid' => $existing['BASKET_ITEM_ID']]
            );
        } else {
            db_execute(
                'INSERT INTO BASKET_ITEM (BASKET_ITEM_ID, BASKET_ID, PRODUCT_ID, QUANTITY)
                 VALUES (seq_basket_item.NEXTVAL, :bid, :pid, :q)',
                ['bid' => $basketId, 'pid' => $productId, 'q' => $quantity]
            );
        }
    }

    // B5-02: set explicit quantity.
    public static function updateQuantity(int $userId, int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            self::removeItem($userId, $productId);
            return;
        }
        $basketId = self::ensureBasketForUser($userId);
        db_execute(
            'UPDATE BASKET_ITEM SET QUANTITY = :q
             WHERE BASKET_ID = :bid AND PRODUCT_ID = :pid',
            ['q' => $quantity, 'bid' => $basketId, 'pid' => $productId]
        );
    }

    // B5-03, A3-04: remove an item from the cart.
    public static function removeItem(int $userId, int $productId): void
    {
        $basketId = self::ensureBasketForUser($userId);
        db_execute(
            'DELETE FROM BASKET_ITEM WHERE BASKET_ID = :bid AND PRODUCT_ID = :pid',
            ['bid' => $basketId, 'pid' => $productId]
        );
    }

    public static function clear(int $userId): void
    {
        $basketId = self::ensureBasketForUser($userId);
        db_execute('DELETE FROM BASKET_ITEM WHERE BASKET_ID = :bid', ['bid' => $basketId]);
    }

    // B5-04: line items + total cost.
    public static function getCart(int $userId): array
    {
        $basketId = self::ensureBasketForUser($userId);
        $items = db_fetch_all(db_execute(
            'SELECT bi.BASKET_ITEM_ID, bi.PRODUCT_ID, bi.QUANTITY,
                    p.NAME, p.PRICE, p.PRODUCT_IMAGE, p.STOCK_QUANTITY,
                    s.SHOP_NAME,
                    (bi.QUANTITY * p.PRICE) AS LINE_TOTAL
             FROM BASKET_ITEM bi
             JOIN PRODUCT p ON bi.PRODUCT_ID = p.PRODUCT_ID
             JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
             WHERE bi.BASKET_ID = :bid',
            ['bid' => $basketId]
        ));
        $total = 0.0;
        foreach ($items as $row) {
            $total += (float)$row['LINE_TOTAL'];
        }
        return ['items' => $items, 'total' => round($total, 2), 'basket_id' => $basketId];
    }
}
