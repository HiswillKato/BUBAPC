<?php
/**
 * BUBAPC - The web installer
 *
 * The whole setup wizard in one file: the requirement checks, the schema import,
 * the writing of app/config.php, the POST handlers for each step, and the markup
 * for the screens. It is one file on purpose - somebody setting the church site
 * up has to be able to copy a single file to the server and have it work, and
 * there is nothing here that benefits from being split up.
 *
 * Open it once, in a browser, to set the website up. When it has finished it
 * finds the administrator it created in the database and refuses to run the
 * wizard again, so the file is safe to leave in place afterwards. The check is
 * a query rather than a lock file on purpose: deleting a file is easy to do by
 * accident, and forgetting one would quietly reopen the installer.
 *
 * The file is laid out in the order it runs, in four parts that used to be four
 * files:
 *
 *   ONE   THE ENTRY CODE   the constant, the load, the installed check
 *   TWO   THE HELPERS       nothing here prints; the words are all in part four
 *   THREE THE ACTIONS       nothing here runs on a GET request
 *   FOUR  THE SCREENS       all of the markup, and it draws nothing else
 *
 * BUBAPC_INSTALLER has to be defined BEFORE app/init.php runs, because
 * app/config.php looks at it: when it is set, config.php reads the settings
 * but does not try to connect to the database. That is what lets this page
 * draw step 1 and step 2 before a database exists.
 *
 * Every other page of the website must NOT define BUBAPC_INSTALLER.
 */

/* =========================================================================
   PART ONE - THE ENTRY CODE
   ========================================================================= */

define('BUBAPC_INSTALLER', true);

require_once __DIR__ . '/app/init.php';

/* Is the website already installed? This is asked ONCE, here, and the answer
   is then remembered, because the answer changes in the middle of the request
   that creates the very first administrator.

   If the screens were left to ask for themselves, the button that saves the
   administrator would put one in the database, the screens would then look and
   find it there, and the "well done, it is installed" screen would be replaced
   by the "already installed" screen on the same click. The person would never
   be told that the install worked.

   So it is decided here, before the actions run, and passed down. */
$already_installed = install_already_done();

/* Defaulted HERE, not only in the actions. They run solely when the website is
   not installed yet, but the screens read $step unconditionally
   ($current_step = (int) $step). So on every visit after the install had
   finished, $step did not exist and PHP warned "Undefined variable $step".

   This is the code that decides whether the actions run, so it is also the right
   place to promise the screens the variable. The actions still assign $step from
   $_GET, which overrides this default on a fresh install. */
$step = 1;

/* =========================================================================
   PART TWO - THE HELPERS
   ========================================================================= */

/**
 * BUBAPC - Installer helpers
 *
 * Everything the installer needs that is not a form handler lives here:
 * the "is it installed yet" check, the requirement checks, the database
 * connection, the schema import, and the writing of app/config.php.
 *
 * Nothing in this part prints anything. The words on the screen are all in
 * part 4, and anything that changes the computer is a function here that the
 * POST handlers in part 3 call.
 */

define('BUBAPC_ROOT', __DIR__);
define('BUBAPC_SCHEMA_FILE', BUBAPC_ROOT . '/database.sql');
define('BUBAPC_CONFIG_FILE', BUBAPC_ROOT . '/app/config.php');

/**
 * Has the website already been installed?
 *
 * The database itself is asked, not a lock file. A file would need the web
 * server to be able to write into the project folder, which is a permission
 * worth not having to grant, and a stray install.lock that nobody remembers
 * would then lock a fresh copy out. Asking the database cannot be faked: if
 * there is an administrator in the people table, the wizard has nothing left
 * to do.
 *
 * A database that cannot be reached counts as "not installed", because that
 * is the state where the wizard is genuinely useful.
 */
function install_already_done()
{
    $link = install_try_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!($link instanceof mysqli)) {
        return false;
    }

    $done = false;
    $result = @$link->query("SELECT COUNT(*) AS total FROM people WHERE role = 'admin'");
    if ($result instanceof mysqli_result) {
        $row = $result->fetch_assoc();
        $done = (int) ($row['total'] ?? 0) > 0;
    }

    $link->close();
    return $done;
}


/* =========================================================================
   1. THE CHECKS
   ========================================================================= */

