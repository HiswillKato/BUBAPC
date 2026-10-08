<?php
/**
 * BUBAPC - Admin: add or change one person who can sign in
 *
 * The password box is empty when the form opens for somebody who is already
 * here, because the old password is never sent to the browser. Leaving the box
 * empty keeps the password that is already saved.
 */

require_once __DIR__ . '/../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../app/includes/admin/parts.php';
require_admin();

$active_module = 'users';

$user_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$other = $user_id ? admin_row($conn, 'users', $user_id) : [];

if ($user_id && !$other) {
    flash('error', 'That account is no longer here.');
    redirect(admin_url('users'));
}

$is_new = $user_id === 0;

$values = array_merge([
    'username' => '',
    'full_name' => '',
    'email' => '',
    'role' => 'editor',
    'can_manage_users' => 0,
    'status' => 1,
], $other);

$errors = [];
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values['username'] = post_text('username');
    $values['full_name'] = post_text('full_name');
    $values['email'] = post_text('email');
    /* The four roles are spelled out here rather than read from a list in
       app/auth.php, because this is the one place a person chooses one. If
       staff_roles() ever grows a fifth role, it is added to both. */
    $roles = ['admin', 'editor', 'church_admin', 'member'];
    $values['role'] = in_array($_POST['role'] ?? '', $roles, true)
        ? $_POST['role']
        : 'editor';

    /* The extra box is only worth reading for a church admin, so it is forced
       to 0 for every other role here rather than being left to the database to
       store. That way a member with the box ticked by a mistake cannot be
       promoted by ticking it. */
    $values['can_manage_users'] = $values['role'] === 'church_admin' && post_checkbox('can_manage_users') ? 1 : 0;
    $values['status'] = post_checkbox('status') ? 1 : 0;
    $password = (string) ($_POST['password'] ?? '');

    admin_check_required($values['full_name'], 'The full name', $errors);
    admin_check_required($values['username'], 'The username', $errors);
    admin_check_required($values['email'], 'The email address', $errors);

    /* A username is typed by hand at the sign in page, so spaces and odd
       characters only cause confusion later. */
    if ($values['username'] !== '' && preg_match('/^[A-Za-z0-9._-]{3,64}$/', $values['username']) !== 1) {
        $errors[] = 'The username may only use letters, numbers, dots, dashes and underscores, '
            . 'and must be at least three characters.';
    }

    if (!is_empty_value($values['email']) && !is_valid_email($values['email'])) {
        $errors[] = 'That email address does not look right.';
    }

    /* The username and the address both have to be free. The check is done
       here so the person is told plainly, rather than the database refusing
       the save and the form looking as if nothing happened. */
    foreach (['username' => $values['username'], 'email' => $values['email']] as $column => $wanted) {
        if (is_empty_value($wanted)) {
            continue;
        }

        $statement = $conn->prepare("SELECT id FROM users WHERE `$column` = ? AND id <> ? LIMIT 1");
        $statement->bind_param('si', $wanted, $user_id);
        $statement->execute();
        $taken = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($taken) {
            $errors[] = $column === 'username'
                ? 'Somebody already uses that username.'
                : 'Somebody already uses that email address.';
        }
    }

    if ($is_new || $password !== '') {
        $problem = password_problem($password);
        if ($problem !== '') {
            $errors[] = $problem;
        }
    }

    /* Nobody may take their own admin rights away, because that would leave
       the website with no way back in through the browser. The test is on what
       the account would be able to reach afterwards, not on the word in the
       box, so a church admin can still correct their own name and address
       while the box is ticked. */
    $me = current_user();
    if (!$is_new && (int) $other['id'] === (int) $me['id']) {
        $would_still_be_admin = $values['role'] === 'admin'
            || ($values['role'] === 'church_admin' && $values['can_manage_users'] === 1);

        if (!$would_still_be_admin) {
            $errors[] = 'You cannot take your own admin rights away. Ask another admin to do it, '
                . 'or leave your own role and the extra box as they are.';
        }
        if ($values['status'] === 0) {
            $errors[] = 'You cannot switch off your own account.';
        }
    }
    if (!$errors) {
        if ($is_new) {
            $statement = $conn->prepare(
                "INSERT INTO users (username, full_name, email, password, role, can_manage_users, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $statement->bind_param(
                'sssssii',
                $values['username'],
                $values['full_name'],
                $values['email'],
                $hash,
                $values['role'],
                $values['can_manage_users'],
                $values['status']
            );
            $statement->execute();
            $user_id = (int) db_last_id($conn);
            $statement->close();

            log_action($conn, 'user_created', $values['full_name']);
            flash('success', $values['full_name'] . ' can now sign in.');
        } else {
            if ($password !== '') {
                $statement = $conn->prepare(
                    "UPDATE users SET username = ?, full_name = ?, email = ?, password = ?,
                                      role = ?, can_manage_users = ?, status = ?
                     WHERE id = ?"
                );
                $statement->bind_param(
                    'sssssiii',
                    $values['username'],
                    $values['full_name'],
                    $values['email'],
                    password_hash($password, PASSWORD_DEFAULT),
                    $values['role'],
                    $values['can_manage_users'],
                    $values['status'],
                    $user_id
                );
            } else {
                $statement = $conn->prepare(
                    "UPDATE users SET username = ?, full_name = ?, email = ?,
                                      role = ?, can_manage_users = ?, status = ?
                     WHERE id = ?"
                );
                $statement->bind_param(
                    'ssssiii',
                    $values['username'],
                    $values['full_name'],
                    $values['email'],
                    $values['role'],
                    $values['can_manage_users'],
                    $values['status'],
                    $user_id
                );
            }
            $statement->execute();
            $statement->close();

            log_action($conn, 'user_updated', $values['full_name']);

            /* What a person may do is read from the copy of their row that was
               put in the session when they signed in, so a role or a tick that
               has just changed does not reach them until they sign in again.
               Your own copy is the one exception: it is dropped here so the
               change you have just made to yourself is true on the next page,
               which is what stops a person who unticks their own box from
               being shut out of the panel they are standing in. */
            $signed_in_id = (int) current_user()['id'];
            if (!$is_new && (int) $user_id === $signed_in_id) {
                unset($_SESSION['user_data']);
                flash('success', $values['full_name'] . ' has been saved.');
            } else {
                $notice = $values['role'] === 'member'
                    ? ''
                    : ' They get the new rights the next time they sign in.';
                flash('success', $values['full_name'] . ' has been saved.' . $notice);
            }
        }

        redirect(admin_url('users'));
    }
}

