<?php
/**
 * BUBAPC - Sessions, CSRF protection and website user login
 *
 * IMPORTANT: website users are NOT the same thing as church leaders.
 * A Pastor is a church leader stored in the people table.
 * An "admin" or "editor" here is only a person who manages this website.
 * A "member" is a church member who signed themselves up on register.php:
 * they may sign in and edit their own details, but they may not touch
 * anything else, and the admin panel is closed to them.
 */

/* Start the session on every page. The session settings were already made
   safe in app/config.php. */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================================
   1. LOGIN STATE
   ========================================================================= */

/**
 * Is somebody logged into the website admin area?
 */
function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

/**
 * The details of the logged in website user.
 * Returns an empty array when nobody is logged in.
 */
function current_user()
{
    global $conn;

    if (!is_logged_in()) {
        return [];
    }

    // Read the user once per request and keep the result for this page.
    if (!empty($_SESSION['user_data'])) {
        return $_SESSION['user_data'];
    }

    $id = (int) $_SESSION['user_id'];
    $statement = $conn->prepare("SELECT id, username, full_name, email, phone, role, can_manage_users,
                                        status, login, created
                                 FROM users WHERE id = ? LIMIT 1");
    $statement->bind_param('i', $id);
    $statement->execute();
    $result = $statement->get_result();
    $user = $result->fetch_assoc();
    $statement->close();

    // The user was deleted or deactivated while logged in, so log them out.
    if (!$user || (int) $user['status'] !== 1) {
        logout_user();
        return [];
    }

    $_SESSION['user_data'] = $user;
    return $user;
}

/**
 * The roles that may open the admin panel, in the order the users screen
 * shows them.
 *
 * 'church_admin' is somebody the church itself made an administrator - a
 * secretary, a treasurer, whoever keeps the records - rather than somebody who
 * was trained on a website. They are staff, so they may add and change all the
 * content. What they may not do, unless the one extra box on their account is
 * ticked, is open the users and the settings. That box is read by is_admin()
 * and by nothing else.
 *
 * This list lives here so that is_staff() keeps its promise of being the one
 * place that answers "who can get into the admin panel". A fourth role added
 * anywhere else in the project would be refused at the door, which is the
 * answer we want: the list is the door.
 */
function staff_roles()
{
    return ['admin', 'editor', 'church_admin'];
}

/**
 * May this person open the screens that change the website itself, meaning the
 * users and the settings?
 *
 * That is a website admin, and a church admin whose account has the extra box
 * ticked. An editor never can, however long they have been here.
 */
function is_admin()
{
    $user = current_user();
    if (!isset($user['role'])) {
        return false;
    }

    if ($user['role'] === 'admin') {
        return true;
    }

    /* The box only counts on a church admin. The form offers it for that one
       role and nothing else reads the column, so the two together mean that a
       member who somehow ended up with the box ticked is still only a member. */
    return $user['role'] === 'church_admin' && !empty($user['can_manage_users']);
}

/**
 * Is this person website staff, meaning somebody who may open the admin
 * panel? That is the three roles in staff_roles(), and nothing else.
 *
 * This is the one answer the admin panel depends on. require_staff() calls it,
 * and it is the only place that decides who is staff, so the answer to "who
 * can see the website admin panel" lives in a single function.
 */
function is_staff()
{
    $user = current_user();
    return isset($user['role']) && in_array($user['role'], staff_roles(), true);
}

/**
 * The address of the member area: the dashboard a church member signs in to.
 *
 * No trailing slash, because the rewrite rule claims /dashboard and hands it
 * straight to dashboard/index.php. Apache still answers a bare /dashboard with
 * one 301 to /dashboard/ first - it does that for any real directory before
 * .htaccess is consulted - so signing in costs one extra round trip. It is left
 * that way on purpose: the alternative is a trailing slash on every address,
 * which reads worse in the bar and in emails and saves nothing, since the
 * redirect happens after the sign-in POST has already been saved.
 */
function member_url()
{
    return url('dashboard');
}

/**
 * The account link in the navigation, as all three things it needs at once:
 * where it points, what it says, and which icon stands for it.
 *
 * There are three kinds of visitor, and the three answers follow the same
 * split for all three of them - so they are answered together here rather than
 * in three functions that each repeat the same question. Split across three
 * functions it was possible for a future change to move the link to the admin
 * panel and forget the icon, and the menu would then offer a person a door it
 * had decided to lock.
 *
 * Nobody signed in gets the login page, which is the one page both kinds of
 * person is welcome on. A member is sent to their own account page and staff
 * to the admin panel, so nobody is ever handed a link to a page they are not
 * allowed to open.
 *
 * The shield icon means "this opens the website admin panel", which is why
 * staff get it and members get a plain person.
 */
function account_link()
{
    if (!is_logged_in()) {
        return [
            'url'   => url('login'),
            'label' => 'Login',
            'icon'  => 'fa-right-to-bracket',
        ];
    }

    if (is_staff()) {
        return [
            'url'   => admin_url(),
            'label' => 'Admin',
            'icon'  => 'fa-shield-halved',
        ];
    }

    return [
        'url'   => member_url(),
        'label' => 'My account',
        'icon'  => 'fa-user',
    ];
}

/**
 * Send a visitor who is not logged in to the login page.
 */
function require_login()
{
    if (is_logged_in()) {
        return;
    }
    $_SESSION['login_redirect'] = current_page_path();
    flash('info', 'Please log in to continue.');
    redirect('login');
}

/**
 * Only a website admin may open this page.
 */
function require_admin()
{
    require_login();
    if (!is_admin()) {
        flash('error', 'Only somebody with website administrator access can open that page.');
        redirect('admin');
    }
}

/**
 * Only website staff may open this page, and only a website admin may open
 * the users and settings screens.
 *
 * Every page inside the admin panel calls this, because a church member is a
 * real account that can sign in and must never be able to reach /dashboard/admin/ by
 * typing the address or by following a bookmark. A member who lands here is
 * sent to their own account page with an explanation rather than shown an
 * error page, because getting turned away is normal for them, not a fault.
 *
 * Note the order: require_login() runs first, so somebody who is not signed
 * in at all is asked to sign in rather than being told they are not staff.
 */
function require_staff()
{
    require_login();
    if (!is_staff()) {
        flash('error', 'The admin panel is for website staff, so that page is not for you.');
        redirect(member_url());
    }
}

/**
 * Sign somebody in, having already checked their password.
 *
 * Both login.php and register.php go through here rather than writing the
 * session out themselves, because the two lines below are the security of the
 * whole login: a fresh session id stops a session id that was guessed before
 * signing in from being used afterwards, and clearing anything already in the
 * session stops a page that was open before signing in from carrying over.
 *
 * $user is a row from the users table, not a list of typed-in values.
 */
function sign_user_in($user)
{
    global $conn;

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_data'] = $user;
    $_SESSION['logged_in_at'] = time();

    $statement = $conn->prepare("UPDATE users SET login = NOW() WHERE id = ?");
    $user_id = (int) $user['id'];
    $statement->bind_param('i', $user_id);
    $statement->execute();
    $statement->close();

    /* The session is written first so that log_action() reads this person as
       the account that signed in, rather than as the person who was signed in
       before. The line is written here rather than in login.php because a
       member registering and signing in arrives the same way. */
    log_action($conn, 'user_signed_in', $user['full_name'] . ' signed in');
}

/**
 * Log the user out and destroy the session completely.
 */
function logout_user()
{
    /* Written before the session is emptied, or there would be nobody left to
       name. current_user() is safe to ask here: it is the same question the
       page asked a moment ago. */
    global $conn;

    $user = current_user();
    if ($user) {
        log_action($conn, 'user_signed_out', $user['full_name'] . ' signed out');
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
    }

    session_destroy();
}

/* =========================================================================
   2. LOGIN ATTEMPTS (LOCKING A NAME AFTER TOO MANY MISTAKES)
   ========================================================================= */

/**
 * How many minutes are left before this name or IP can try again.
 * Returns 0 when the account is not locked.
 */
function login_lockout_minutes($conn, $username)
{
    $minutes_ago = date('Y-m-d H:i:s', time() - (LOGIN_LOCK_MINUTES * 60));

    $statement = $conn->prepare("SELECT COUNT(*) AS wrong_count
                                 FROM login_attempts
                                 WHERE success = 0
                                   AND attempted > ?
                                   AND (username = ? OR ip = ?)");
    $now = $minutes_ago;
    $name = (string) $username;
    $ip = ip();
    $statement->bind_param('sss', $now, $name, $ip);
    $statement->execute();
    $result = $statement->get_result();
    $row = $result->fetch_assoc();
    $statement->close();

    if ((int) $row['wrong_count'] < LOGIN_MAX_ATTEMPTS) {
        return 0;
    }
    return LOGIN_LOCK_MINUTES;
}

/**
 * Remember one login try, good or bad.
 */
function login_record_attempt($conn, $username, $success)
{
    $now = date('Y-m-d H:i:s');
    $name = substr((string) $username, 0, 191);
    $ip = ip();
    $flag = $success ? 1 : 0;

    $statement = $conn->prepare("INSERT INTO login_attempts (username, ip, success, attempted)
                                 VALUES (?, ?, ?, ?)");
    $statement->bind_param('ssis', $name, $ip, $flag, $now);
    $statement->execute();
    $statement->close();

    /* Only the failures are written to the activity log. A successful sign in
       is already recorded by sign_user_in(), which knows the real name, so
       logging both would double-count every honest sign-in on the screen. */
    if (!$success) {
        log_action($conn, 'login_refused', 'Wrong password for "' . $name . '"');
    }
}

/**
 * Clear the wrong tries for a name after a correct login.
 */
function login_clear_attempts($conn, $username)
{
    $name = (string) $username;
    $statement = $conn->prepare("DELETE FROM login_attempts WHERE username = ?");
    $statement->bind_param('s', $name);
    $statement->execute();
    $statement->close();
}

/* =========================================================================
   3. CSRF PROTECTION
   ========================================================================= */

/**
 * A ready made hidden input to place inside every form that changes data.
 *
 * The token is made here rather than in a helper of its own, because the token
 * and the box that carries it are the same thing: nothing in the project ever
 * needs the bare token, and every form needs the box. Keeping them apart would
 * only invite a form that forgets one half of it.
 */
function csrf_field()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

/**
 * Check the token that came back with a POST request.
 * If it does not match, the request is stopped.
 */
function verify_csrf()
{
    $token_from_form = $_POST['csrf_token'] ?? '';
    $token_from_session = $_SESSION['csrf_token'] ?? '';

    if ($token_from_session === '' || !hash_equals($token_from_session, $token_from_form)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Request blocked | BUBAPC</title></head><body>';
        echo '<h1>403 - Request blocked</h1>';
        echo '<p>This form has expired. Please go back, reload the page and try again.</p>';
        echo '<p><a href="' . url('') . '">Go to the homepage</a></p>';
        echo '</body></html>';
        exit;
    }
}

/* =========================================================================
   4. SMALL FORM HELPERS
   ========================================================================= */

/**
 * Read a text field from $_POST and remove any extra spaces.
 * Anything printed later still goes through e().
 */
function post_text($field_name)
{
    return isset($_POST[$field_name]) ? trim((string) $_POST[$field_name]) : '';
}

/**
 * Read a checkbox. Returns 1 when it is ticked and 0 when it is not.
 */
function post_checkbox($field_name)
{
    return isset($_POST[$field_name]) ? 1 : 0;
}

/**
 * Is a value empty? Used when checking a form before saving.
 */
function is_empty_value($value)
{
    return trim((string) $value) === '';
}

/**
 * Is this a real looking email address?
 */
function is_valid_email($email)
{
    return filter_var((string) $email, FILTER_VALIDATE_EMAIL) !== false;
}

/* =========================================================================
   5. MAINTENANCE MODE GATE
   ========================================================================= */
/* When the admin turns maintenance mode on, visitors see a "back soon"
   page while website admins can still work. This runs on every public page
   because every page includes app/auth.php. */

if (!defined('BUBAPC_INSTALLER') && !defined('BUBAPC_SKIP_MAINTENANCE')) {
    $maintenance_on = false;

    // A table check keeps a half installed website working.
    $tables_result = $conn->query("SHOW TABLES LIKE 'settings'");
    if ($tables_result && $tables_result->num_rows > 0) {
        $maintenance_on = is_maintenance_mode($conn);
    }

    if ($maintenance_on && !is_admin()) {
        $maintenance_message = setting_get($conn, 'maintenance_message', '');
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

/* =========================================================================
   6. ONE-TIME TOKENS (PASSWORD RESETS)
   ========================================================================= */
/* A token is a long random string; only its SHA-256 hash is ever stored, in
   the tokens table, so a copy of the database is useless to somebody trying
   to guess a live link. The raw string is shown in exactly one email and
   never kept afterwards. */

/**
 * Make a fresh token for a user and store only its hash.
 *
 * Returns the raw random string, which is what goes in the email link, or
 * '' if the row could not be saved. The hash is stored with the expiry from
 * TOKEN_RESET_HOURS; a token that sits around unused past that becomes a row
 * that can never match again, which is harmless - tokens is safe to empty.
 */
function token_issue($conn, $user_id)
{
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);

    /* The expiry is worked out by the DATABASE, not by PHP, on purpose.
       token_row() compares it with the database's own NOW(), and on this
       machine those two clocks are three hours apart: PHP runs in UTC while
       MariaDB answers in local time. An expiry calculated in PHP would
       therefore be written as a time already in the past, and every link
       would read as expired the moment it arrived. Both ends of the
       comparison have to come from the same clock. */
    $statement = $conn->prepare(
        "INSERT INTO tokens (user, type, hash, expires)
         VALUES (?, 'password_reset', ?, DATE_ADD(NOW(), INTERVAL ? HOUR))"
    );
    $user_id = (int) $user_id;
    $hours = (int) TOKEN_RESET_HOURS;
    $statement->bind_param('ssi', $user_id, $hash, $hours);
    if (!$statement->execute()) {
        $statement->close();
        return '';
    }
    $statement->close();
    return $raw;
}

/**
 * Find the tokens row behind a raw token.
 *
 * The incoming string is hashed and matched, so the hash column is never
 * compared against a value that could have been built by hand. A row that
 * is already used, or whose expiry is in the past, is refused here so that
 * NOBODY (page or helper) can spend the same token twice.
 *
 * Returns the tokens row (with user, id, ...) or null. The caller decides
 * what an invalid token means; this function only decides validity.
 */
function token_row($conn, $raw_token)
{
    $hash = hash('sha256', (string) $raw_token);

    $statement = $conn->prepare(
        "SELECT * FROM tokens WHERE hash = ? AND used IS NULL AND expires > NOW() LIMIT 1"
    );
    $statement->bind_param('s', $hash);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return $row ?: null;
}

/**
 * Mark a token as spent, so it can never be used again.
 */
function token_use($conn, $token_id)
{
    $statement = $conn->prepare("UPDATE tokens SET used = NOW() WHERE id = ? AND used IS NULL");
    $token_id = (int) $token_id;
    $statement->bind_param('i', $token_id);
    $statement->execute();
    $statement->close();
}
