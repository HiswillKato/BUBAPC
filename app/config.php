<?php
/**
 * BUBAPC - Main configuration file
 * Busitema University Born Again Pentecostal Church
 *
 * This is the only file you normally need to edit after installing the
 * website. It holds the database details, the website address, the contact
 * details, the social links and the database connection.
 *
 * The web installer (installer.php) is also allowed to write to this file.
 */

/* =========================================================================
   1. DATABASE CONNECTION DETAILS
   ========================================================================= */
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'bubapc');

/* =========================================================================
   2. WEBSITE IDENTITY
   ========================================================================= */
define('SITE_NAME', 'BUBAPC');
define('SITE_FULL_NAME', 'Busitema University Born Again Pentecostal Church');
define('SITE_TAGLINE', 'A Voice to Generations');

/* =========================================================================
   3. WEBSITE ADDRESS
   ========================================================================= */
// Leave BASE_URL as '' and the website works out its own address from the
// request. That is what you want on this machine, where the site is reached
// as http://bubapc/ — every link, redirect and email link then points back
// to the same name the visitor typed.
// On the live website set the real address, for example:
//     define('BASE_URL', 'https://bubapc.int.yt');
define('BASE_URL', '');

// When BASE_URL is empty the website trusts the Host header the browser sent,
// which is how it works under any name without editing any config. That is a
// tiny trust issue: the address written into links and emails is the visitor's
// own word. BUBAPC_HOSTS pins which names may drive that, so a stray Host
// header cannot make the website hand out addresses for a foreign domain.
// Leave it empty to allow any name (ideal on this machine, where it answers
// as http://bubapc/), fill it here on the live website:
//     define('BUBAPC_HOSTS', ['bubapc.int.yt', 'www.bubapc.int.yt']);
define('BUBAPC_HOSTS', []);

// Only needed when the project sits in a sub folder and not in the root of
// the domain, for example '/bubapc'. Leave as '' for the normal case.
define('BASE_PATH', '');

/* =========================================================================
   4. MAIL (SENDING EMAILS)
   ========================================================================= */
// These are used by send_mail() in app/mail.php, which is the ONE way this
// website sends an email. Three things use it today: the password reset
// pages, the contact form (a copy of every message goes to the mailbox
// below), and the newsletter.
//
// Mail is sent through Postfix on this machine, which delivers to local
// mailboxes. Empty MAIL_USER means no SMTP authentication is needed, which
// is the right setup for localhost. On the live website put the provider's
// server and account here instead.
// The FROM address is never taken from a form. Both addresses below must be
// ones Postfix accepts on this machine, which means they end in the local
// mail domain (mail.local here). An address in a domain that does not resolve
// is accepted by the mail server and then bounces, which looks like a working
// send and is not one.
define('MAIL_FROM', 'noreply@mail.local');
define('MAIL_FROM_NAME', SITE_NAME);

// Where contact messages go. The admin mailbox on this machine is
// admin@mail.local, which is what dovecot reads.
define('MAIL_ADMIN', 'admin@mail.local');
define('MAIL_HOST', '127.0.0.1');
define('MAIL_PORT', 25);
define('MAIL_USER', '');
define('MAIL_PASS', '');

// How long a password reset link stays valid. Reset links are one-time
// anyway (see token_row() in app/auth.php), so this is only the window in
// which the link may be used at all.
define('TOKEN_RESET_HOURS', 2);

// How many days a visitor row is kept before visitor_sweep() deletes it.
// A year is long enough to see that people come back, and short enough that
// the table never becomes the biggest thing in the database.
define('VISITOR_KEEP_DAYS', 365);

// Whether anything is written to the logs table. Turning this to false makes
// log_action() a no-op, which is the one-line way to stop logging on a site
// where nobody wants it. The logs screen hides itself too.
define('BUBAPC_LOGS', true);

/* =========================================================================
   4. CONTACT DETAILS
   ========================================================================= */
