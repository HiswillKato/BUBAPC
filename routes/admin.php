<?php
// Dashboard (member area) + admin panel migrated to the martazi router.

$dashDir = APP_ROOT . '/dashboard';
$adminDir = APP_ROOT . '/dashboard/admin';

$registerPage = function (string $path, string $file) use ($router) {
    $handler = function () use ($file) {
        global $conn; require $file;
    };
    $router->get($path, $handler);
    $router->post($path, $handler);
};

// /dashboard and named pages
$registerPage('/dashboard', APP_ROOT . '/dashboard/index.php');
$registerPage('/dashboard/profile', APP_ROOT . '/dashboard/profile.php');
$registerPage('/dashboard/person', APP_ROOT . '/dashboard/person.php');

foreach (glob($dashDir . '/*.php') as $file) {
    $name = basename($file, '.php');
    if (in_array($name, ['index', 'profile', 'person'], true)) {
        continue;
    }
    $registerPage('/dashboard/' . $name, $file);
}

// /dashboard/admin and named pages
$registerPage('/dashboard/admin', APP_ROOT . '/dashboard/admin/index.php');
$registerPage('/dashboard/admin/index', APP_ROOT . '/dashboard/admin/index.php');
$registerPage('/dashboard/index', APP_ROOT . '/dashboard/index.php');

foreach (glob($adminDir . '/*.php') as $file) {
    $name = basename($file, '.php');
    if ($name === 'index') {
        continue;
    }
    $registerPage('/dashboard/admin/' . $name, $file);
}
