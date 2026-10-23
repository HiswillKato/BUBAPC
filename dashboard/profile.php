<?php
/**
 * BUBAPC - Changing your own details
 *
 * A member may change their name, email, phone number and birthday, and their
 * password. Nothing else: the role column is not on this form, so there is no
 * way for a member to promote themselves to a website admin by editing their
 * own page.
 *
 * The birthday is asked for here as well as on the church profile, because a
 * member who signed themselves up has no directory page to edit - but their
 * row is in the same table, so their birthday counts in the church's month
 * just like everybody else's. The year is shown to nobody.
 *
 * The email is checked against the other accounts, and the row that is updated
 * is always the one belonging to the signed in person, taken from the session.
 * The id is never read from the form, which is what stops somebody posting an
 * id that is not theirs and overwriting another member's details.
 */

require_once __DIR__ . '/../app/init.php';

require_login();

$member = current_user();
$errors = [];

/* A linked person's name, email and phone are kept on their church profile,
   where they also appear on the site. Editing them here as well would let the
   two drift apart, so this page only holds the password for those people. */
$person = current_person($conn);

$full_name = $member['full_name'];
$email = $member['email'];
$phone = $member['phone'];
$born = (string) $member['born'];

/* Which of the two forms on the page was filled in. A password change is its
   own POST so that a mistake in one does not throw away the other's typing. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (post_text('form') === 'password') {
        $current_password = (string) ($_POST['current_password'] ?? '');
        $new_password = (string) ($_POST['new_password'] ?? '');
        $new_confirm = (string) ($_POST['new_password_confirm'] ?? '');

        /* The current password is asked for even though the session already
           proves who this is. It is what stops somebody walking up to a
           browser that is still signed in and taking the account over. */
        $statement = $conn->prepare("SELECT password FROM people WHERE id = ? LIMIT 1");
        $member_id = (int) $member['id'];
        $statement->bind_param('i', $member_id);
        $statement->execute();
        $stored = $statement->get_result()->fetch_assoc();
        $statement->close();

        if (!$stored || !password_verify($current_password, $stored['password'])) {
            $errors[] = 'That is not your current password.';
        }

        $password_error = password_problem($new_password);
        if ($password_error !== '') {
            $errors[] = $password_error;
        } elseif ($new_password !== $new_confirm) {
            $errors[] = 'The two new passwords do not match.';
        } elseif ($stored && $new_password === $current_password) {
            $errors[] = 'The new password is the same as the old one.';
        }

        if (!$errors) {
            /* The hash goes into a variable of its own rather than straight
               into bind_param(), which takes its values by reference and would
               otherwise print "Only variables should be passed by reference". */
            $hash = password_hash($new_password, PASSWORD_DEFAULT);

            $statement = $conn->prepare("UPDATE people SET password = ? WHERE id = ?");
            $statement->bind_param('si', $hash, $member_id);
            $saved = $statement->execute();
            $statement->close();

            if ($saved) {
                /* The cached copy of the row still holds the old state, and it
                   is in the session rather than the database, so it has to be
                   cleared by hand or this page would carry on showing it. */
                unset($_SESSION['user_data']);

                flash('success', 'Your password has been changed.');
                redirect('dashboard/profile');
            }

            $errors[] = 'Your password could not be changed. Please try again.';
        }
    } else {
        /* A linked person edits their own details on their church profile, so
           a details form should never arrive from here. Send them to where the
           change belongs rather than quietly ignoring it. */
        if ($person) {
            redirect('dashboard/person');
        }

        $full_name = post_text('full_name');
        $email = post_text('email');
        $phone = post_text('phone');
        $born_typed = post_text('born');

        if (is_empty_value($full_name)) {
            $errors[] = 'Please tell us your name.';
        } elseif (mb_strlen($full_name) > 160) {
            $errors[] = 'That name is too long. Please use 160 characters or fewer.';
        }

        if (!is_valid_email($email)) {
            $errors[] = 'Please type a real email address, for example name@example.com.';
        } elseif ($email !== $member['email']) {
            /* The address is the sign-in name, so no other account may use it.
               A directory-only person may share it, so the check is limited to
               rows that carry a password. */
            $statement = $conn->prepare(
                "SELECT id FROM people WHERE password IS NOT NULL AND (username = ? OR email = ?) AND id <> ? LIMIT 1"
            );
            $member_id = (int) $member['id'];
            $statement->bind_param('ssi', $email, $email, $member_id);
            $statement->execute();
            $clash = (bool) $statement->get_result()->fetch_assoc();
            $statement->close();

            if ($clash) {
                $errors[] = 'Another account already uses that email address.';
            }
        }

        /* One helper checks the birthday for both forms that ask for one,
           so this page and the church profile can never disagree. It
           answers with the value for the column, an empty string for an
           empty box, or null for something that is not a birthday. */
        $birthday = birthday_from_form($born_typed);
        if ($birthday === null) {
            $errors[] = 'Please type your birthday as a real day, or leave the box empty.';
        }
        $born = $birthday ?? $born_typed;

        if (!$errors) {
            /* The name, the email and the sign-in name are on the one row now,
               so they are written together. The sign-in name follows the
               address, because a member signs in with the address they typed.
               An empty birthday box is a NULL in the column, not an empty
               date, so a PHP null is sent for it. */
            $born_value = $birthday !== '' ? $birthday : null;
            $statement = $conn->prepare(
                "UPDATE people SET name = ?, email = ?, username = ?, phone = ?, born = ? WHERE id = ?"
            );
            $member_id = (int) $member['id'];
            $statement->bind_param(
                'sssssi',
                mb_substr($full_name, 0, 160),
                mb_substr($email, 0, 190),
                mb_substr($email, 0, 190),
                mb_substr($phone, 0, 40),
                $born_value,
                $member_id
            );
            $saved = $statement->execute();
            $insert_error = $statement->error;
            $statement->close();

            if ($saved) {
                /* The session holds a copy of the row, so the change is not
                   visible until that copy is thrown away. */
                unset($_SESSION['user_data']);

                flash('success', 'Your details have been saved.');
                redirect(member_url());
            }

            $errors[] = 'Your details could not be saved'
                . ($insert_error ? ' (' . $insert_error . ')' : '') . '. Please try again.';
        }
    }
}