define('SITE_EMAIL', 'admin@bubapc.local');
define('SITE_PHONE', '+256 700 000 000');
define('SITE_LOCATION', 'Busitema, Uganda');

/* =========================================================================
   5. SOCIAL MEDIA LINKS
   ========================================================================= */
// These links live in this file on purpose. There is no social media table
// in the database. A platform with an empty URL is simply not shown.
// The church is on YouTube only for now, so Facebook is left empty. Put the
// real channel address here when it is known, and it appears on its own.
define('FACEBOOK_URL', '');
define('YOUTUBE_URL', 'https://youtube.com/BUBAPCMinistries');
define('INSTAGRAM_URL', '');
define('TIKTOK_URL', '');
define('WHATSAPP_URL', '');

/* =========================================================================
   6. UPLOAD SETTINGS
   ========================================================================= */
// The largest image we accept, in bytes (5 MB).
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);

// The folder inside the project where uploaded images are stored.
//
// It sits inside assets/ so that everything the browser can download lives in
// one place, and so that assets/.htaccess blocks script execution on anything
// a person uploads without needing a second copy of those rules.
//
// What is saved in the database is only the part AFTER this folder, for
// example "gallery/photo.jpg". Changing this folder therefore does not need
// any change to the rows already in the database, because they never mention
// it. Moving a live site's folder is a different job: the images have to be
// copied across, or the old folder left in place.
define('UPLOAD_FOLDER', 'assets/uploads');

/* =========================================================================
   7. SECURITY SETTINGS
   ========================================================================= */
// Set to true while testing so PHP shows errors on the screen.
// Turn it to false on the live website.
define('SHOW_ERRORS', true);

// How many wrong passwords are allowed before the account is locked,
// and for how many minutes the lock lasts.
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 15);

// The shortest password allowed for a website account.
define('MIN_PASSWORD_LENGTH', 8);

/* =========================================================================
   8. SEND EXTRA SECURITY HEADERS
   ========================================================================= */
// These tell the browser how it is allowed to use our pages. They are sent
// on every page, which protects the site from clickjacking and content
// sniffing attacks.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('X-Permitted-Cross-Domain-Policies: none');

    // Only tell the browser to force HTTPS when we are really on HTTPS.
    $on_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if ($on_https) {
        // HSTS is emitted by Apache in bubapc.conf (*:443), not here, for the
        // same duplicate-header reason described in the Gospelzora config.
    }
}

/* =========================================================================
   9. HARDEN THE PHP SESSION
   ========================================================================= */
// These must be set BEFORE session_start(), which happens in app/auth.php.
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

/* =========================================================================
   10. THE DATABASE CONNECTION
   ========================================================================= */
// Every page includes this file, so one connection object is created and
// shared by the whole website.
//
// We use MySQLi in its object style, and never PDO:
//     $conn = new mysqli(...);
//     $statement = $conn->prepare($sql);
//     $statement->bind_param('i', $id);
//
// The web installer defines BUBAPC_INSTALLER before it includes this file,
// so the installer can read the settings above even when the database
// has not been created yet.
if (!defined('BUBAPC_INSTALLER')) {
    // Since PHP 8.1 mysqli reports a failed connection by THROWING, not by
    // setting connect_errno. Without this try/catch the exception escapes
    // as a fatal error and the visitor gets a stack trace instead of the
    // friendly page below, so the catch is what makes that page reachable.
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    } catch (mysqli_sql_exception $problem) {
        // The message is deliberately not printed: it can name the database
        // user, and this page is shown to anyone who asked for a missing URL.
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
        echo '<code>app/config.php</code> and make sure the database has been imported from ';
        echo '<code>database.sql</code>.</p>';
        echo '<p>If the website has not been installed yet, please open ';
        echo '<a href="' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        echo '/installer.php">installer.php</a> first.</p>';
        echo '</div></body></html>';
        exit;
    }

    $conn->set_charset('utf8mb4');
}
