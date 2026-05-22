<?php
// Request parsing & validation helpers.

function input_data(): array
{
    $raw  = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return array_merge($_POST, $_GET);
}

function input(string $key, $default = null)
{
    $data = input_data();
    return $data[$key] ?? $_GET[$key] ?? $default;
}

function require_fields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            respond_error("Field '$field' is required", 400);
        }
    }
}

function validate_email_or_fail(string $email): string
{
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond_error('A valid email is required', 400);
    }
    return $email;
}

// E8 - strong passwords. Minimum 8 chars, at least one letter and one digit.
function validate_password_or_fail(string $password): string
{
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        respond_error('Password must be at least 8 characters and contain letters and digits', 400);
    }
    return $password;
}

function method_is(string $method): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD']) === strtoupper($method);
}

function require_method(string $method): void
{
    if (!method_is($method)) {
        respond_error('Method not allowed', 405);
    }
}
