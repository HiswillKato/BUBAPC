<?php
// Dashboard (member area) + admin panel migrated to the martazi router.

$dashDir = APP_ROOT . '/dashboard';
$adminDir = APP_ROOT . '/resources/views/dashboard/admin';

$registerPage = function (string $path, string $file) use ($router) {
    $handler = function () use ($file) {
        global $conn; require $file;
    };
    $router->get($path, $handler);
    $router->post($path, $handler);
};

// /dashboard and named pages
$registerPage('/dashboard', APP_ROOT . '/resources/views/dashboard/index.php');
$registerPage('/dashboard/profile', APP_ROOT . '/resources/views/dashboard/profile.php');
$registerPage('/dashboard/person', APP_ROOT . '/resources/views/dashboard/person.php');

foreach (glob($dashDir . '/*.php') as $file) {
    $name = basename($file, '.php');
    if (in_array($name, ['index', 'profile', 'person'], true)) {
        continue;
    }
    $registerPage('/dashboard/' . $name, $file);
}

// /dashboard/admin and named pages
$registerPage('/dashboard/admin', APP_ROOT . '/resources/views/dashboard/admin/index.php');
$registerPage('/dashboard/admin/index', APP_ROOT . '/resources/views/dashboard/admin/index.php');
$registerPage('/dashboard/index', APP_ROOT . '/resources/views/dashboard/index.php');

foreach (glob($adminDir . '/*.php') as $file) {
    $name = basename($file, '.php');
    if ($name === 'index') {
        continue;
    }
    $registerPage('/dashboard/admin/' . $name, $file);
}
