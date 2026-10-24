<?php
/**
 * BUBAPC - Main configuration file (martazi-style, .env backed)
 *
 * Values come from /.env via env(). Legacy constants are still defined below
 * so the existing codebase keeps working while call sites migrate to $config.
 */

// Timezone
date_default_timezone_set(env('APP_TIMEZONE', 'Africa/Kampala'));

/* =========================================================================
   CONFIG ARRAY (martazi-style)
   ========================================================================= */
$config = [
    'app' => [
        'name' => env('SITE_NAME', 'BUBAPC'),
        'full_name' => env('SITE_FULL_NAME', 'Busitema University Born Again Pentecostal Church'),
        'tagline' => env('SITE_TAGLINE', 'A Voice to Generations'),
        'url' => env('BASE_URL', ''),
        'debug' => filter_var(env('SHOW_ERRORS', false), FILTER_VALIDATE_BOOLEAN),
        'timezone' => env('APP_TIMEZONE', 'Africa/Kampala'),
    ],
    'site' => [
        'email' => env('SITE_EMAIL', ''),
        'phone' => env('SITE_PHONE', ''),
        'location' => env('SITE_LOCATION', ''),
    ],
    'database' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', 3306),
        'user' => env('DB_USER', 'root'),
        'password' => env('DB_PASS', ''),
        'name' => env('DB_NAME', 'bubapc'),
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'from' => env('MAIL_FROM', ''),
        'from_name' => env('MAIL_FROM_NAME', env('SITE_NAME', 'BUBAPC')),
        'admin' => env('MAIL_ADMIN', ''),
        'host' => env('MAIL_HOST', '127.0.0.1'),
        'port' => (int) env('MAIL_PORT', 25),
        'user' => env('MAIL_USER', ''),
        'password' => env('MAIL_PASS', ''),
    ],
    'social' => [
        'facebook' => env('FACEBOOK_URL', ''),
        'youtube' => env('YOUTUBE_URL', ''),
        'instagram' => env('INSTAGRAM_URL', ''),
        'tiktok' => env('TIKTOK_URL', ''),
        'whatsapp' => env('WHATSAPP_URL', ''),
    ],
    'upload' => [
        'max_size' => (int) env('MAX_UPLOAD_SIZE', 5242880),
        'folder' => env('UPLOAD_FOLDER', 'assets/uploads'),
    ],
    'security' => [
        'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'login_lock_minutes' => (int) env('LOGIN_LOCK_MINUTES', 15),
        'min_password_length' => (int) env('MIN_PASSWORD_LENGTH', 8),
        'token_reset_hours' => (int) env('TOKEN_RESET_HOURS', 2),
        'visitor_keep_days' => (int) env('VISITOR_KEEP_DAYS', 365),
        'logs' => filter_var(env('BUBAPC_LOGS', true), FILTER_VALIDATE_BOOLEAN),
    ],
    'hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('BUBAPC_HOSTS', ''))))),
];

/* =========================================================================
   LEGACY CONSTANTS (backward compat) — derived from .env
   ========================================================================= */
define('DB_HOST', $config['database']['host']);
define('DB_USER', $config['database']['user']);
define('DB_PASS', $config['database']['password']);
define('DB_NAME', $config['database']['name']);
define('DB_PORT', $config['database']['port']);

define('SITE_NAME', $config['app']['name']);
define('SITE_FULL_NAME', $config['app']['full_name']);
define('SITE_TAGLINE', $config['app']['tagline']);
define('SITE_EMAIL', $config['site']['email']);
define('SITE_PHONE', $config['site']['phone']);
define('SITE_LOCATION', $config['site']['location']);

define('BASE_URL', $config['app']['url']);
define('BASE_PATH', env('BASE_PATH', ''));
define('BUBAPC_HOSTS', $config['hosts'] ?: []);

define('MAIL_FROM', $config['mail']['from']);
define('MAIL_FROM_NAME', $config['mail']['from_name']);
define('MAIL_ADMIN', $config['mail']['admin']);
define('MAIL_HOST', $config['mail']['host']);
define('MAIL_PORT', $config['mail']['port']);
define('MAIL_USER', $config['mail']['user']);
define('MAIL_PASS', $config['mail']['password']);

define('TOKEN_RESET_HOURS', $config['security']['token_reset_hours']);
define('VISITOR_KEEP_DAYS', $config['security']['visitor_keep_days']);
define('BUBAPC_LOGS', $config['security']['logs']);

define('FACEBOOK_URL', $config['social']['facebook']);
define('YOUTUBE_URL', $config['social']['youtube']);
define('INSTAGRAM_URL', $config['social']['instagram']);
define('TIKTOK_URL', $config['social']['tiktok']);
define('WHATSAPP_URL', $config['social']['whatsapp']);

define('MAX_UPLOAD_SIZE', $config['upload']['max_size']);
define('UPLOAD_FOLDER', $config['upload']['folder']);

define('SHOW_ERRORS', $config['app']['debug']);
define('LOGIN_MAX_ATTEMPTS', $config['security']['login_max_attempts']);
define('LOGIN_LOCK_MINUTES', $config['security']['login_lock_minutes']);
define('MIN_PASSWORD_LENGTH', $config['security']['min_password_length']);

// Security headers block (kept from original)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('X-Permitted-Cross-Domain-Policies: none');
}

// Session ini settings (kept from original)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '7200');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
}

if (SHOW_ERRORS) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Database connection (kept from original)
if (!defined('BUBAPC_INSTALLER')) {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    } catch (mysqli_sql_exception $problem) {
        $conn = null;
    }

    if ($conn === null || $conn->connect_errno) {
        http_response_code(503);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Database problem | BUBAPC</title>';
        echo '<style>body{font-family:system-ui,sans-serif;background:#f5f6fb;color:#1b1f3b;';
        echo 'display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}';
        echo '.box{max-width:620px;background:#fff;border-radius:20px;padding:40px;';
        echo 'box-shadow:0 20px 50px rgba(27,31,59,.10)}h1{font-size:26px;margin:0 0 12px}';
        echo 'code{background:#f1f2f8;padding:2px 6px;border-radius:6px}';
        echo 'a{color:#4338ca}</style></head><body><div class="box">';
        echo '<h1>The database is not reachable</h1>';
        echo '<p>The website could not connect to the database. Please check the details in ';
        echo '<code>config/config.php</code> and make sure the database has been imported from ';
        echo '<code>database.sql</code>.</p>';
        echo '<p>If the website has not been installed yet, please open ';
        echo '<a href="' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        echo '/install.php">install.php</a> first.</p>';
        echo '</div></body></html>';
        exit;
    }

    $conn->set_charset('utf8mb4');
}

return $config;