/**
 * Look at the server and list anything that is not ready.
 *
 * Returns an array of 'ok' => message, or 'bad' => message. An empty array
 * means everything is fine. The database is NOT checked here, because there
 * is nothing to connect to until step 2 has been filled in.
 */
function install_checks()
{
    $results = [];

    $php_ok = version_compare(PHP_VERSION, '8.0.0', '>=');
    $results[] = $php_ok
        ? ['ok', 'PHP ' . PHP_VERSION . ' is installed.']
        : ['bad', 'PHP 8.0 or newer is needed. This server has ' . PHP_VERSION . '.'];

    foreach (['mysqli', 'mbstring', 'fileinfo', 'json'] as $needed) {
        $results[] = extension_loaded($needed)
            ? ['ok', 'The ' . $needed . ' extension is available.']
            : ['bad', 'The ' . $needed . ' extension is missing. Ask your host to turn it on.'];
    }

    // The schema file is read by the database step. Saying so now saves a
    // confusing failure two screens later.
    $results[] = is_readable(BUBAPC_SCHEMA_FILE)
        ? ['ok', 'database.sql is readable.']
        : ['bad', 'database.sql is missing or unreadable.'];

    // app/config.php has to be writable, because the database details and
    // the website details are saved back into it.
    $results[] = is_writable(BUBAPC_CONFIG_FILE)
        ? ['ok', 'app/config.php can be written to.']
        : ['bad', 'app/config.php cannot be written to. Give it write permission (chmod 664, and the '
            . 'file must belong to the user Apache runs as), or edit it by hand after installing.'];

    // The copy of config.php taken before the first change is a NEW file in
    // the app folder, so it needs the FOLDER to be writable, not the file.
    // is_writable() on the file itself would say yes while this still failed.
    $results[] = is_writable(dirname(BUBAPC_CONFIG_FILE))
        ? ['ok', 'The app folder can be written to, so a backup of config.php can be kept.']
        : ['bad', 'The app folder cannot be written to, so the installer cannot keep a backup of '
            . 'app/config.php. Give the folder write permission (chmod 775) or do it by hand.'];

    return $results;
}

/**
 * Is everything in the checks above fine?
 */
function install_checks_passed()
{
    foreach (install_checks() as $one) {
        if ($one[0] === 'bad') {
            return false;
        }
    }
    return true;
}

/* =========================================================================
   2. THE DATABASE
   ========================================================================= */

/**
 * Try to connect with the details a visitor typed.
 *
 * Returns the mysqli connection on success, or a sentence explaining the
 * problem. The sentence is safe to show: it never contains the password.
 */
function install_try_connect($host, $user, $password, $database)
{
    // mysqli throws on a failed connection since PHP 8.1, so the try/catch
    // is what stops a stack trace appearing on the screen.
    try {
        if ($database !== '') {
            $link = @new mysqli($host, $user, $password, $database);
        } else {
            $link = @new mysqli($host, $user, $password);
        }
    } catch (mysqli_sql_exception $problem) {
        return 'Could not connect: ' . install_friendly_db_error($problem->getMessage());
    }

    if ($link->connect_errno) {
        return 'Could not connect: ' . install_friendly_db_error($link->connect_error);
    }

    $link->set_charset('utf8mb4');
    return $link;
}

/**
 * Turn a MySQL error into a sentence a non-technical person can act on.
 *
 * The raw message names the database user and sometimes the host, so it is
 * never printed as it is.
 */
function install_friendly_db_error($raw)
{
    $raw = strtolower($raw);

    if (strpos($raw, 'access denied') !== false) {
        return 'the username or password is wrong, or that user is not allowed from this server.';
    }
    if (strpos($raw, 'unknown database') !== false || strpos($raw, '1049') !== false) {
        return 'there is no database with that name yet. Tick "create the database" and try again.';
    }
    if (strpos($raw, 'connection refused') !== false) {
        return 'the database server is not running, or the host is wrong.';
    }
    if (strpos($raw, 'can\'t connect') !== false) {
        return 'the database server could not be reached. Check the host name.';
    }
    return 'the server said "' . e($raw) . '".';
}

/**
 * Create the database if it is not there.
 * Returns true, or a sentence saying why not.
 */
