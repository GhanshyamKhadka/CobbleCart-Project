<?php
// Admin-only — save a reply to a contact message and email it to the customer.
require 'config.php';
require_once __DIR__ . '/send-email.php';
require_role('admin');

$data      = input_json();
$messageId = (int)($data['message_id'] ?? 0);
$reply     = trim($data['reply'] ?? '');

if ($messageId <= 0 || $reply === '') {
    json_response(['success' => false, 'message' => 'message_id and a reply are required'], 400);
}
if (mb_strlen($reply) > 2000) {
    json_response(['success' => false, 'message' => 'Reply is too long (max 2000 characters)'], 400);
}

try {
    $stmt = oci_execute_stmt(
        'SELECT message_id, first_name, email, subject FROM CONTACT_MESSAGE WHERE message_id = :mid',
        ['mid' => $messageId]
    );
    $msg = oci_fetch_assoc_one($stmt);
    if (!$msg) {
        json_response(['success' => false, 'message' => 'No such message'], 404);
    }

    oci_execute_stmt(
        "UPDATE CONTACT_MESSAGE
         SET admin_reply = :reply, status = 'REPLIED', replied_at = SYSDATE
         WHERE message_id = :mid",
        ['reply' => $reply, 'mid' => $messageId]
    );

    // Email the reply to the customer (best-effort — DB update already committed).
    $emailed = false;
    if (filter_var($msg['EMAIL'], FILTER_VALIDATE_EMAIL)) {
        $bodyLines = [
            'Hi ' . ($msg['FIRST_NAME'] ?: 'there') . ',',
            '',
            'Thanks for contacting CobbleCart. Here is our reply regarding "' . $msg['SUBJECT'] . '":',
            '',
            $reply,
            '',
            'If you need anything else, just reply to this email.',
            '',
            '— The CobbleCart team',
        ];
        $emailed = sendEmail($msg['EMAIL'], $msg['FIRST_NAME'] ?: 'Customer',
            'Re: ' . $msg['SUBJECT'] . ' — CobbleCart', implode("\n", $bodyLines));
    }

    json_response([
        'success' => true,
        'message' => $emailed
            ? 'Reply saved and emailed to the customer.'
            : 'Reply saved. (Email could not be sent — check api/mail-config.local.php.)',
        'emailed' => $emailed,
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
