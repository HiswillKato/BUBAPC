<?php
declare(strict_types=1);
use Martazi\core\Router;
require_once dirname(__DIR__) . '/bootstrap/app.php';

$router = new Router();

// Public pages (and slug detail pages)
require_once APP_ROOT . '/routes/web.php';
// Dashboard + admin
require_once APP_ROOT . '/routes/admin.php';
// API (currently empty)
if (is_file(APP_ROOT . '/routes/api.php')) {
    require_once APP_ROOT . '/routes/api.php';
}

$router->setNotFound(function () {
    global $conn; require APP_ROOT . '/resources/views/public/404.php';
});

$router->dispatch();
