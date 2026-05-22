<?php
// Public endpoint — store a contact-form submission for the admin inbox.
require 'config.php';

$data = input_json();
$firstName = trim($data['first_name'] ?? '');
$lastName  = trim($data['last_name']  ?? '');
$email     = trim($data['email']      ?? '');
$orderRef  = trim($data['order_ref']  ?? '');
$subject   = trim($data['subject']    ?? '');
$body      = trim($data['message']    ?? $data['body'] ?? '');

if ($firstName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $subject === '' || $body === '') {
    json_response(['success' => false, 'message' => 'First name, a valid email, subject and message are all required'], 400);
}
if (mb_strlen($body) > 2000) {
    json_response(['success' => false, 'message' => 'Message is too long (max 2000 characters)'], 400);
}

try {
    $messageId = oracle_nextval('seq_contact');
    oci_execute_stmt(
        "INSERT INTO CONTACT_MESSAGE
            (message_id, first_name, last_name, email, order_ref, subject, body, status, created_at)
         VALUES
            (:mid, :first_name, :last_name, :email, :order_ref, :subject, :body, 'NEW', SYSDATE)",
        [
            'mid'        => $messageId,
            'first_name' => substr($firstName, 0, 50),
            'last_name'  => substr($lastName, 0, 50),
            'email'      => substr($email, 0, 100),
            'order_ref'  => substr($orderRef, 0, 50),
            'subject'    => substr($subject, 0, 120),
            'body'       => $body,
        ]
    );
    json_response([
        'success'    => true,
        'message'    => 'Thanks — your message has been sent to the CobbleCart team.',
        'message_id' => $messageId,
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Could not send your message: ' . $e->getMessage()], 500);
}