function install_create_database($host, $user, $password, $database)
{
    $link = install_try_connect($host, $user, $password, '');
    if (!($link instanceof mysqli)) {
        return $link; // already a sentence
    }

    // The name is checked first because it is pasted into the SQL below.
    // Only letters, digits and underscores are allowed, so nothing else in
    // the name can end the quoted string.
    if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        return 'The database name may only use letters, numbers and underscores.';
    }

    $ok = @$link->query('CREATE DATABASE IF NOT EXISTS `' . $database
        . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    if (!$ok) {
        return 'The database could not be created. This user cannot create databases, so create one '
            . 'by hand in phpMyAdmin and untick the box below. (' . e($link->error) . ')';
    }

    $link->close();
    return true;
}

/**
 * Import database.sql into the chosen database.
 *
 * The file is read as plain text and handed to the server statement by
 * statement, so no shell and no mysql command line program is needed. That
 * keeps the installer working on any host that can run PHP.
 *
 * Returns the number of statements run, or a sentence saying what went wrong.
 */
function install_import_schema($link)
{
    if (!is_readable(BUBAPC_SCHEMA_FILE)) {
        return 'database/bubapc.sql could not be read.';
    }

    $sql_text = file_get_contents(BUBAPC_SCHEMA_FILE);
    if ($sql_text === false) {
        return 'database/bubapc.sql could not be read.';
    }

    // One statement per call. The file separates them with a semicolon at
    // the end of a line, which is enough here because it contains no stored
    // routines and no semicolons inside a string.
    $statements = install_split_sql($sql_text);

    $done = 0;
    foreach ($statements as $one) {
        // database/bubapc.sql says "CREATE DATABASE bubapc" and "USE bubapc"
        // so that it can be fed straight to the mysql command line program.
        // In the browser those two lines are skipped on purpose: the person
        // installing may well have typed a different database name, and
        // honouring the name in the file would quietly build the tables in
        // the wrong place.
        $upper = strtoupper($one);
        if (strpos($upper, 'CREATE DATABASE') === 0 || strpos($upper, 'USE ') === 0) {
            continue;
        }

        if (@$link->query($one)) {
            $done++;
            continue;
        }

        return 'The schema stopped on this statement: ' . e(substr($one, 0, 120))
            . ' — the server said "' . e($link->error) . '".';
    }

    return $done;
}

/**
 * Split a SQL file into single statements.
 *
 * Comment lines are dropped, and semicolons inside single quotes are left
 * alone so a sentence like "Bring; water" is not cut in half.
 */
function install_split_sql($sql_text)
{
    $lines = preg_split('/\r\n|\r|\n/', $sql_text);
    $statements = [];
    $current = '';
    $inside_quotes = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Skip whole-line comments, but keep anything after a statement.
        if (!$inside_quotes && strpos($trimmed, '--') === 0) {
            continue;
        }
        if (trim($trimmed) === '') {
            continue;
        }

        $current .= $line . "\n";

        // Count the quotes so a ; inside a string does not end the statement.
        $inside_quotes = (substr_count($line, "'") % 2) === 1 ? !$inside_quotes : $inside_quotes;

        // A statement is only finished when the line ENDS with a semicolon.
        // Merely containing one is not enough: a line such as
        //   INSERT INTO t VALUES ('Bring; water');
        // has a semicolon inside the text, and cutting there would produce
        // two broken statements.
        if (!$inside_quotes && substr(rtrim($line), -1) === ';') {
            $one = trim(rtrim(trim($current), ';'));
            if ($one !== '') {
                $statements[] = $one;
            }
            $current = '';
        }
    }

    $last = trim($current);
    if ($last !== '') {
        $statements[] = $last;
    }

    return $statements;
}

/**
 * Has the schema been imported? Used to decide whether step 4 can offer to
 * make the first administrator.
 */
