<?php
// Admin-only — list contact-form messages, newest first.
require 'config.php';
require_role('admin');

try {
    $where  = '1 = 1';
    $params = [];
    $filter = strtoupper(trim($_GET['status'] ?? ''));
    if ($filter === 'NEW' || $filter === 'REPLIED') {
        $where = 'status = :status';
        $params['status'] = $filter;
    }

    $stmt = oci_execute_stmt(
        "SELECT message_id, first_name, last_name, email, order_ref, subject, body,
                status, admin_reply,
                TO_CHAR(created_at, 'YYYY-MM-DD HH24:MI') AS created_at,
                TO_CHAR(replied_at, 'YYYY-MM-DD HH24:MI') AS replied_at
         FROM CONTACT_MESSAGE
         WHERE $where
         ORDER BY message_id DESC",
        $params
    );
    $messages = oci_fetch_assoc_all($stmt);

    $countStmt = oci_execute_stmt(
        "SELECT
            COUNT(*) AS TOTAL,
            SUM(CASE WHEN status = 'NEW' THEN 1 ELSE 0 END) AS NEW_COUNT
         FROM CONTACT_MESSAGE",
        []
    );
    $counts = oci_fetch_assoc_one($countStmt) ?: ['TOTAL' => 0, 'NEW_COUNT' => 0];

    json_response(['success' => true, 'messages' => $messages, 'counts' => $counts]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
