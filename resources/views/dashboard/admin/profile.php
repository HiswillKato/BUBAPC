<?php
/**
 * BUBAPC - Admin: my own details
 *
 * Every signed in person has this page, editors included, because changing
 * your own password is not an admin job. Nobody can edit anybody else's account
 * from here: that is the People page, and it is for admins only.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'profile';

$me = currentUser();
$my_id = (int) $me['id'];

$errors = [];
$done = '';

$full_name = $me['full_name'];
$email = $me['email'];

/* The password boxes are never filled in. They are only here so the person
   typed something, and an empty box on the second form means "leave it". */
$current_password = '';
$new_password = '';
$confirm_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $what = postText('what');

    if ($what === 'password') {
        $current_password = (string) ($_POST['current_password'] ?? '');
        $new_password = (string) ($_POST['new_password'] ?? '');
        $confirm_password = (string) ($_POST['confirm_password'] ?? '');

        /* The current password is checked even though the person is already
           signed in. On a shared computer it stops one person changing the
           password of whoever is sitting there. */
        if (!password_verify($current_password, $me['password'])) {
            $errors['current_password'] = 'That is not your current password.';
        }
        if ($new_password !== $confirm_password) {
            $errors['confirm_password'] = 'The two new passwords are not the same.';
        }
        $problem = passwordProblem($new_password);
        if ($problem !== '') {
            $errors['new_password'] = $problem;
        }

        if (!$errors) {
            $statement = $conn->prepare("UPDATE people SET password = ? WHERE id = ?");
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $statement->bind_param('si', $hash, $my_id);
            $statement->execute();
            $statement->close();

            $current_password = '';
            $new_password = '';
            $confirm_password = '';
            $done = 'Your password has been changed. Use the new one the next time you sign in.';
        }
    } else {
        $full_name = postText('full_name');
        $email = postText('email');

        adminCheckRequired($full_name, 'Your name', $errors);
        adminCheckRequired($email, 'Your email address', $errors);
        if (!isEmptyValue($email) && !isValidEmail($email)) {
            $errors['email'] = 'That email address does not look right.';
        }

        /* Somebody else may already have that address on their account. A
           directory-only person may share it, so the check is limited to rows
           that carry a sign-in. */
        if (!isEmptyValue($email)) {
            $statement = $conn->prepare(
                "SELECT id FROM people WHERE password IS NOT NULL AND (username = ? OR email = ?) AND id <> ? LIMIT 1"
            );
            $statement->bind_param('ssi', $email, $email, $my_id);
            $statement->execute();
            $taken = $statement->get_result()->fetch_assoc();
            $statement->close();

            if ($taken) {
                $errors['email'] = 'Somebody else already uses that email address.';
            }
        }

        if (!$errors) {
            $statement = $conn->prepare("UPDATE people SET name = ?, email = ? WHERE id = ?");
            $statement->bind_param('ssi', $full_name, $email, $my_id);
            $statement->execute();
            $statement->close();

            /* The session keeps a copy of the row, so it is thrown away here
               and currentUser() reads the fresh one when the header needs it. */
            unset($_SESSION['user_data']);

            $done = 'Your details have been saved.';
        }
    }
}

$page_title = 'My profile';
$page_heading = 'My profile';

require_once __DIR__ . '/../../partials/admin/header.php';
?>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">

            <?php if ($done !== ''): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i><?php echo e($done); ?>
                </div>
            <?php endif; ?>

            <?php adminErrors($errors); ?>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <?php echo avatar($me['image'] ?? '', $me['full_name'], 64); ?>
                        <div>
                            <h2 class="h5 mb-1"><?php echo e($me['full_name']); ?></h2>
                            <p class="small text-body-secondary mb-0">
                                <?php echo e(userRoleLabel($me['role'] ?? 'member')); ?>
                                &middot; signed in as <strong><?php echo e($me['username']); ?></strong>
                            </p>
                        </div>
                    </div>

                    <form method="post" novalidate>
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="what" value="details">

                        <div class="row">
                            <div class="col-md-6">
                                <?php adminTextField('full_name', 'Your name', $full_name); ?>
                            </div>
                            <div class="col-md-6">
                                <?php adminTextField('email', 'Your email address', $email); ?>
                            </div>
                        </div>

                        <p class="small text-body-secondary mb-0">
                            Your username never changes, so that somebody who has typed it for years is not
                            confused. Only a website administrator can change it, on the People page.
                        </p>

                        <?php adminFormButtons(adminUrl('profile'), 'Save my details'); ?>
                    </form>

                    <h2 class="h5 mt-5 mb-3">
                        <i class="fa-solid fa-key me-2 text-gold" aria-hidden="true"></i>Change my password
                    </h2>

                    <form method="post" novalidate autocomplete="off">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="what" value="password">

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label" for="current_password">Current password</label>
                                    <input type="password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>"
                                           id="current_password" name="current_password" required
                                           autocomplete="current-password">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label" for="new_password">New password</label>
                                    <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>"
                                           id="new_password" name="new_password" required
                                           minlength="<?php echo (int) MIN_PASSWORD_LENGTH; ?>"
                                           autocomplete="new-password">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label" for="confirm_password">Type it again</label>
                                    <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                           id="confirm_password" name="confirm_password" required
                                           autocomplete="new-password">
                                </div>
                            </div>
                        </div>

                        <p class="small text-body-secondary mb-0">
                            At least <?php echo (int) MIN_PASSWORD_LENGTH; ?> characters, with a capital
                            letter, a number and a symbol. A short sentence you will remember is better
                            than a short password you will not.
                        </p>

                        <?php adminFormButtons(adminUrl('profile'), 'Change my password'); ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../../partials/admin/footer.php'; ?>
