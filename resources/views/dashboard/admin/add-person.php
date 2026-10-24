<?php
/**
 * BUBAPC - Admin: add or change one person
 *
 * One page for one person: the group decides which page they appear on, and
 * the login part (administrators only) decides whether they can sign in.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../../../app/includes/admin/parts.php';

$active_module = 'people';

$person_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$person = $person_id ? admin_row($conn, 'people', $person_id) : [];

$is_new = $person_id < 1;

$values = array_merge([
    'name'           => '',
    'slug'           => '',
    'category'       => 'student',
    'position'       => '',
    'phone'          => '',
    'email'          => '',
    'family_id'      => 0,
    'department_id'  => 0,
    'joined_year'    => '',
    'graduated_year' => '',
    'bio'            => '',
    'testimony'      => '',
    'image'          => '',
    'display_order'  => 0,
    'status'         => 1,
], $person);

/* The row names its family and department as ids in `family` and `department`;
   the form and the checks below call them family_id and department_id. */
$values['family_id'] = (int) ($person['family'] ?? 0);
$values['department_id'] = (int) ($person['department'] ?? 0);

/* Giving or changing a sign-in is an administrator's job. An editor or a
   church leader opens this form to keep the directory up to date, and must not
   be able to hand out rights with it, so without this the login card would be
   a way to make yourself an administrator. */
$can_manage_login = is_admin();

/* The sign-in card's starting state. A row with a password already has an
   account; it opens on its own role and on the switch it was left at, so an
   account that is switched off opens with the box empty and can be switched
   back on by ticking it. A person in the Church Admins group opens on "Church
   admin", because that is where their rights come from. */