function install_schema_ready($link)
{
    $result = @$link->query("SHOW TABLES LIKE 'people'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

/**
 * Create the first website administrator.
 *
 * Returns true, or a sentence saying why not. The administrator is a row in
 * the people table, the one table that now holds both the directory people and
 * the sign-in accounts; the password makes this row an account, and staff keeps
 * it off every public page. The password is hashed with the same call the login
 * page uses, so nothing here is readable back.
 */
function install_create_admin($link, $username, $full_name, $email, $password)
{
    $statement = $link->prepare(
        "INSERT INTO people (name, slug, category, email, username, password, role, standing, active, status)
         VALUES (?, ?, 'staff', ?, ?, ?, 'admin', 'member', 1, 0)"
    );
    if (!$statement) {
        return 'The administrator could not be saved: ' . e($link->error);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    /* The slug must be unique among people; it is built from the sign-in name
       and given a short tail so it can never collide with a directory row. */
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $username), '-'));
    $slug = ($slug === '' ? 'admin' : $slug) . '-' . bin2hex(random_bytes(3));

    $statement->bind_param('sssss', $full_name, $slug, $email, $username, $hash);

    if (!$statement->execute()) {
        $message = $statement->error;
        $statement->close();

        if (strpos($message, 'Duplicate entry') !== false) {
            return 'That username or email address is already taken. Try different ones.';
        }
        return 'The administrator could not be saved: ' . e($message);
    }

    $statement->close();
    return true;
}

/* =========================================================================
   3. SAVING TO app/config.php
   ========================================================================= */

/**
 * Change one define() line in app/config.php, keeping the rest of the file
 * exactly as it is.
 *
 * The file is read, the line is replaced, and the file is written back. The
 * comment banners and every other setting are untouched, so the file stays
 * readable and hand-editable afterwards.
 *
 * Returns true, or a sentence saying why not.
 */
function install_write_config($changes)
{
    if (!is_writable(BUBAPC_CONFIG_FILE)) {
        return 'app/config.php cannot be written to. Make it writable and try again.';
    }

    $contents = file_get_contents(BUBAPC_CONFIG_FILE);
    if ($contents === false) {
        return 'app/config.php could not be read.';
    }

    // Keep a copy before the first change, so a botched install can be put
    // back by hand. Written next to the file, never over an old backup.
    //
    // This is not silenced with @, unlike a few other file calls here. A
    // backup that quietly did not happen is worse than a clear refusal,
    // because the person would believe they could undo the install.
    if (!is_file(BUBAPC_CONFIG_FILE . '.before-install')) {
        if (!copy(BUBAPC_CONFIG_FILE, BUBAPC_CONFIG_FILE . '.before-install')) {
            return 'The settings could be saved, but no backup of app/config.php could be made '
                . 'because the app folder is not writable. Nothing has been lost, but make that '
                . 'folder writable (chmod 775) before trying again.';
        }
    }

    foreach ($changes as $constant => $value) {
        $line = "define('" . $constant . "', '" . install_quote_for_php($value) . "');";

        // Match the constant by name only, so the current value can be
        // anything at all, including a value with slashes in it.
        $pattern = "/define\(\s*'" . preg_quote($constant, '/') . "'\s*,[^)]*\);/";

        $replaced = preg_replace($pattern, install_preg_escape_replacement($line), $contents, 1, $count);
        if ($replaced === null) {
            return 'app/config.php could not be updated.';
        }
        if ($count !== 1) {
            return "app/config.php has no single define('$constant', ...) line to update. "
                . 'Please edit it by hand.';
        }
        $contents = $replaced;
    }

    if (@file_put_contents(BUBAPC_CONFIG_FILE, $contents) === false) {
        return 'app/config.php could not be saved.';
    }

    return true;
}

/**
 * Make a value safe to sit inside single quotes in PHP.
 *
 * A backslash or a single quote in the website tagline would otherwise end
 * the string early and break the whole configuration file.
 */
function install_quote_for_php($value)
{
    return str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value);
}

/**
 * preg_replace treats $ and \ in the replacement as special, so the line we
 * build has to be escaped before it goes in.
 */
function install_preg_escape_replacement($text)
{
    return str_replace(['\\', '$'], ['\\\\', '\\$'], $text);
}

/* =========================================================================
   PART THREE - THE ACTIONS
   ========================================================================= */

/* Nothing here runs on a GET request, and none of it runs once the website is
   installed, so no handler can act on a site that already has an administrator. */
