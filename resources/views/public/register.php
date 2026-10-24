<?php
/**
 * BUBAPC - Church member registration
 *
 * Lets somebody who attends the church sign themselves up. They get an account
 * they can sign in with, and a page of their own at /dashboard where they can
 * keep their name, email and phone number up to date.
 *
 * A member is NOT a website manager. The account is written with
 * role = 'member', and the admin gate turns members away from every page in /dashboard/admin/.
 *
 * A member registration makes a row in people with category 'member' and
 * status 0, so it exists as an account but belongs to no page on the public
 * site. An administrator can see it on the People screen and give it a
 * directory page if the person should have one.
 *
 * A member and an alumnus get an account that works straight away, because
 * neither can do anything except read and edit their own details. A leader is
 * different: the same account is made, but it waits for an administrator to
 * switch it on before it can sign in, because a leader may change the content.
 *
 * The daily devotional box is unticked and stays that way. Mail is only sent to
 * somebody who asked for it, so the box is a separate and deliberate tick and
 * never a default.
 */

require_once __DIR__ . '/../../../app/init.php';

$errors = [];
$full_name = '';
$email = '';
$phone = '';
$standing = 'member';
$wants_devotional = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $full_name = post_text('full_name');
    $email = post_text('email');
    $phone = post_text('phone');
    $standing = post_text('standing');
    $password = (string) ($_POST['password'] ?? '');
    $password_confirm = (string) ($_POST['password_confirm'] ?? '');
    $wants_devotional = post_checkbox('devotional');

    if (is_empty_value($full_name)) {
        $errors[] = 'Please tell us your name.';
    } elseif (mb_strlen($full_name) > 160) {
        $errors[] = 'That name is too long. Please use 160 characters or fewer.';
    }

    if (!is_valid_email($email)) {
        $errors[] = 'Please type a real email address, for example name@example.com.';
    }

    if (is_empty_value($phone)) {
        $errors[] = 'Please give us a phone number, so we can reach you.';
    } elseif (mb_strlen($phone) > 40) {
        $errors[] = 'That phone number is too long.';
    }

    /* The password is checked before the address is looked up, so somebody
       probing for which addresses are registered is not told either. */
    $password_error = password_problem($password);
    if ($password_error !== '') {
        $errors[] = $password_error;
    } elseif ($password !== $password_confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!array_key_exists($standing, standing_labels())) {
        $errors[] = 'Please choose how you are registering.';
    }

    /* An address that is already spoken for by an account, whether a member
       or an authorised member, is turned away. A directory-only person may share
       the address, so the check is limited to rows that carry a sign-in. */
    if (!$errors) {
        $statement = $conn->prepare(
            "SELECT id FROM people WHERE password IS NOT NULL AND (username = ? OR email = ?) LIMIT 1"
        );
        $statement->bind_param('ss', $email, $email);
        $statement->execute();
        $already_there = (bool) $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($already_there) {
            $errors[] = 'There is already an account with that email address. Try signing in, or use a different address.';
        }
    }

    if (!$errors) {
        $full_name = mb_substr($full_name, 0, 160);
        $email = mb_substr($email, 0, 190);
        $phone = mb_substr($phone, 0, 40);

        /* The sign-in name is copied from the address, so a member signs in
           with the email they typed rather than having to invent a second
           name. It is unique, and the address has just been checked, so the
           two cannot collide. */
        /* A leader waits for an administrator, so their account is made
           switched off and every other registration works at once. The row
           also belongs to no directory page, so it carries category 'member'
           and status 0 and never shows on the public site. */
        $role = $standing === 'leader' ? 'leader' : 'member';
        $active = $standing === 'leader' ? 0 : 1;
        $slug = slug($conn, 'people', $full_name);

        /* The hash is put in a variable of its own rather than written straight
           into bind_param(). bind_param() takes what it is given by reference,
           and handing it the result of a function call instead of a variable
           makes PHP print "Only variables should be passed by reference". */
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $statement = $conn->prepare(
            "INSERT INTO people (name, slug, category, email, username, password, role, standing, active, status)
             VALUES (?, ?, 'member', ?, ?, ?, ?, ?, ?, 0)"
        );
        $statement->bind_param('sssssssi', $full_name, $slug, $email, $email, $hash, $role, $standing, $active);
        $inserted = $statement->execute();
        $new_id = $statement->insert_id;
        $insert_error = $statement->error;
        $statement->close();

        if (!$inserted) {
            /* Almost always a unique key losing a race with somebody else
               registering the same address at the same moment. */
            $errors[] = 'That account could not be saved' . ($insert_error ? ' (' . $insert_error . ')' : '') . '. Please try again.';
        } else {
            /* The daily devotional tick, from the same place as the footer
               form, so a member who also asked for the devotional is on the
               list once and not twice. An address that is already there is
               left exactly as it is: ticking the box must never switch
               somebody off. */
            if ($wants_devotional) {
                $statement = $conn->prepare("INSERT IGNORE INTO subscribers (email, status) VALUES (?, 1)");
                $statement->bind_param('s', $email);
                $statement->execute();
                $statement->close();
            }

            /* A leader is not signed in. The account has been made and the
               devotional recorded, but an administrator switches it on before
               it can be used, so the person waits at the sign-in page. */
            if ($standing === 'leader') {
                flash('success', 'Thank you, ' . $full_name . '. A leader account is checked '
                    . 'by an administrator before it can be used, so please try signing in again later.');
                redirect('login');
            }

            /* Read the row back rather than using the values that were just
               inserted, so the session holds the database's own copy - the
               password hash, the id, everything sign_user_in() expects. */
            $statement = $conn->prepare("SELECT * FROM people WHERE id = ? LIMIT 1");
            $member_id = (int) $new_id;
            $statement->bind_param('i', $member_id);
            $statement->execute();
            $member = $statement->get_result()->fetch_assoc();
            $statement->close();

            /* Signing somebody in straight after they register saves typing the
               password twice. It goes through sign_user_in() rather than
               setting the session here, because that is where the fresh
               session id is. */
            sign_user_in($member);

            flash('success', 'Welcome to the church, ' . $full_name . '. Your account is ready.');
            redirect(member_url());
        }
    }
}