$has_account = ($person['password'] ?? null) !== null;
$values['login'] = ($has_account && (int) ($person['active'] ?? 0) === 1) ? 1 : 0;
$values['login_role'] = ($values['category'] === 'church_admin')
    ? 'church_admin'
    : ($person['role'] ?? 'member');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values['name'] = post_text('name');
    $values['position'] = post_text('position');
    $values['email'] = post_text('email');
    $values['status'] = post_checkbox('status') ? 1 : 0;

    admin_check_required($values['name'], 'The name', $errors);

    /* The group is looked up in the church's own list, so a value that did
       not come from the drop down is refused rather than saved. */
    $chosen_category = post_text('category');
    if (!array_key_exists($chosen_category, people_categories())) {
        $errors[] = 'Please choose a group from the list.';
    } else {
        $values['category'] = $chosen_category;
    }

    if ($values['email'] !== '' && !is_valid_email($values['email'])) {
        $errors[] = 'The email address does not look like an email address.';
    }

    /* The sign-in switch, the role and the password. */
    $login_on = post_checkbox('login');
    $values['login'] = $login_on;
    $login_role = post_text('login_role');
    if (!in_array($login_role, ['church_admin', 'admin', 'editor', 'pastor', 'elder', 'minister', 'leader', 'member'], true)) {
        $login_role = 'member';
    }
    $values['login_role'] = $login_role;

    /* "Church admin" is not a sign-in role of its own: the full rights live in
       the Church Admins group, and this is a short way of saying "put them in
       it". Choosing it moves the person into that group, so the group box and
       the sign-in box on this page cannot end up disagreeing. */
    if ($login_role === 'church_admin') {
        $values['category'] = 'church_admin';
    } elseif ($login_role === 'pastor') {
        $values['category'] = 'pastor';
    } elseif ($login_role === 'elder') {
        $values['category'] = 'elder';
    } elseif ($login_role === 'minister') {
        $values['category'] = 'minister';
    }

    /* The row keeps an ordinary role even for a church admin, because the
       rights come from the group. The group is what is_admin() reads, so a
       church admin keeps them only while they stay in the group. */
    $account_role = $login_role === 'church_admin' ? 'member' : $login_role;
    $login_password = (string) ($_POST['login_password'] ?? '');

    if ($can_manage_login && $login_on) {
        /* The address is what the person signs in with, so it has to be a real
           one before an account can be made from it. */
        if (!is_valid_email($values['email'])) {
            $errors[] = 'Give this person a real email address first: it is what they will sign in with.';
        }

        /* A row with no password yet needs one now. A row that already has a
           sign-in may keep the password it has, so an empty box means "leave
           it". */
        if ($login_password !== '') {
            $problem = password_problem($login_password);
            if ($problem !== '') {
                $errors[] = $problem;
            }
        } elseif (!$has_account) {
            $errors[] = 'Give this person a password: it is needed the first time they sign in.';
        }

        /* The address must not already belong to a different sign in. This
           person's own row is ignored, so saving the form again is fine. */
        if (is_valid_email($values['email'])) {
            $statement = $conn->prepare(
                "SELECT id FROM people WHERE password IS NOT NULL AND (username = ? OR email = ?) AND id <> ? LIMIT 1"
            );
            $statement->bind_param('ssi', $values['email'], $values['email'], $person_id);
            $statement->execute();
            $email_taken = (bool) $statement->get_result()->fetch_assoc();
            $statement->close();

            if ($email_taken) {
                $errors[] = 'That email address already belongs to another sign in. Use a different address.';
            }
        }
    }

    if (!$errors) {
        /* The address is made from the name and never typed, so it cannot
           arrive with a capital or a space in it. slug() adds a number when
           another person already has this one. On an edit the address that is
           already saved is kept, because somebody's page may already have been
           printed and shared. */
        $values['slug'] = $is_new
            ? slug($conn, 'people', $values['name'])
            : $person['slug'];

        /* A photograph is optional. When it is missing the public pages draw
           the person's initials instead, so an empty answer here is a normal
           answer and not a half-finished form. A problem with the file never
           stops the rest of the person being saved. */
        $image = save_image(
            $_FILES['image'] ?? null,
            post_checkbox('remove_image'),
            'people',
            $person['image'] ?? ''
        );
        $values['image'] = $image['path'];
        if ($image['error'] !== '') {
            flash('error', 'The person was saved, but the picture was not: ' . $image['error']);
        }

        /* The account columns, all on this same row. Switching the box off
           never deletes the sign-in: a row that already had one keeps its
           password and role and is only switched off, so an admin can bring it
           back by ticking the box again. A row that never had one is left with
           none. A member signs in with the address they typed, so the sign-in
           name follows it; an account row keeps the username it already has. */
        if ($can_manage_login && $login_on) {
            /* A row that already signs in keeps its name; a new account signs
               in with the email address, which is the same string, so the
               address in the box above is what it is made from. */
            $sign_in_name = ($person['username'] ?? '') !== ''
                ? $person['username']
                : mb_substr($values['email'], 0, 190);
            $sign_in_role = $account_role;
            $sign_in_active = 1;
            $sign_in_password = $login_password !== ''
                ? password_hash($login_password, PASSWORD_DEFAULT)
                : ($person['password'] ?? null);
        } elseif ($can_manage_login) {
            /* The switch was left off. An account that was already there is
               switched off but kept, so an administrator can turn it back on;
               a person who never had one stays without one. */
            $sign_in_name = $person['username'] ?? null;
            $sign_in_role = $person['role'] ?? null;
            $sign_in_active = $has_account ? 0 : (int) ($person['active'] ?? 1);
            $sign_in_password = $person['password'] ?? null;
        } else {
            /* An editor or a church leader may not touch a sign-in, so whatever
               the row already had is left exactly as it was. */
            $sign_in_name = $person['username'] ?? null;
            $sign_in_role = $person['role'] ?? null;
            $sign_in_active = (int) ($person['active'] ?? 1);
            $sign_in_password = $person['password'] ?? null;
        }

        if ($is_new) {
            /* The two year columns are written as NULLIF(?, 0) rather than a
               plain placeholder. bind_param() hands MySQL a 0 for an empty
               value, and 0 is not a year anybody joined in - NULLIF turns it
               back into an empty column, which is what an unfilled box means. */
            $statement = $conn->prepare(
                "INSERT INTO people
                    (name, slug, category, position, phone, email, username, password, role, active,
                     family, department, joined_year, graduated_year, bio, testimony, image, display_order, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, ?, ?)"
            );
            $statement->bind_param(
                'sssssssssiiiiisssii',
                $values['name'],
                $values['slug'],
                $values['category'],
                $values['position'],
                $values['phone'],
                $values['email'],
                $sign_in_name,
                $sign_in_password,
                $sign_in_role,
                $sign_in_active,
                $values['family_id'],
                $values['department_id'],
                $values['joined_year'],
                $values['graduated_year'],
                $values['bio'],
                $values['testimony'],
                $values['image'],
                $values['display_order'],
                $values['status']
            );
            $statement->execute();
            $person_id = (int) db_last_id($conn);
            $statement->close();

            log_action($conn, 'person_created', $values['name']);
            flash('success', $values['name'] . ' has been added to the website.');
        } else {
            $statement = $conn->prepare(
                "UPDATE people
                 SET name = ?, slug = ?, category = ?, position = ?, phone = ?, email = ?,
                     username = ?, password = ?, role = ?, active = ?,
                     family = ?, department = ?, joined_year = NULLIF(?, 0), graduated_year = NULLIF(?, 0),
                     bio = ?, testimony = ?, image = ?, display_order = ?, status = ?
                 WHERE id = ?"
            );
            $statement->bind_param(
                'sssssssssiiiiisssiii',
                $values['name'],
                $values['slug'],
                $values['category'],
                $values['position'],
                $values['phone'],
                $values['email'],
                $sign_in_name,
                $sign_in_password,
                $sign_in_role,
                $sign_in_active,
                $values['family_id'],
                $values['department_id'],
                $values['joined_year'],
                $values['graduated_year'],
                $values['bio'],
                $values['testimony'],
                $values['image'],
                $values['display_order'],
                $values['status'],
                $person_id
            );
            $statement->execute();
            $statement->close();

            log_action($conn, 'person_updated', $values['name']);
            flash('success', $values['name'] . ' has been saved.');
        }

        redirect(admin_url('people'));
    }
}