if (!$already_installed) {
/**
 * BUBAPC - Installer actions
 *
 * This part decides what each POST request does. It runs in the scope of the
 * entry code above, so it can change $step, $error, $notice and $success,
 * which is how the screens know what to draw.
 *
 * THE ONE RULE OF THIS PART: nothing here may run on a GET request. A GET
 * only ever draws a form. That is what makes it safe to open installer.php in
 * a browser, click through the pages, and go back, without a single change
 * being made to the server.
 */

$step = isset($_GET['step']) ? max(1, min(5, (int) $_GET['step'])) : 1;
$error = '';
$notice = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Every form in the wizard carries a token from the session that
    // app/auth.php started. A missing or wrong one is refused here, before
    // anything is touched.
    verify_csrf();

    $action = post_text('action');

    /* ----------------------------------------------------------------------
       STEP 1 -> 2: the database
       ---------------------------------------------------------------------- */
    if ($action === 'save_database') {
        $host = post_text('db_host');
        $name = post_text('db_name');
        $user = post_text('db_user');
        $pass = (string) ($_POST['db_pass'] ?? '');
        $make_database = post_checkbox('create_database');

        if (is_empty_value($host) || is_empty_value($name) || is_empty_value($user)) {
            $error = 'Please fill in the host, the database name and the username.';
        } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $error = 'The database name may only use letters, numbers and underscores.';
        } else {

            $link = null;

            if ($make_database) {
                $made = install_create_database($host, $user, $pass, $name);
                if ($made !== true) {
                    $error = $made;
                }
            }

            if ($error === '') {
                $link = install_try_connect($host, $user, $pass, $name);
                if (!($link instanceof mysqli)) {
                    $error = $link;
                }
            }

            if ($error === '') {
                $imported = install_import_schema($link);
                if (!is_int($imported)) {
                    $error = $imported;
                }
            }

            if ($error === '') {

                // Hold the details for the next step, so the visitor does not
                // have to type the password twice.
                $_SESSION['install']['db'] = [
                    'host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass,
                ];

                $wrote = install_write_config([
                    'DB_HOST' => $host,
                    'DB_NAME' => $name,
                    'DB_USER' => $user,
                    'DB_PASS' => $pass,
                ]);
                if ($wrote !== true) {
                    $error = $wrote;
                } else {
                    $notice = 'The database is ready and app/config.php has been updated.';
                }
            }

            if ($error === '' && $link instanceof mysqli) {
                $link->close();
            }
        }

        if ($error === '') {
            $step = 3;
        }
    }

    /* ----------------------------------------------------------------------
       STEP 3: the website details, saved into app/config.php
       ---------------------------------------------------------------------- */
    if ($action === 'save_details') {
        $name_in = post_text('site_name');
        $full_name = post_text('site_full_name');
        $tagline = post_text('site_tagline');
        $email = post_text('site_email');
        $phone = post_text('site_phone');
        $location = post_text('site_location');

        if (is_empty_value($name_in) || is_empty_value($full_name)) {
            $error = 'The website name and the full church name are both needed.';
        } elseif ($email !== '' && !is_valid_email($email)) {
            $error = 'That email address does not look right.';
        } else {
            $wrote = install_write_config([
                'SITE_NAME' => $name_in,
                'SITE_FULL_NAME' => $full_name,
                'SITE_TAGLINE' => $tagline,
                'SITE_EMAIL' => $email,
                'SITE_PHONE' => $phone,
                'SITE_LOCATION' => $location,
            ]);

            if ($wrote !== true) {
                $error = $wrote;
            } else {
                $notice = 'The website details have been saved to app/config.php.';
                $step = 4;
            }
        }
    }

    /* ----------------------------------------------------------------------
       STEP 4: the first administrator
       ---------------------------------------------------------------------- */
    if ($action === 'save_admin') {
        $username = post_text('username');
        $full_name = post_text('full_name');
        $email = post_text('email');
        $password = (string) ($_POST['password'] ?? '');
        $password_again = (string) ($_POST['password_again'] ?? '');

        if (is_empty_value($username) || is_empty_value($full_name) || is_empty_value($email)) {
            $error = 'Please fill in the username, the name and the email address.';
        } elseif (!is_valid_email($email)) {
            $error = 'That email address does not look right.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
            $error = 'The username may only use letters, numbers, dot, dash and underscore, '
                . 'and must be at least 3 characters.';
        } elseif ($password !== $password_again) {
            $error = 'The two passwords are not the same.';
        } else {
            // The same rule the people form uses, so the first password is
            // held to the same standard as every password after it.
            $problem = password_problem($password);
            if ($problem !== '') {
                $error = $problem;
            }
        }

        if ($error === '') {
            $db = $_SESSION['install']['db'] ?? null;
            if (!is_array($db)) {
                $error = 'The database details have been lost. Please go back to step 2 and enter them again.';
            } else {
                $link = install_try_connect($db['host'], $db['user'], $db['pass'], $db['name']);
                if (!($link instanceof mysqli)) {
                    $error = $link;
                } else {
                    $made = install_create_admin($link, $username, $full_name, $email, $password);
                    $link->close();

                    if ($made !== true) {
                        $error = $made;
                    } else {
                        // The administrator existing IS the finished install,
                        // so there is nothing left to press. The next page
                        // load will notice the administrator in the database
                        // and show the "already installed" screen instead of
                        // the wizard.
                        $success = true;
                        $step = 5;
                    }
                }
            }
        }
    }
}
}

