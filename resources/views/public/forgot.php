<?php
/**
 * BUBAPC - Forgot your password
 *
 * Step one of the reset: the visitor types the address on their account and
 * we send a link to it. There is deliberately no question asking who they
 * are and no code they have to copy out of a second message, because a church
 * member should not have to get that right from a phone at midnight.
 *
 * What the page does NOT do is say whether the address is on the website.
 * A page that answers "no such account" hands a stranger a list of who is a
 * member here, so the same message is shown either way and the real answer
 * only ever travels by email to the person who owns the address.
 */

require_once __DIR__ . '/../../../app/init.php';

$page_title = 'Forgot your password';
$page_description = 'Send yourself a link to choose a new ' . SITE_NAME . ' password.';

$sent = false;
$formErrors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email = postText('email');

    if (!isValidEmail($email)) {
        $formErrors['email'] = 'Please type the email address you signed in with.';
    }

    if (!$formErrors) {
        $statement = $conn->prepare("SELECT id, username, email, name AS full_name FROM people
                                     WHERE password IS NOT NULL AND email = ? AND active = 1 LIMIT 1");
        $statement->bind_param('s', $email);
        $statement->execute();
        $user = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($user) {
            /* Any account may reset its password. The link is sent to the address on the account. */
            $raw = tokenIssue($conn, $user['id']);

            if ($raw !== '') {
                mailPasswordReset($user, $raw);
            }

            logAction($conn, 'password_reset', 'A reset link was sent to ' . $email);
        }

        /* The answer is the same either way, and that is the whole point: the
           page never confirms whether an address belongs to an account. */
        $sent = true;
    }
}

require_once __DIR__ . '/../partials/public/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">

            <?php if ($sent): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4 p-md-5 text-center">
                        <i class="fa-solid fa-envelope-circle-check fa-3x text-gold mb-3" aria-hidden="true"></i>
                        <h1 class="h4 mb-3">Check your email</h1>
                        <p class="text-body-secondary mb-4">
                            If that address is on our records, a link to choose a new password is on its way.
                            It works once, and stops working after <?php echo e(TOKEN_RESET_HOURS); ?> hours.
                        </p>
                        <p class="small text-body-secondary mb-4">
                            Nothing arrived? Look in the spam folder, then ask the person who looks after
                            the website to check that your address is spelled the way you typed it.
                        </p>
                        <a class="btn btn-outline-secondary" href="<?php echo url('login'); ?>">
                            <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Back to sign in
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4 p-md-5">
                        <h1 class="h4 mb-2">Forgot your password?</h1>
                        <p class="text-body-secondary mb-4">
                            Type the email address you signed in with and we will send you a link to
                            choose a new one.
                        </p>

                        <form method="post" action="<?php echo url('forgot'); ?>" novalidate>
                            <?php echo csrfField(); ?>

                            <div class="mb-3">
                                <label class="form-label" for="email">Email address</label>
                                <input type="email" class="form-control<?php echo isset($formErrors['email']) ? ' is-invalid' : ''; ?>"
                                       id="email" name="email" value="<?php echo e($email); ?>"
                                       autocomplete="email" required autofocus>
                                <?php if (isset($formErrors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo e($formErrors['email']); ?></div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i>Send the link
                            </button>
                        </form>

                        <p class="small text-body-secondary text-center mt-4 mb-0">
                            <a href="<?php echo url('login'); ?>">Back to sign in</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/public/footer.php'; ?>