$page_title = 'My details';
require_once __DIR__ . '/../app/includes/public/header.php';
?>

    <section class="py-4 py-lg-5">
        <div class="container">
            <?php page_header(
                'My details',
                '',
                [['Home', url('')], ['My account', member_url()], ['My details', null]]
            ); ?>

            <?php form_errors($errors); ?>
            <?php show_flashes(); ?>

            <?php /* One card holds both. They are two separate submissions, so
                   each keeps its own form and its own button, and a heading -
                   not a line - is what separates them. */ ?>
            <div class="row justify-content-center">
                <div class="col-12 col-xl-10">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">

                            <?php if ($person): ?>
                                <h2 class="h5 mb-3">Your church details</h2>
                                <p class="text-body-secondary mb-3">
                                    Your name, email and phone are kept on your church profile, where they also
                                    appear on the site.
                                </p>
                                <a href="<?php echo url('dashboard/person'); ?>" class="btn btn-primary">
                                    <i class="fa-solid fa-id-card me-1" aria-hidden="true"></i>
                                    My church profile
                                </a>
                            <?php else: ?>
                                <h2 class="h5 mb-3">Your details</h2>

                                <form method="post" action="<?php echo url('dashboard/profile'); ?>" novalidate>
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="form" value="details">

                                    <div class="mb-3">
                                        <label class="form-label" for="full_name">Your name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name"
                                               value="<?php echo e($full_name); ?>"
                                               required maxlength="160" autocomplete="name">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="email">Email</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?php echo e($email); ?>"
                                               required maxlength="190" autocomplete="email"
                                               autocapitalize="none" spellcheck="false">
                                        <div class="form-text">You sign in with this address.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="phone">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone"
                                               value="<?php echo e($phone); ?>"
                                               maxlength="40" autocomplete="tel">
                                        <div class="form-text">Optional.</div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label" for="born">Birthday</label>
                                        <input type="date" class="form-control" id="born" name="born"
                                               value="<?php echo e($born); ?>"
                                               max="<?php echo date('Y-m-d'); ?>">
                                        <div class="form-text">
                                            Only the day and the month are ever shown - the church
                                            greets everybody who celebrates in the month. The year
                                            stays with you.
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
                                        Save my details
                                    </button>
                                </form>
                            <?php endif; ?>

                            <h2 class="h5 mt-4 mb-3">Change your password</h2>

                            <form method="post" action="<?php echo url('dashboard/profile'); ?>" novalidate>
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="form" value="password">

                                <div class="mb-3">
                                    <label class="form-label" for="current_password">Current password</label>
                                    <input type="password" class="form-control" id="current_password"
                                           name="current_password" required autocomplete="current-password">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="new_password">New password</label>
                                    <input type="password" class="form-control" id="new_password"
                                           name="new_password" required autocomplete="new-password">
                                    <div class="form-text">
                                        At least <?php echo (int) MIN_PASSWORD_LENGTH; ?> characters, with a letter
                                        and a number in it.
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label" for="new_password_confirm">New password again</label>
                                    <input type="password" class="form-control" id="new_password_confirm"
                                           name="new_password_confirm" required autocomplete="new-password">
                                </div>

                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fa-solid fa-key me-1" aria-hidden="true"></i>
                                    Change my password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <p class="mt-4 mb-0">
                <a href="<?php echo member_url(); ?>">
                    <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
                    Back to my account
                </a>
            </p>
        </div>
    </section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; ?>
