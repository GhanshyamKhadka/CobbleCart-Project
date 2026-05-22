<?php
// Single entry-point bootstrap. Every controller / route file must require this first.

define('BACKEND_ROOT', dirname(__DIR__));

$appConfig = require BACKEND_ROOT . '/config/app.php';
$dbConfig  = require BACKEND_ROOT . '/config/database.php';

$GLOBALS['app_config'] = $appConfig;
$GLOBALS['db_config']  = $dbConfig;

// Session setup (E9 - secure storage of user data).
session_name($appConfig['session_name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Response headers (JSON API + CORS, E5 cross-browser support).
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: '  . $appConfig['cors']['allow_origin']);
header('Access-Control-Allow-Methods: ' . $appConfig['cors']['allow_methods']);
header('Access-Control-Allow-Headers: ' . $appConfig['cors']['allow_headers']);
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require BACKEND_ROOT . '/core/db.php';
require BACKEND_ROOT . '/core/response.php';
require BACKEND_ROOT . '/core/request.php';
require BACKEND_ROOT . '/core/auth.php';
