<?php
// Product model. Covers requirements A1-*, A2-*, B4-*, C3-*.

class Product
{
    // A2-01, A2-02, A2-03, B4-01: browse / search / filter.
    public static function search(array $filters, ?string $viewerRole, ?int $viewerShopId): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (!empty($filters['shop_id'])) {
            $where[] = 'p.SHOP_ID = :shop_id';
            $params['shop_id'] = (int)$filters['shop_id'];
        }
        if (!empty($filters['product_type_id'])) {
            $where[] = 'p.PRODUCT_TYPE_ID = :ptid';
            $params['ptid'] = (int)$filters['product_type_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(UPPER(p.NAME) LIKE UPPER(:q) OR UPPER(s.SHOP_NAME) LIKE UPPER(:q))';
            $params['q'] = '%' . $filters['search'] . '%';
        }
        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $where[] = 'p.PRICE >= :min_price';
            $params['min_price'] = (float)$filters['min_price'];
        }
        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $where[] = 'p.PRICE <= :max_price';
            $params['max_price'] = (float)$filters['max_price'];
        }

        // Visibility rules:
        // - admin viewing "pending" sees pending products
        // - trader sees their own pending + approved
        // - everyone else sees only approved
        if ($viewerRole === 'admin' && !empty($filters['pending'])) {
            $where[] = "p.APPROVAL_STATUS = 'PENDING'";
        } elseif ($viewerRole === 'trader' && !empty($filters['shop_id']) && $viewerShopId === (int)$filters['shop_id']) {
            $where[] = "p.APPROVAL_STATUS IN ('APPROVED', 'PENDING')";
        } else {
            $where[] = "p.APPROVAL_STATUS = 'APPROVED'";
        }

        $sql = 'SELECT p.PRODUCT_ID, p.SHOP_ID, p.PRODUCT_TYPE_ID, p.NAME, p.DESCRIPTION,
                       p.PRICE, NVL(p.OFFER_PERCENT, 0) AS OFFER_PERCENT,
                       CASE
                           WHEN NVL(p.OFFER_PERCENT, 0) > 0 THEN ROUND(p.PRICE * (100 - p.OFFER_PERCENT) / 100, 2)
                           ELSE p.PRICE
                       END AS OFFER_PRICE,
                       p.QUANTITY_PER_ITEM, p.STOCK_QUANTITY, p.MIN_ORDER, p.MAX_ORDER,
                       p.ALLERGY_INFO, p.PRODUCT_IMAGE, p.APPROVAL_STATUS,
                       s.SHOP_NAME, s.SHOP_TYPE, pt.TYPE_NAME
                FROM PRODUCT p
                JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
                LEFT JOIN PRODUCT_TYPE pt ON p.PRODUCT_TYPE_ID = pt.PRODUCT_TYPE_ID
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY p.PRODUCT_ID DESC';

        return db_fetch_all(db_execute($sql, $params));
    }

    // B4-02: detailed product view.
    public static function findById(int $productId): ?array
    {
        $sql = 'SELECT p.*, s.SHOP_NAME, s.SHOP_TYPE, pt.TYPE_NAME
                FROM PRODUCT p
                JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
                LEFT JOIN PRODUCT_TYPE pt ON p.PRODUCT_TYPE_ID = pt.PRODUCT_TYPE_ID
                WHERE p.PRODUCT_ID = :pid';
        return db_fetch_one(db_execute($sql, ['pid' => $productId]));
    }

    // C3-01: trader creates a product. Inserted as PENDING for admin approval (C1-05).
    public static function create(int $shopId, array $data): int
    {
        $productId = db_nextval('seq_product');
        db_execute(
            'INSERT INTO PRODUCT (PRODUCT_ID, SHOP_ID, PRODUCT_TYPE_ID, NAME, DESCRIPTION,
                                  PRICE, OFFER_PERCENT, QUANTITY_PER_ITEM, STOCK_QUANTITY, MIN_ORDER, MAX_ORDER,
                                  ALLERGY_INFO, PRODUCT_IMAGE, APPROVAL_STATUS)
             VALUES (:pid, :sid, :ptid, :nm, :ds, :pr, :ofr, :qpi, :sq, :mn, :mx, :al, :im, :st)',
            [
                'pid'  => $productId,
                'sid'  => $shopId,
                'ptid' => $data['product_type_id'] ?? null,
                'nm'   => $data['name'],
                'ds'   => $data['description'] ?? '',
                'pr'   => (float)($data['price'] ?? 0),
                'ofr'  => (float)($data['offer_percent'] ?? 0),
                'qpi'  => $data['quantity_per_item'] ?? null,
                'sq'   => (int)($data['stock_quantity'] ?? 0),
                'mn'   => (int)($data['min_order'] ?? 1),
                'mx'   => (int)($data['max_order'] ?? 0),
                'al'   => $data['allergy_info'] ?? null,
                'im'   => $data['product_image'] ?? null,
                'st'   => 'PENDING',
            ]
        );
        return $productId;
    }

    // C3-01: trader updates own product.
    public static function update(int $productId, int $shopId, array $data): bool
    {
        $stmt = db_execute(
            'UPDATE PRODUCT
             SET NAME = :nm, DESCRIPTION = :ds, PRICE = :pr, OFFER_PERCENT = :ofr,
                 STOCK_QUANTITY = :sq, PRODUCT_IMAGE = :im, PRODUCT_TYPE_ID = :ptid
             WHERE PRODUCT_ID = :pid AND SHOP_ID = :sid',
            [
                'nm'   => $data['name'],
                'ds'   => $data['description'] ?? '',
                'pr'   => (float)$data['price'],
                'ofr'  => (float)($data['offer_percent'] ?? 0),
                'sq'   => (int)$data['stock_quantity'],
                'im'   => $data['product_image'] ?? null,
                'ptid' => $data['product_type_id'] ?? null,
                'pid'  => $productId,
                'sid'  => $shopId,
            ]
        );
        return oci_num_rows($stmt) > 0;
    }

    // C3-01: trader deletes own product.
    public static function delete(int $productId, int $shopId): bool
    {
        $stmt = db_execute(
            'DELETE FROM PRODUCT WHERE PRODUCT_ID = :pid AND SHOP_ID = :sid',
            ['pid' => $productId, 'sid' => $shopId]
        );
        return oci_num_rows($stmt) > 0;
    }

    // Admin: approve a pending product.
    public static function setApprovalStatus(int $productId, string $status): void
    {
        db_execute(
            'UPDATE PRODUCT SET APPROVAL_STATUS = :st WHERE PRODUCT_ID = :pid',
            ['st' => $status, 'pid' => $productId]
        );
    }

    // C4-03: trader's remaining stock view.
    public static function listForShop(int $shopId): array
    {
        return db_fetch_all(db_execute(
            'SELECT PRODUCT_ID, NAME, PRICE, NVL(OFFER_PERCENT, 0) AS OFFER_PERCENT,
                    CASE
                        WHEN NVL(OFFER_PERCENT, 0) > 0 THEN ROUND(PRICE * (100 - OFFER_PERCENT) / 100, 2)
                        ELSE PRICE
                    END AS OFFER_PRICE,
                    STOCK_QUANTITY, APPROVAL_STATUS
             FROM PRODUCT WHERE SHOP_ID = :sid ORDER BY PRODUCT_ID DESC',
            ['sid' => $shopId]
        ));
    }
}