$page_title = 'Register';
require_once __DIR__ . '/../partials/public/header.php';
?>

    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-8 col-lg-6">
                    <div class="card border-0 shadow rounded-4">
                        <div class="card-body p-4 p-md-5">
                            <h1 class="h4 mb-3">Register</h1>
                            <p class="text-body-secondary mb-4">Register here and keep your details up to date.</p>
                            <?php form_errors($errors, 'Please check the form and try again.'); ?>
                            <form method="post" action="<?php echo url('register'); ?>" novalidate>
                                <?php echo csrf_field(); ?>

                                <div class="mb-3">
                                    <label class="form-label" for="full_name">Your name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                           value="<?php echo e($full_name); ?>"
                                           required maxlength="160" autocomplete="name" autofocus>
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
                                           required maxlength="40" autocomplete="tel">
                                    <div class="form-text">How we reach you with anything urgent.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="standing">I am registering as</label>
                                    <select class="form-select" id="standing" name="standing" required>
                                        <?php foreach (standing_labels() as $key => $label): ?>
                                            <option value="<?php echo e($key); ?>"<?php echo $standing === $key ? ' selected' : ''; ?>>
                                                <?php echo e($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        Students pick Member. A leader account is checked by an administrator
                                        before it can sign in.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="password">Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control border-end-0" id="password"
                                               name="password" required autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary px-3"
                                                data-toggle-password="#password" aria-label="Show the password">
                                            <i class="fa-regular fa-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        At least <?php echo (int) MIN_PASSWORD_LENGTH; ?> characters, with a letter
                                        and a number in it.
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label" for="password_confirm">Password again</label>
                                    <input type="password" class="form-control" id="password_confirm"
                                           name="password_confirm" required autocomplete="new-password">
                                </div>

                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" value="1" id="devotional"
                                           name="devotional" <?php echo $wants_devotional ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="devotional">
                                        Send me the daily devotional
                                    </label>
                                    <div class="form-text">
                                        One short message from God&rsquo;s Word by email each morning.
                                        Leave this unticked if you would rather not &mdash; you can be a member without it.
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fa-solid fa-user-plus me-1" aria-hidden="true"></i>
                                    Register
                                </button>
                            </form>
                        </div>
                    </div>

                    <p class="small text-body-secondary mt-4 mb-0">
                        Already registered? <a href="<?php echo url('login'); ?>">Sign in</a>.
                    </p>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../partials/public/footer.php'; ?>
