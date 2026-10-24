<?php
/**
 * BUBAPC - the sign-in session, CSRF protection and who may do what
 *
 * There is one table about people, and this file signs them in. A row in
 * `people` with a password is an account and a row without one is a plain
 * directory page, so the account and the page are the same thing and no query
 * has to join two tables to know who somebody is.
 *
 * role is the website job, not the church job: 'admin' may open the users and
 * the settings as well as the content, and 'editor', 'pastor' and 'leader' may
 * manage the content but never the settings. A 'member' is a church member who
 * signed themselves up on register.php: they may sign in and edit their own
 * details, but the admin panel is closed to them.
 *
 * A church position such as pastor or elder is the person's category, which
 * decides which page they appear on. One thing sits on top of the roles: a
 * person filed under "Church Admins" is an administrator through that group
 * alone, whatever the role says, because the group is the church's own senior
 * list. isAdmin() is the one place that reads it, so a church admin and a
 * website admin share one kind of power.
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
function isLoggedIn()
{
    return !empty($_SESSION['user_id']);
}

/**
 * The details of the logged in website user.
 * Returns an empty array when nobody is logged in.
 *
 * An account is a row in people with a password, so this is simply that row.
 * Two aliases: full_name is the person's name, and person_category is their group.
 */
function currentUser()
{
    global $conn;

    if (!isLoggedIn()) {
        return [];
    }

    // Read the person once per request and keep the result for this page.
    if (!empty($_SESSION['user_data'])) {
        return $_SESSION['user_data'];
    }

    $id = (int) $_SESSION['user_id'];
    $statement = $conn->prepare(
        "SELECT p.*, p.name AS full_name, p.category AS person_category, p.id AS person_id
         FROM people p
         WHERE p.id = ? AND p.password IS NOT NULL LIMIT 1"
    );
    $statement->bind_param('i', $id);
    $statement->execute();
    $person = $statement->get_result()->fetch_assoc();
    $statement->close();

    /* The account was deleted or switched off while the person was signed in,
       so they are signed out on the next thing they do. A switched off account
       is one with active at 0. */
    if (!$person || (int) $person['active'] !== 1) {
        logoutUser();
        return [];
    }

    $_SESSION['user_data'] = $person;
    return $person;
}

/**
 * The directory page behind this sign in, or an empty array.
 *
 * Now that a row is both the page and the sign-in, this is the signed in person
 * themselves - unless they are a 'member' account, which belongs
 * to no page. Those two have nothing on the public site to edit, so they are
 * handed back an empty array, exactly as a person with no page always was.
 */
function currentPerson($conn)
{
    $person = currentUser();

    if ($person === [] || in_array($person['category'], ['member'], true)) {
        return [];
    }

    return $person;
}

/**
 * Can this person sign in at all? True when the row carries a password, which
 * is what separates a sign-in from a plain directory page. Used by the lists to
 * put an "Account" badge on the right rows.
 */
function personCanSignIn($person)
{
    return !empty($person['password']);
}

/**
 * Who may look at the whole website while it is closed for maintenance.
 *
 * An administrator - the same answer as everywhere else in the panel, because
 * a website admin and a church admin both run the place. A church leader with
 * an ordinary account may not: the website is not theirs to close.
 */
function mayBypassMaintenance()
{
    return isAdmin();
}

/**
 * The roles that may open the admin panel, in the order the users screen
 * shows them.
 *
 * 'admin' is a website or a church administrator and reaches the users and
 * the settings screens as well as the content. The two kinds of admin share
 * this one role and have the same rights. 'editor' writes the content,
 * 'pastor' marks a religious leader and 'leader' any other church leader;
 * all three may add and change the content but never the users or the
 * settings.
 *
 * Roles that can access the admin panel.
 */
function staffRoles()
{
    return ['admin', 'church_admin', 'pastor', 'elder', 'minister', 'editor', 'leader'];
}

/**
 * Is the signed in account an administrator, in effect?
 *
 * Two things make one: the 'admin' role, for somebody who runs the website and
 * has no page of their own in the directory, and the "Church Admins" group,
 * which a church leader is already filed under. A church admin linked to such
 * a page is an administrator everywhere a website admin is - the users, the
 * settings and the whole panel - not only at the maintenance door. The group
 * is the church's own senior list, so reading it here is what folds the two
 * kinds of admin into one kind of power.
 */
function isAdmin()
{
    $user = currentUser();
    if (!isset($user['role'])) {
        return false;
    }

    return $user['role'] === 'admin';
}

/**
 * Is the signed in account linked to a directory person in the "Church Admins"
 * group? The group is the people category 'church_admin'. The group is read
 * from the cached user row, so this costs nothing after the first check on a
 * page.
 */
function isChurchAdmin()
{
    return (currentUser()['person_category'] ?? '') === 'church_admin';
}

/**
 * Is this person authorised to open the admin panel?
 */
function isLeader()
{
    $user = currentUser();
    if (!isset($user['role'])) {
        return false;
    }
    return $user['role'] === 'leader';
}

function isEditor()
{
    $user = currentUser();
    if (!isset($user['role'])) {
        return false;
    }
    return $user['role'] === 'editor';
}

function isPastor()
{
    $user = currentUser();
    if (!isset($user['role'])) {
        return false;
    }
    return $user['role'] === 'pastor';
}

function isElder()
{
    $user = currentUser();
    if (!isset($user['role'])) {
        return false;
    }
    return $user['role'] === 'elder';
}

function isMinister()
{
    $user = currentUser();
    if (!isset($user['role'])) {
        return false;
    }
    return $user['role'] === 'minister';
}