$page_title = $is_new ? 'Add a person' : 'Edit a person';
$page_heading = $is_new ? 'Add a person' : 'Edit a person';
$page_actions = '<a class="btn btn-outline-secondary" href="' . admin_url('users') . '">'
    . '<i class="fa-solid fa-list me-1" aria-hidden="true"></i>Back to the list</a>';

require_once __DIR__ . '/../../app/includes/admin/header.php';
?>

    <div class="row g-4">
        <div class="col-lg-8">

            <?php admin_errors($errors); ?>

            <form method="post" novalidate autocomplete="off">
                <?php echo csrf_field(); ?>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 text-uppercase text-body-secondary mb-3">Who they are</h2>
                        <?php
                        admin_text_field('full_name', 'Full name', $values['full_name'], '', 'Ruth Nakato');
                        admin_text_field('username', 'Username', $values['username'],
                            'What they type at the sign in page. Letters, numbers, dots, dashes and underscores.', 'ruth');
                        admin_text_field('email', 'Email address', $values['email'], '', 'ruth@example.com');
                        ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 text-uppercase text-body-secondary mb-3">Signing in</h2>
                        <div class="mb-3">
                            <label class="form-label" for="password">
                                <?php echo $is_new ? 'Password' : 'New password'; ?>
                            </label>
                            <input type="password" class="form-control" id="password" name="password"
                                   minlength="<?php echo (int) MIN_PASSWORD_LENGTH; ?>"
                                   autocomplete="new-password" <?php echo $is_new ? 'required' : '' ?>>
                            <div class="form-text">
                                At least <?php echo (int) MIN_PASSWORD_LENGTH; ?> characters, with a capital
                                letter, a number and a symbol.
                                <?php if (!$is_new): ?>
                                    <strong>Leave this empty to keep the password that is already saved.</strong>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php
                        /* The role names come from user_role_label() rather than
                           being typed again here, because this drop down and the
                           badge on the list and the sidebar all have to call the
                           same account by the same words. The part after the dash
                           is the one thing that is not a name. */
                        admin_select('role', 'What they can do', [
                            'editor'       => user_role_label('editor') . ' - can change all the content',
                            'church_admin' => user_role_label('church_admin') . ' - can change all the content',
                            'admin'        => user_role_label('admin') . ' - can also do the users and the settings',
                            'member'       => user_role_label('member') . ' - can only edit their own details',
                        ], $values['role']);

                        /* The extra box is drawn only for a church administrator,
                           because that is the only role it does anything for.
                           is_admin() is the only thing that reads the column, and
                           it reads it only for this role, so the two cannot
                           disagree. */
                        if ($values['role'] === 'church_admin'):
                            ?>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="can_manage_users" name="can_manage_users" value="1"
                                        <?php echo (int) $values['can_manage_users'] === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="can_manage_users">
                                        This church administrator can also do the users and the settings
                                    </label>
                                </div>
                                <div class="form-text">
                                    Leave this off and they can add and change the church's writing but
                                    cannot open the users or the settings. Tick it for the one person the
                                    church trusts to hand out and take back accounts.
                                </div>
                            </div>
                            <?php
                        endif;

                        admin_checkbox('status', 'This person can sign in', (int) $values['status'] === 1,
                            'Switch it off to lock somebody out without losing their work.');
                        ?>
                    </div>
                </div>

                <?php admin_form_buttons(admin_url('users'), $is_new ? 'Add person' : 'Save changes'); ?>
            </form>
        </div>

        <div class="col-lg-4">
            <?php if (!$is_new): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-3">About this account</h2>
                        <p class="small text-body-secondary mb-0">
                            Added <?php echo e(ago($other['created'])); ?>.
                            <?php if ($other['login'] === null): ?>
                                Has never signed in.
                            <?php else: ?>
                                Last signed in <?php echo e(ago($other['login'])); ?>.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if ((int) $other['id'] !== (int) current_user()['id']): ?>
                    <div class="card border-danger rounded-4">
                        <div class="card-body p-4">
                            <h2 class="h6 mb-2">Delete this account</h2>
                            <p class="small text-body-secondary">
                                They lose their sign in at once. Anything they wrote stays on the website.
                            </p>
                            <?php echo admin_delete_button('users', $user_id, 'the account for ' . $other['full_name']); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        <i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>
                        This is your own account. You can change the details here, but you cannot
                        lock yourself out or delete yourself.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Which role?</h2>
                        <p class="small text-body-secondary mb-3">
                            Give an editor to anybody who writes the devotionals, adds events and types
                            messages out.
                        </p>
                        <p class="small text-body-secondary mb-3">
                            A <strong>church administrator</strong> is somebody the church itself made an
                            administrator, such as a secretary or a treasurer. They can add and change all
                            the content, and one extra box raises that person to the same reach as a
                            website administrator.
                        </p>
                        <p class="small text-body-secondary mb-3">
                            A church member is somebody who signed themselves up on register.php. They can
                            sign in and change their own name, email, phone and password, and they cannot
                            reach the admin panel at all. Only make somebody a member by hand if they
                            cannot use the registration form themselves.
                        </p>
                        <p class="small text-body-secondary mb-0">
                            Keep the <strong>website administrator</strong> role to yourself or to one other
                            trusted person. Somebody in that role can delete any account, including yours.
                        </p>
                        <p class="small text-body-secondary mb-0 mt-3">
                            A church administrator here is not the same as a Church Admin on the People
                            screen. This one says what the sign-in may do on the website; the other says
                            where the person stands in the church.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../app/includes/admin/footer.php'; ?>