/* =========================================================================
   PART FOUR - THE SCREENS
   ========================================================================= */

/**
 * BUBAPC - The installer screens
 *
 * All of the markup for the wizard lives in this last part, so the code above
 * it stays short enough to read at a glance. It draws the current step and
 * nothing else; every change was already made by the actions.
 *
 * It is handed $already_installed, $step, $error, $notice and $success by the
 * entry code in part 1 rather than working them out for itself, so that a screen
 * cannot disagree with the decision that led to it.
 *
 * The styling is the same Bootstrap 5 and the same theme stylesheet as the
 * finished website, so the wizard looks like the site it is installing. It
 * adds no stylesheet of its own and no rules of its own; everything is a
 * Bootstrap card, form-control, btn or colour utility.
 */

$install_steps = [
    1 => 'Server check',
    2 => 'Database',
    3 => 'Website details',
    4 => 'Administrator',
    5 => 'Finished',
];
$current_step = (int) $step;
$checks = install_checks();
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install BUBAPC</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="<?php echo asset('css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/all.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
</head>
<body class="page-install">
<main class="container py-4 py-lg-5">

    <div class="text-center mb-4">
        <h1 class="h3 mb-1">Install BUBAPC</h1>
        <p class="text-body-secondary mb-0">Busitema University Born Again Pentecostal Church</p>
    </div>

    <?php if ($already_installed): ?>
        <!-- ================= ALREADY INSTALLED ================= -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <i class="fa-solid fa-circle-check fa-3x text-success" aria-hidden="true"></i>
                    <h2 class="h4 mt-2 mb-1">This website is installed</h2>
                    <p class="text-body-secondary mb-0">
                        An administrator already exists in the database, so there is nothing left
                        to set up and installer.php will keep saying this.
                    </p>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a class="btn btn-gold" href="<?php echo url('login'); ?>">
                        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Go to the admin login
                    </a>
                    <a class="btn btn-outline-secondary" href="<?php echo url(''); ?>">
                        <i class="fa-solid fa-house" aria-hidden="true"></i> Go to the home page
                    </a>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body p-4">
                <h3 class="h6 text-uppercase text-body-secondary">If you have lost the administrator password</h3>
                <p class="text-body-secondary small mb-2">
                    The installer will not make a second administrator, on purpose. Set a new password
                    from the command line on the server instead, where the password never travels over
                    the web. Open the database, run the UPDATE below with a hash you generate first,
                    then delete the rest of this page if you like.
                </p>
                <pre class="bg-body-secondary p-3 rounded small mb-0"><code>php -r 'echo password_hash("YourNewPassword1", PASSWORD_DEFAULT), "\n";'

mysql bubapc -e "UPDATE people SET password = 'PASTE_THE_HASH_HERE' WHERE username = 'admin';"