$page_title = $is_new ? 'Add a person' : 'Edit person';
$page_heading = $is_new ? 'Add a person' : 'Edit person';
$page_actions = '<a class="btn btn-outline-secondary" href="' . admin_url('people') . '">'
    . '<i class="fa-solid fa-list me-1" aria-hidden="true"></i>Back to the list</a>';

require_once __DIR__ . '/../../../../app/includes/admin/header.php';
?>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">

            <?php admin_errors($errors); ?>

            <form method="post" enctype="multipart/form-data" novalidate>
                <?php echo csrf_field(); ?>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <?php
                        admin_text_field('name', 'Full name', $values['name'], '',
                            'Pastor Ruth Nakato');
                        admin_select('category', 'Group', people_categories(), $values['category'],
                            'Decides which page of the website the person is listed on.');
                        admin_text_field('position', 'Title', $values['position'], '',
                            'e.g. Bishop, Pastor');
                        admin_text_field('email', 'Email', $values['email'], '', 'name@example.com');
                        admin_image_field('image', 'Photograph', $values['image'],
                            'Optional. Initials are shown when there is none.');
                        admin_checkbox('status', 'Show this person on the website', (int) $values['status'] === 1);
                        ?>

                        <?php if ($can_manage_login): ?>
                            <?php
                            admin_checkbox('login', 'Let this person sign in', (int) $values['login'] === 1);

                            admin_select('login_role', 'Role of the person', [
                                'church_admin' => 'Church admin - full access',
                                'pastor'       => 'Pastor - full access',
                                'elder'        => 'Elder - full access',
                                'minister'     => 'Minister - full access',
                                'editor'       => 'Editor - can change all the content',
                                'leader'       => 'Leader - can manage the groups they lead',
                                'admin'        => 'Website admin - full access',
                                'member'       => 'Member - can only edit their own details',
                            ], $values['login_role'],
                                'Select the role for this person.');

                            if (in_array($values['login_role'], ['church_admin', 'pastor', 'elder', 'minister', 'admin'])) {
                                echo '<p class="small text-body-secondary">This role has full access to all content and the website.</p>';
                            } elseif ($values['login_role'] === 'editor') {
                                echo '<p class="small text-body-secondary">Editors can access and manage all published content.</p>';
                            } elseif ($values['login_role'] === 'leader') {
                                echo '<p class="small text-body-secondary">Leaders can manage the groups they lead.</p>';
                            }
                            ?>

                            <div class="mb-2">
                                <label class="form-label" for="login_password">
                                    <?php echo $has_account ? 'New password' : 'Password'; ?>
                                </label>
                                <input type="password" class="form-control" id="login_password" name="login_password"
                                       minlength="<?php echo (int) MIN_PASSWORD_LENGTH; ?>" autocomplete="new-password">
                                <div class="form-text">
                                    At least <?php echo (int) MIN_PASSWORD_LENGTH; ?> characters, with a letter and a number.
                                    <?php echo $has_account
                                        ? 'Leave empty to keep the saved password.'
                                        : 'Needed for the first sign in.'; ?>
                                </div>
                            </div>

                            <?php if ($has_account): ?>
                                <p class="small text-body-secondary mb-0">
                                    Signs in as <strong><?php echo e($person['username']); ?></strong>
                                    (<?php echo (int) ($person['active'] ?? 0) === 1 ? 'on' : 'off'; ?>).
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php admin_form_buttons(admin_url('people'), $is_new ? 'Add person' : 'Save changes'); ?>
                    </div>
                </div>
            </form>

            <?php if (!$is_new): ?>
                <div class="card border-danger rounded-4 mt-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Remove this person</h2>
                        <p class="small text-body-secondary mb-3">
                            They come off the website at once and their picture is deleted. To take them
                            off the website but keep the record, untick the switch on the form instead.
                        </p>
                        <?php echo admin_delete_button('people', $person_id, $person['name']); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../../../app/includes/admin/footer.php'; ?>
