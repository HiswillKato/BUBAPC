<?php
/**
 * BUBAPC - Choosing a new password
 *
 * Step two of the reset, reached from the link in the email. The link carries
 * a token; the database only ever holds the SHA-256 hash of it, and tokenRow()
 * in app/auth.php refuses a token that is used or out of date, so a link
 * cannot be replayed.
 *
 * The GET shows the form only if the token is good. The POST does the work and
 * then sends the person to sign in, rather than signing them in from here:
 * signing in is a separate, ordinary step on a page that is also what a
 * stranger sees if they follow an old or forwarded link.
 */

define('BUBAPC_SKIP_MAINTENANCE', true);

require_once __DIR__ . '/../../../app/init.php';

/* The token comes from the link, and from the hidden field on the form. Both
   are the same string; the hidden one is there because a form post cannot be
   trusted to still be talking about the token the page was opened with. */
$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$tokenRow = $token !== '' ? tokenRow($conn, $token) : null;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $token = postText('token');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    /* Looked up again on the POST. Checking only on the GET would mean a
       token that was spent between the two requests still worked. */
    $tokenRow = $token !== '' ? tokenRow($conn, $token) : null;

    if (!$tokenRow) {
        $errors['token'] = 'This link is no longer valid. Please ask for a new one.';
    }

    if ($password !== $confirm) {
        $errors['confirm'] = 'The two passwords are not the same.';
    }

    $problem = passwordProblem($password);
    if ($problem !== '') {
        $errors['password'] = $problem;
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        /* Put the hash in a variable first: bind_param() takes its values by
           reference, so handing it the result of a function call produces a
           notice and stores nothing. */
        $statement = $conn->prepare('UPDATE people SET password = ? WHERE id = ?');
        $user_id = (int) $tokenRow['user'];
        $statement->bind_param('si', $hash, $user_id);
        $statement->execute();
        $statement->close();

        /* Spent now, whatever happens next, so the same link cannot be used
           to change the password again. */
        tokenUse($conn, $tokenRow['id']);

        logAction($conn, 'password_changed', 'A password was changed with a reset link');

        flash('success', 'Your password has been changed. Please sign in with it.');
        redirect('login');
    }
}

$page_title = 'Choose a new password';
$page_description = 'Choose a new ' . SITE_NAME . ' password.';

require_once __DIR__ . '/../partials/public/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">

                    <?php if (!$tokenRow): ?>
                        <i class="fa-solid fa-link-slash fa-3x text-body-secondary mb-3" aria-hidden="true"></i>
                        <h1 class="h4 mb-2">This link does not work</h1>
                        <p class="text-body-secondary mb-4">
                            A reset link works once and stops working after
                            <?php echo e(TOKEN_RESET_HOURS); ?> hours. This one has already been used,
                            or it has expired.
                        </p>
                        <a class="btn btn-primary" href="<?php echo url('forgot'); ?>">
                            <i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i>Ask for a new link
                        </a>

                    <?php else: ?>
                        <h1 class="h4 mb-2">Choose a new password</h1>
                        <p class="text-body-secondary mb-4">
                            Pick something you will remember and have not used here before. At least
                            <?php echo e(MIN_PASSWORD_LENGTH); ?> characters, with a letter and a number in it.
                        </p>

                        <form method="post" action="<?php echo url('reset'); ?>" novalidate>
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="token" value="<?php echo e($token); ?>">

                            <?php if (isset($errors['token'])): ?>
                                <div class="alert alert-danger" role="alert"><?php echo e($errors['token']); ?></div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label" for="password">New password</label>
                                <input type="password" class="form-control<?php echo isset($errors['password']) ? ' is-invalid' : ''; ?>"
                                       id="password" name="password" autocomplete="new-password" required autofocus>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['password']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="confirm">Type it again</label>
                                <input type="password" class="form-control<?php echo isset($errors['confirm']) ? ' is-invalid' : ''; ?>"
                                       id="confirm" name="confirm" autocomplete="new-password" required>
                                <?php if (isset($errors['confirm'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['confirm']); ?></div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-key me-1" aria-hidden="true"></i>Change my password
                            </button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/public/footer.php'; ?>