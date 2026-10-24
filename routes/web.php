<?php
// Public pages migrated to the martazi router.

$pagesDir = APP_ROOT . '/pages';

$registerPage = function (string $path, string $file) use ($router) {
    $handler = function () use ($file) {
        global $conn; require $file;
    };
    $router->get($path, $handler);
    $router->post($path, $handler);
};

$slugPages = ['devotionals', 'events', 'departments', 'families', 'people'];

// Slug pages: list + detail (detail sets $_GET['slug'])
foreach ($slugPages as $name) {
    $file = $pagesDir . '/' . $name . '.php';
    $registerPage('/' . $name, $file);

    $detailHandler = function (string $slug) use ($file) {
        $_GET['slug'] = $slug;
        global $conn; require $file;
    };
    $router->get('/' . $name . '/{slug}', $detailHandler);
    $router->post('/' . $name . '/{slug}', $detailHandler);
}

// Every other page
foreach (glob($pagesDir . '/*.php') as $file) {
    $name = basename($file, '.php');
    if ($name === 'index' || $name === '404' || in_array($name, $slugPages, true)) {
        continue;
    }
    $registerPage('/' . $name, $file);
}

// Home
$registerPage('/', APP_ROOT . '/pages/index.php');
$registerPage('/index', APP_ROOT . '/pages/index.php');
