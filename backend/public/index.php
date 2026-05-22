<?php
// Front controller. All HTTP requests to /backend/* land here and are routed
// to the appropriate controller. Mount this directory as the document root, or
// add a .htaccess / nginx rewrite that maps /backend/* -> /backend/public/index.php.

require dirname(__DIR__) . '/core/bootstrap.php';
require dirname(__DIR__) . '/middleware/auth.php';

spl_autoload_register(function (string $class): void {
    $base = dirname(__DIR__);
    $candidates = [
        "$base/models/$class.php",
        "$base/controllers/$class.php",
        "$base/controllers/public/$class.php",
        "$base/controllers/customer/$class.php",
        "$base/controllers/trader/$class.php",
        "$base/controllers/admin/$class.php",
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            require $path;
            return;
        }
    }
});

$routes = require dirname(__DIR__) . '/routes/web.php';
$method = strtoupper($_SERVER['REQUEST_METHOD']);

// Strip the script prefix and trailing slashes from the request path.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = preg_replace('#^/backend(?:/public)?#', '', $path) ?: '/';
$path = '/' . trim($path, '/');

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($routeMethod !== $method) continue;

    $regex = '#^' . preg_replace('#\\\\{id\\\\}#', '(\d+)', preg_quote($pattern, '#')) . '$#';
    if (preg_match($regex, $path, $matches)) {
        array_shift($matches);
        [$controllerClass, $action] = $handler;
        try {
            $args = array_map('intval', $matches);
            call_user_func_array([$controllerClass, $action], $args);
        } catch (Throwable $e) {
            error_log('[backend] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            respond_error('Server error: ' . $e->getMessage(), 500);
        }
        exit;
    }
}

respond_error('Route not found: ' . $method . ' ' . $path, 404);
