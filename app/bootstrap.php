<?php
/**
 * BUBAPC - Bootstrap
 *
 * Loads config, initializes database, starts session, sets timezone.
 * Must be included first by every entry point (except installer).
 */

if (defined('BUBAPC_BOOTSTRAPPED')) {
    return;
}
define('BUBAPC_BOOTSTRAPPED', true);

// 1. Load configuration (defines all constants)
require_once __DIR__ . '/config.php';

// 2. Timezone
date_default_timezone_set('Africa/Kampala');

// 3. Session - must start before any output
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', '7200');
    session_start();
}

// 4. Security headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('X-Permitted-Cross-Domain-Policies: none');

    if ($secure) {
        // HSTS handled by Apache vhost on production
    }
}

// 5. Error reporting
if (SHOW_ERRORS) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 6. Maintenance mode gate (runs after bootstrap so admin can bypass)
if (!defined('BUBAPC_INSTALLER') && !defined('BUBAPC_SKIP_MAINTENANCE')) {
    if (is_maintenance_mode() && !is_admin()) {
        $maintenance_message = setting_get('maintenance_message', '');
        if (trim($maintenance_message) === '') {
            $maintenance_message = 'We are carrying out some work on the website. Please check back shortly.';
        }
        http_response_code(503);
        $page_title = 'Website under maintenance';
        $body_class = 'page-maintenance';
        require __DIR__ . '/includes/maintenance.php';
        exit;
    }
}