<?php
/**
 * BUBAPC - Signing in to the admin panel
 *
 * A GET shows the form. A POST checks the password and either opens the admin
 * panel or explains what went wrong. The slow-down of repeated wrong
 * passwords is done by the three helpers in app/auth.php, so a stolen guess
 * is no use to anyone.
 */

/* This page has to stay reachable while the website is closed for
   maintenance. The check in app/auth.php shows the maintenance message to
   everybody who is not already signed in, which is right for the rest of the
   site but would lock the admin out of the very page they need to switch it
   off again. Naming the constant here is the way to say "not this page". */
define('BUBAPC_SKIP_MAINTENANCE', true);

require_once __DIR__ . '/../../../app/init.php';

/* Someone who is already signed in has no business on the login page. */
if (is_logged_in()) {
    redirect(is_staff() ? admin_url() : member_url());
}

$page_title = 'Login';
$login_username = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $login_username = post_text('username');
    $password = (string) ($_POST['password'] ?? '');
    $next = post_text('next');

    $locked_for = login_lockout_minutes($conn, $login_username);
    if ($locked_for > 0) {
        flash('error', 'Too many wrong passwords. Please try again in ' . $locked_for . ' minutes.');
        redirect('login');
    }

    $statement = $conn->prepare(
        "SELECT p.*, p.name AS full_name
         FROM people p
         WHERE p.password IS NOT NULL AND (p.username = ? OR p.email = ?)
         LIMIT 1"
    );
    $statement->bind_param('ss', $login_username, $login_username);
    $statement->execute();
    $result = $statement->get_result();
    $user = $result->fetch_assoc();
    $statement->close();

    /* The same message for a wrong name and a wrong password, so the form
       never tells a stranger whether an account exists. */
    $password_ok = $user && password_verify($password, $user['password']);
    $account_usable = $user && (int) $user['active'] === 1;

    login_record_attempt($conn, $login_username, (bool) ($password_ok && $account_usable));

    if ($password_ok && $account_usable) {
        login_clear_attempts($conn, $user['username']);

        /* A fresh session id after signing in, so a session id that was
           guessed before the login cannot be used afterwards. The session
           itself is written by sign_user_in() in app/auth.php, which
           register.php uses too. */
        sign_user_in($user);

        /* Only a path on this website is followed, never a whole address
           from somebody else, which is what stops the "log in and get sent to
           another site" trick. */
        if ($next !== '' && preg_match('#^/[^/\\\\]#', $next) === 1) {
            header('Location: ' . $next);
            exit;
        }

        flash('success', 'Welcome back, ' . $user['full_name'] . '.');

        /* Staff go to the admin panel and church members to their own account
           page. */
        redirect(is_staff() ? admin_url() : member_url());
    }

    if ($password_ok && !$account_usable) {
        /* A leader who has never signed in is waiting to be approved, which
           reads differently from an account an admin has switched off. */
        if ($user['role'] === 'leader' && $user['login'] === null) {
            $errors[] = 'That account is waiting to be approved by an administrator. Please try again later.';
        } else {
            $errors[] = 'That account has been switched off. Please ask somebody with administrator access to turn it back on.';
        }
    } else {
        $errors[] = 'That username or password is not right.';
    }
}

require_once __DIR__ . '/../../../app/includes/public/header.php';

/* The form partial reads these, so the page hands over what happened and
   where to go back to, and leaves the wording to the partial. */
$login_errors = $errors;
?>

    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-8 col-lg-5">
                    <?php require __DIR__ . '/../../../app/includes/public/login-form.php'; ?>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../../../app/includes/public/footer.php'; ?>