function isChurchAdminRole()
{
    $user = currentUser();
    if (!isset($user['role'])) {
        return false;
    }
    return $user['role'] === 'church_admin';
}

function hasFullAccess()
{
    $user = currentUser();
    if (!isset($user['role'])) {
        return false;
    }
    $role = $user['role'];
    return in_array($role, ['admin', 'church_admin', 'pastor', 'elder', 'minister'], true);
}

function canManageAllContent()
{
    $role = currentUser()['role'] ?? '';
    return hasFullAccess() || in_array($role, ['editor'], true);
}

function canManageUsers()
{
    $role = currentUser()['role'] ?? '';
    return $role === 'admin';
}

function canManageSettings()
{
    $role = currentUser()['role'] ?? '';
    return $role === 'admin';
}

function isStaff()
{
    $user = currentUser();
    if (!isset($user['role'])) {
        return false;
    }

    return in_array($user['role'], staffRoles(), true);
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
function memberUrl()
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
 * Nobody signed in gets the login page. A member is sent to their own account page
 * and those who may access the admin panel to the admin panel.
 */
function accountLink()
{
    if (!isLoggedIn()) {
        return [
            'url'   => url('login'),
            'label' => 'Login',
            'icon'  => 'fa-right-to-bracket',
        ];
    }

    if (isStaff()) {
        return [
            'url'   => adminUrl(),
            'label' => 'Admin',
            'icon'  => 'fa-shield-halved',
        ];
    }

    return [
        'url'   => memberUrl(),
        'label' => 'My account',
        'icon'  => 'fa-user',
    ];
}

/**
 * Send a visitor who is not logged in to the login page.
 */
function requireLogin()
{
    if (isLoggedIn()) {
        return;
    }
    $_SESSION['login_redirect'] = currentPagePath();
    flash('info', 'Please log in to continue.');
    redirect('login');
}

/**
 * Only an administrator - a website or a church admin - may open this page.
 */
function requireAdmin()
{
    requireLogin();
    if (!isAdmin()) {
        flash('error', 'Only somebody with administrator access can open that page.');
        redirect(adminUrl());
    }
}

/**
 * Only authorised members may open the admin panel.
 */
function requireStaff()
{
    requireLogin();
    if (!isStaff()) {
        flash('error', 'The admin panel is for authorised members only.');
        redirect(memberUrl());
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
 * $user is a row from the people table, not a list of typed-in values.
 */
function signUserIn($user)
{
    global $conn;

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    /* One row is what the session carries, and it is the row currentUser()
       builds, not the raw login row handed in here. That row is the one that
       knows the person's directory group, which isAdmin() and isStaff() read. */
    unset($_SESSION['user_data']);
    $_SESSION['logged_in_at'] = time();

    $statement = $conn->prepare("UPDATE people SET login = NOW() WHERE id = ?");
    $user_id = (int) $user['id'];
    $statement->bind_param('i', $user_id);
    $statement->execute();
    $statement->close();

    /* The session is written first so that logAction() reads this person as
       the account that signed in, rather than as the person who was signed in
       before. The line is written here rather than in login.php because a
       member registering and signing in arrives the same way. */
    logAction($conn, 'user_signed_in', ($user['full_name'] ?? $user['name']) . ' signed in');
}

/**
 * Log the user out and destroy the session completely.
 */
function logoutUser()
{
    /* Written before the session is emptied, or there would be nobody left to
       name. currentUser() is safe to ask here: it is the same question the
       page asked a moment ago. */
    global $conn;

    $user = currentUser();
    if ($user) {
        logAction($conn, 'user_signed_out', $user['full_name'] . ' signed out');
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
function loginLockoutMinutes($conn, $username)
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
function loginRecordAttempt($conn, $username, $success)
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
       is already recorded by signUserIn(), which knows the real name, so
       logging both would double-count every honest sign-in on the screen. */
    if (!$success) {
        logAction($conn, 'login_refused', 'Wrong password for "' . $name . '"');
    }
}

/**
 * Clear the wrong tries for a name after a correct login.
 */
function loginClearAttempts($conn, $username)
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
function csrfField()
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
function verifyCsrf()
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
function postText($field_name)
{
    return isset($_POST[$field_name]) ? trim((string) $_POST[$field_name]) : '';
}

/**
 * Read a checkbox. Returns 1 when it is ticked and 0 when it is not.
 */
function postCheckbox($field_name)
{
    return isset($_POST[$field_name]) ? 1 : 0;
}

/**
 * Is a value empty? Used when checking a form before saving.
 */
function isEmptyValue($value)
{
    return trim((string) $value) === '';
}

/**
 * Is this a real looking email address?
 */
function isValidEmail($email)
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
        $maintenance_on = isMaintenanceMode($conn);
    }

    if ($maintenance_on && !mayBypassMaintenance()) {
        $maintenance_message = settingGet($conn, 'maintenance_message', '');
        if (trim($maintenance_message) === '') {
            $maintenance_message = 'We are carrying out some work on the website. Please check back shortly.';
        }

        http_response_code(503);
        $page_title = 'Website under maintenance';
        $body_class = 'page-maintenance';
        require APP_ROOT . '/resources/views/partials/maintenance.php';
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
function tokenIssue($conn, $user_id)
{
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);

    /* The expiry is worked out by the DATABASE, not by PHP, on purpose.
       tokenRow() compares it with the database's own NOW(), and on this
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
function tokenRow($conn, $raw_token)
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
function tokenUse($conn, $token_id)
{
    $statement = $conn->prepare("UPDATE tokens SET used = NOW() WHERE id = ? AND used IS NULL");
    $token_id = (int) $token_id;
    $statement->bind_param('i', $token_id);
    $statement->execute();
    $statement->close();
}
