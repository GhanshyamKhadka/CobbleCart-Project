<?php
// Review model. Covers A4-01, A4-02, B4-05, B8-01.

class Review
{
    public static function create(int $userId, int $productId, int $rating, string $comments): int
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Rating must be between 1 and 5');
        }
        $reviewId = db_nextval('seq_review');
        db_execute(
            'INSERT INTO REVIEW (REVIEW_ID, USER_ID, PRODUCT_ID, RATING, COMMENTS)
             VALUES (:rid, :uid, :pid, :rt, :cm)',
            [
                'rid' => $reviewId,
                'uid' => $userId,
                'pid' => $productId,
                'rt'  => $rating,
                'cm'  => mb_substr($comments, 0, 255),
            ]
        );
        return $reviewId;
    }

    public static function listForProduct(int $productId): array
    {
        return db_fetch_all(db_execute(
            'SELECT r.REVIEW_ID, r.RATING, r.COMMENTS,
                    u.FIRST_NAME, u.LAST_NAME
             FROM REVIEW r
             JOIN USERS u ON r.USER_ID = u.USER_ID
             WHERE r.PRODUCT_ID = :pid
             ORDER BY r.REVIEW_ID DESC',
            ['pid' => $productId]
        ));
    }

    public static function averageRating(int $productId): ?float
    {
        $row = db_fetch_one(db_execute(
            'SELECT AVG(RATING) AS AVG_RATING, COUNT(*) AS RATING_COUNT
             FROM REVIEW WHERE PRODUCT_ID = :pid',
            ['pid' => $productId]
        ));
        return $row && $row['AVG_RATING'] !== null
            ? round((float)$row['AVG_RATING'], 2)
            : null;
    }
}
