<?php
/**
 * BUBAPC - bootstrap (martazi-style)
 */

define('APP_ROOT', dirname(__DIR__));

// Composer autoload (also loads app/helpers/functions.php, defining env() etc.)
if (is_file(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}

// Load environment variables from .env
if (class_exists('Dotenv\\Dotenv')) {
    \Dotenv\Dotenv::createImmutable(APP_ROOT)->safeLoad();
}

// Load configuration (martazi-style array + legacy constants)
$config = require_once APP_ROOT . '/config/config.php';

// configure error reporting
error_reporting(E_ALL);
if (isset($config['app']['debug'])) {
    ini_set('display_errors', $config['app']['debug'] ? '1' : '0');
}

// Configure timezone
if (isset($config['app']['timezone'])) {
    date_default_timezone_set($config['app']['timezone']);
}

// Initialize sessions / load auth & mail
require_once APP_ROOT . '/app/helpers/auth.php';
require_once APP_ROOT . '/app/helpers/mail.php';
require_once APP_ROOT . '/bootstrap/session.php';

define('APP_INITIALIZED', true);

/* Failure handler (unchanged) */
set_exception_handler(function ($problem) {
    error_log(sprintf(
        '[bubapc] %s in %s on line %d',
        $problem->getMessage(),
        $problem->getFile(),
        $problem->getLine()
    ));

    if (defined('BUBAPC_INSTALLER')) {
        http_response_code(500);
        exit;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);

    $detail = (defined('SHOW_ERRORS') && SHOW_ERRORS)
        ? '<pre class="text-start bg-body-tertiary p-3 rounded mt-3" style="white-space:pre-wrap">'
            . e($problem->getMessage() . "\n\n" . $problem->getFile() . ' on line ' . $problem->getLine())
            . '</pre>'
        : '';

    echo '<!DOCTYPE html><html lang="en" data-theme="light"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Something went wrong | BUBAPC</title>'
        . '<link rel="stylesheet" href="' . asset('css/bootstrap.min.css') . '">'
        . '<link rel="stylesheet" href="' . asset('css/all.min.css') . '">'
        . '<link rel="stylesheet" href="' . asset('css/style.css') . '">'
        . '</head><body class="bg-body text-body"><main class="container py-5">'
        . '<div class="row justify-content-center"><div class="col-lg-7">'
        . '<h1 class="h3">Something went wrong</h1>'
        . '<p class="text-body-secondary">This page could not be finished. The problem has'
        . ' been written to the error log. Please try again in a moment, and tell the'
        . ' church if it keeps happening.</p>'
        . $detail
        . '<a class="btn btn-primary mt-2" href="' . url() . '">Back to the home page</a>'
        . '</div></div></main></body></html>';

    exit;
});
