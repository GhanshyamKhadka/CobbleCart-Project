<?php
// Session-based authentication helpers.

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    return [
        'user_id'   => (int)$_SESSION['user_id'],
        'role'      => $_SESSION['role'] ?? null,
        'name'      => $_SESSION['name'] ?? null,
        'email'     => $_SESSION['email'] ?? null,
        'shop_id'   => isset($_SESSION['shop_id']) ? (int)$_SESSION['shop_id'] : null,
    ];
}

function current_user_id(): ?int   { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }
function current_user_role(): ?string { return isset($_SESSION['role']) ? strtolower($_SESSION['role']) : null; }
function current_shop_id(): ?int   { return isset($_SESSION['shop_id']) ? (int)$_SESSION['shop_id'] : null; }

function login_session(array $user, array $extra = []): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['USER_ID'];
    $_SESSION['role']    = strtolower($user['ROLE']);
    $_SESSION['name']    = trim(($user['FIRST_NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
    $_SESSION['email']   = $user['EMAIL'];
    foreach ($extra as $k => $v) {
        $_SESSION[$k] = $v;
    }
}

function logout_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function password_hash_strong(string $plain): string
{
    $cfg = $GLOBALS['app_config'];
    return password_hash($plain, $cfg['password_algo'], ['cost' => $cfg['password_cost']]);
}
