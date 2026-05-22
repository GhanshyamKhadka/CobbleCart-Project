<?php
// Uniform JSON response helpers. Every controller exits through these.

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function respond_ok($data = null, string $message = 'OK'): void
{
    $body = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $body['data'] = $data;
    }
    respond($body, 200);
}

function respond_error(string $message, int $status = 400, array $extra = []): void
{
    respond(array_merge(['success' => false, 'message' => $message], $extra), $status);
}

function respond_not_found(string $resource = 'Resource'): void
{
    respond_error("$resource not found", 404);
}