sudo rm /var/www/bubapc/installer.php</code></pre>
            </div>
        </div>

    <?php else: ?>

        <!-- ================= THE WIZARD ================= -->
        <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-5 g-2 mb-4">
            <?php foreach ($install_steps as $number => $label): ?>
                <?php
                /* The chip already behind us is pale, the one being looked at is
                   deep brand, and the ones ahead are neutral. Bootstrap colours
                   only; there is no installer stylesheet to keep in step. */
                if ($number === $current_step) {
                    $class = 'bg-brand-deep text-white border-transparent';
                } elseif ($number < $current_step) {
                    $class = 'bg-brand-soft text-brand';
                } else {
                    $class = 'bg-body-secondary text-body-secondary';
                }
                ?>
                <div class="col">
                    <div class="h-100 d-flex align-items-center justify-content-center text-center rounded-3 px-2 py-2 small fw-semibold border <?php echo e($class); ?>">
                        <?php echo e($number); ?>. <?php echo e($label); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($notice !== ''): ?>
            <div class="alert alert-success" role="alert">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i> <?php echo e($notice); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 text-center">
                    <i class="fa-solid fa-circle-check fa-3x text-success mb-3" aria-hidden="true"></i>
                    <h2 class="h4">The administrator is saved &mdash; the website is installed</h2>
                    <p class="text-body-secondary">
                        There is nothing left to press. Because an administrator now exists in the
                        database, installer.php will show &ldquo;already installed&rdquo; from now on and
                        can be deleted from the server whenever you like.
                    </p>
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <a class="btn btn-gold" href="<?php echo url('login'); ?>">
                            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Sign in as the new administrator
                        </a>
                        <a class="btn btn-outline-secondary" href="<?php echo url(''); ?>">
                            <i class="fa-solid fa-house" aria-hidden="true"></i> Go to the home page
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">

                    <?php /* ---------------- STEP 1: the server check ---------------- */ ?>
                    <?php if ($current_step === 1): ?>
                        <h2 class="h4 mb-3">Does this server have everything it needs?</h2>

                        <?php foreach ($checks as $one): ?>
                            <div class="d-flex gap-2 align-items-start py-1">
                                <?php if ($one[0] === 'ok'): ?>
                                    <i class="fa-solid fa-circle-check text-success mt-1" aria-hidden="true"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-circle-xmark text-danger mt-1" aria-hidden="true"></i>
                                <?php endif; ?>
                                <span><?php echo e($one[1]); ?></span>
                            </div>
                        <?php endforeach; ?>

                        <?php if (install_checks_passed()): ?>
                            <p class="text-body-secondary mt-3 mb-0">
                                Nothing is wrong that the installer can fix. The next screen writes to
                                your database, so take a copy of anything you care about first.
                            </p>
                        <?php else: ?>
                            <div class="alert alert-warning mt-3 mb-0">
                                Fix the red lines above and reload this page. The installer cannot go on
                                until they are all green.
                            </div>
                        <?php endif; ?>

                        <?php if (install_checks_passed()): ?>
                            <form method="post" class="mt-3">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="save_database">
                                <input type="hidden" name="db_host" value="<?php echo e(DB_HOST); ?>">
                                <input type="hidden" name="db_name" value="<?php echo e(DB_NAME); ?>">
                                <input type="hidden" name="db_user" value="<?php echo e(DB_USER); ?>">
                                <input type="hidden" name="db_pass" value="<?php echo e(DB_PASS); ?>">
                                <input type="hidden" name="create_database" value="1">
                                <button class="btn btn-gold" type="submit">
                                    These are the details in app/config.php &mdash; use them
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </button>
                            </form>
                        <?php endif; ?>

                    <?php /* ---------------- STEP 2: the database ---------------- */ ?>
                    <?php elseif ($current_step === 2): ?>
                        <h2 class="h4 mb-1">Where is the database?</h2>
                        <p class="text-body-secondary">
                            Leave these as they are if the details in <code>app/config.php</code> are correct.
                        </p>

                        <form method="post" class="row g-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="save_database">

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="db_host">Host</label>
                                <input class="form-control" type="text" id="db_host" name="db_host"
                                       value="<?php echo e(post_text('db_host') ?: DB_HOST); ?>" required>
                                <div class="form-text">Usually <code>localhost</code>.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="db_name">Database name</label>
                                <input class="form-control" type="text" id="db_name" name="db_name"
                                       value="<?php echo e(post_text('db_name') ?: DB_NAME); ?>" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="db_user">Username</label>
                                <input class="form-control" type="text" id="db_user" name="db_user"
                                       value="<?php echo e(post_text('db_user') ?: DB_USER); ?>" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="db_pass">Password</label>
                                <input class="form-control" type="password" id="db_pass" name="db_pass"
                                       value="<?php echo e(post_text('db_pass') ?: DB_PASS); ?>" autocomplete="off">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1"
                                           id="create_database" name="create_database" checked>
                                    <label class="form-check-label" for="create_database">
                                        Create the database if it is not there yet
                                    </label>
                                </div>
                                <div class="form-text">
                                    The schema in <code>database/bubapc.sql</code> then drops and rebuilds
                                    every table, so <strong>this erases anything already in the database</strong>.
                                </div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-gold" type="submit">
                                    <i class="fa-solid fa-database" aria-hidden="true"></i> Save and import the schema
                                </button>
                            </div>
                        </form>

                    <?php /* ---------------- STEP 3: the website details ---------------- */ ?>
                    <?php elseif ($current_step === 3): ?>
                        <h2 class="h4 mb-1">The website details</h2>
                        <p class="text-body-secondary">These are written into <code>app/config.php</code>.</p>

                        <form method="post" class="row g-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="save_details">

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="site_name">Short name</label>
                                <input class="form-control" type="text" id="site_name" name="site_name"
                                       value="<?php echo e(post_text('site_name') ?: SITE_NAME); ?>" required>
                                <div class="form-text">Used in the browser tab and the footer.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="site_full_name">Full church name</label>
                                <input class="form-control" type="text" id="site_full_name" name="site_full_name"
                                       value="<?php echo e(post_text('site_full_name') ?: SITE_FULL_NAME); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="site_tagline">Tagline</label>
                                <input class="form-control" type="text" id="site_tagline" name="site_tagline"
                                       value="<?php echo e(post_text('site_tagline') ?: SITE_TAGLINE); ?>">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="site_email">Email</label>
                                <input class="form-control" type="email" id="site_email" name="site_email"
                                       value="<?php echo e(post_text('site_email') ?: SITE_EMAIL); ?>">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="site_phone">Phone</label>
                                <input class="form-control" type="text" id="site_phone" name="site_phone"
                                       value="<?php echo e(post_text('site_phone') ?: SITE_PHONE); ?>">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="site_location">Location</label>
                                <input class="form-control" type="text" id="site_location" name="site_location"
                                       value="<?php echo e(post_text('site_location') ?: SITE_LOCATION); ?>">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-gold" type="submit">
                                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save these details
                                </button>
                            </div>
                        </form>

                    <?php /* ---------------- STEP 4: the administrator ---------------- */ ?>
                    <?php elseif ($current_step === 4): ?>
                        <h2 class="h4 mb-1">The first administrator</h2>
                        <p class="text-body-secondary">
                            This person signs in at <code><?php echo url('login'); ?></code> and can do
                            everything in the admin panel. Later accounts can be given the editor job
                            instead, which cannot open the users and settings screens.
                        </p>

                        <form method="post" class="row g-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="save_admin">

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="username">Username</label>
                                <input class="form-control" type="text" id="username" name="username"
                                       value="<?php echo e(post_text('username')); ?>" required
                                       autocomplete="username" autofocus>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="full_name">Full name</label>
                                <input class="form-control" type="text" id="full_name" name="full_name"
                                       value="<?php echo e(post_text('full_name')); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="email">Email</label>
                                <input class="form-control" type="email" id="email" name="email"
                                       value="<?php echo e(post_text('email')); ?>" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="password">Password</label>
                                <input class="form-control" type="password" id="password" name="password"
                                       required autocomplete="new-password">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="password_again">Type it again</label>
                                <input class="form-control" type="password" id="password_again"
                                       name="password_again" required autocomplete="new-password">
                            </div>
                            <div class="col-12">
                                <div class="form-text">
                                    At least <?php echo e(MIN_PASSWORD_LENGTH); ?> characters, with a capital
                                    letter, a small letter and a number.
                                </div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-gold" type="submit">
                                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Create the administrator
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <p class="text-center text-body-secondary small mt-4 mb-0">
        BUBAPC installer &mdash; one screen at a time, nothing is saved until you press a button.
    </p>
</main>
</body>
</html>
