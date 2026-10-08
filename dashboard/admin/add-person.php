<?php
/**
 * BUBAPC - Admin: add or change one person
 *
 * The group is the one box that matters. It is chosen from the church's own
 * list of groups, because that single choice decides which page the person
 * appears on: the leadership page, the students list, or the alumni list.
 *
 * Everything else on this form is detail. The years, the phone number and the
 * email are all optional, and nothing here is published unless it is filled in
 * and the person is switched on.
 */

require_once __DIR__ . '/../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../app/includes/admin/parts.php';

$active_module = 'people';

$person_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$person = $person_id ? admin_row($conn, 'people', $person_id) : [];

if ($person_id && !$person) {
    flash('error', 'That person is no longer here.');
    redirect(admin_url('people'));
}

$is_new = $person_id === 0;

$categories = people_categories();

$values = array_merge([
    'name' => '',
    'slug' => '',
    'category' => 'student',
    'position' => '',
    'phone' => '',
    'email' => '',
    'family_id' => 0,
    'department_id' => 0,
    'joined_year' => '',
    'graduated_year' => '',
    'bio' => '',
    'testimony' => '',
    'image' => '',
    'display_order' => 0,
    'status' => 1,
], $person);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values['name'] = post_text('name');
    $values['position'] = post_text('position');
    $values['phone'] = post_text('phone');
    $values['email'] = post_text('email');
    $values['bio'] = post_text('bio');
    $values['testimony'] = post_text('testimony');
    $values['display_order'] = (int) ($_POST['display_order'] ?? 0);
    $values['status'] = post_checkbox('status') ? 1 : 0;

    admin_check_required($values['name'], 'The name', $errors);

    /* The group is looked up in the church's own list, so a value that did
       not come from the drop down is refused rather than saved. */
    $chosen_category = post_text('category');
    if (!array_key_exists($chosen_category, $categories)) {
        $errors[] = 'Please choose a group from the list.';
    } else {
        $values['category'] = $chosen_category;
    }

    /* The family and the department are ids from a drop down. They are cast
       to a number and checked against the real table, so a value that did not
       come from the list cannot point at a row that is not there. */
    $values['family_id'] = admin_check_group_choice(
        $conn, 'families', (int) ($_POST['family_id'] ?? 0), 'The family', $errors
    );
    $values['department_id'] = admin_check_group_choice(
        $conn, 'departments', (int) ($_POST['department_id'] ?? 0), 'The department', $errors
    );

    $values['joined_year'] = admin_check_year(
        $_POST['joined_year'] ?? '', 'The year they joined', $errors
    );
    $values['graduated_year'] = admin_check_year(
        $_POST['graduated_year'] ?? '', 'The year they graduated', $errors
    );

    if ($values['email'] !== '' && !is_valid_email($values['email'])) {
        $errors[] = 'The email address does not look like an email address.';
    }

    /* Telling us the year somebody left but not which year they arrived is
       almost always a slip on the form, so it is pointed out rather than
       quietly saved. */
    if ($values['graduated_year'] !== null && $values['joined_year'] === null) {
        $errors[] = 'You have given the year they graduated but not the year they joined. '
            . 'Fill the first box in, or leave both empty.';
    }

    if (!$errors) {
        /* The address is made from the name and never typed, so it cannot arrive
           with a capital or a space in it. slug() adds a number when another
           person already has this one. On an edit the address that is already saved
           is kept, because somebody's page may already have been printed and
           shared, and a corrected spelling must not break those links. */
        $values['slug'] = $is_new
            ? slug($conn, 'people', $values['name'])
            : $person['slug'];

        /* A photograph is optional. When it is missing the public pages draw
           the person's initials instead, so an empty answer here is a normal
           answer and not a half-finished form. A problem with the file never
           stops the rest of the person being saved. */
        $image = admin_save_image(
            $_FILES['image'] ?? null,
            post_checkbox('remove_image'),
            'people',
            $person['image'] ?? ''
        );
        $values['image'] = $image['path'];
        if ($image['error'] !== '') {
            flash('error', 'The person was saved, but the picture was not: ' . $image['error']);
        }

        if ($is_new) {
            /* The two year columns are written as NULLIF(?, 0) rather than a
               plain placeholder. bind_param() hands MySQL a 0 for an empty
               value, and 0 is not a year anybody joined in - NULLIF turns it
               back into an empty column, which is what an unfilled box means. */
            $statement = $conn->prepare(
                "INSERT INTO people
                    (name, slug, category, position, phone, email, family, department,
                     joined_year, graduated_year, bio, testimony, image, display_order, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, ?, ?)"
            );
            $statement->bind_param(
                'ssssssiiiisssii',
                $values['name'],
                $values['slug'],
                $values['category'],
                $values['position'],
                $values['phone'],
                $values['email'],
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
                     family = ?, department = ?, joined_year = NULLIF(?, 0),
                     graduated_year = NULLIF(?, 0), bio = ?, testimony = ?, image = ?,
                     display_order = ?, status = ?
                 WHERE id = ?"
            );
            $statement->bind_param(
                'ssssssiiiisssiii',
                $values['name'],
                $values['slug'],
                $values['category'],
                $values['position'],
                $values['phone'],
                $values['email'],
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

    /* A form that came back with a mistake still has to show the years that
       were typed, and the helper above hands back NULL for a bad one, which
       would quietly blank the box the editor just filled in. */
    $values['joined_year'] = post_text('joined_year');
    $values['graduated_year'] = post_text('graduated_year');
}

/* The family and the department drop downs, both with "not in one" first. */
$family_options = [0 => 'Not in a family'];
$statement = $conn->prepare("SELECT id, name FROM families WHERE active = 1 ORDER BY display_order ASC, name ASC");
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $family_options[$row['id']] = $row['name'];
}
$statement->close();

$department_options = [0 => 'Not in a department'];
$statement = $conn->prepare("SELECT id, name FROM departments WHERE active = 1 ORDER BY display_order ASC, name ASC");
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $department_options[$row['id']] = $row['name'];
}
$statement->close();

$page_title = $is_new ? 'Add a person' : 'Edit person';
$page_heading = $is_new ? 'Add a person' : 'Edit person';
$page_actions = '<a class="btn btn-outline-secondary" href="' . admin_url('people') . '">'
    . '<i class="fa-solid fa-list me-1" aria-hidden="true"></i>Back to the list</a>';

require_once __DIR__ . '/../../app/includes/admin/header.php';
?>

    <div class="row g-4">
        <div class="col-lg-8">

            <?php admin_errors($errors); ?>

            <form method="post" enctype="multipart/form-data" novalidate>
                <?php echo csrf_field(); ?>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 text-uppercase text-body-secondary mb-3">Who they are</h2>
                        <?php
                        admin_text_field('name', 'Full name', $values['name'], '',
                            'Pastor Ruth Nakato', 'data-slug-source');
                        admin_select('category', 'Group', $categories, $values['category'],
                            'This one box decides which page the person appears on: the leadership page, '
                            . 'the students list, or the alumni list.');
                        admin_text_field('position', 'Job title', $values['position'], '',
                            'Bishop, Head Teacher, House Coordinator. Leave empty if there is none.');
                        ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 text-uppercase text-body-secondary mb-3">Where they belong</h2>
                        <?php
                        admin_select('family_id', 'Family', $family_options, (int) $values['family_id'],
                            'A family is a group of students who meet through the week. Leave it on '
                            . '"Not in a family" for anybody who does not have one yet.');
                        admin_select('department_id', 'Department', $department_options, (int) $values['department_id'],
                            'The ministry or team they are part of, if any.');
                        ?>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <?php admin_text_field('joined_year', 'Year they joined',
                                    (string) ($values['joined_year'] ?? ''), '', '2019'); ?>
                            </div>
                            <div class="col-sm-6">
                                <?php admin_text_field('graduated_year', 'Year they graduated',
                                    (string) ($values['graduated_year'] ?? ''),
                                    'Shown as "Class of" on the alumni page. Does not change the group.', '2023'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 text-uppercase text-body-secondary mb-3">Their story</h2>
                        <?php
                        admin_textarea('bio', 'About them', $values['bio'],
                            'A few sentences, in plain words. Leave an empty line between paragraphs.', 6);
                        admin_textarea('testimony', 'Testimony', $values['testimony'],
                            'Only shown on the alumni page, and only when the group above is Alumni. '
                            . 'Leave empty for everybody else.', 6);
                        ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 text-uppercase text-body-secondary mb-3">On the website</h2>
                        <?php
                        admin_slug_field(url('people'), $values['slug']);
                        admin_image_field('image', 'Photograph', $values['image'],
                            'Optional. Shown as a circle. With no picture, their initials are shown instead.');
                        admin_text_field('display_order', 'Order inside the group', (string) $values['display_order'],
                            'Lower numbers come first. You can also change this from the list.', '0');
                        admin_checkbox('status', 'Show this person on the website', (int) $values['status'] === 1);
                        ?>
                    </div>
                </div>

                <?php admin_form_buttons(admin_url('people'), $is_new ? 'Add person' : 'Save changes'); ?>
            </form>
        </div>

        <div class="col-lg-4">
            <?php if (!$is_new): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-3">On the website</h2>
                        <p class="small text-body-secondary mb-2">
                            Address:
                            <a class="d-block text-break" target="_blank" rel="noopener"
                               href="<?php echo url('people/' . $person['slug']); ?>">
                                <?php echo url('people/' . $person['slug']); ?>
                            </a>
                        </p>
                        <p class="small text-body-secondary mb-0">
                            <?php echo e($person['name']); ?> appears under
                            <strong><?php echo e($categories[$person['category']] ?? $person['category']); ?></strong>
                            <?php echo (int) $person['status'] === 1 ? '' : ', but is currently hidden'; ?>.
                        </p>
                    </div>
                </div>

                <div class="card border-danger rounded-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Remove this person</h2>
                        <p class="small text-body-secondary">
                            They come off the website at once and the picture is deleted. Any department
                            or family they led will go on naming them, so nothing is left saying
                            "Led by" nobody. If you only want them off the website for now, untick the
                            switch on the form instead &mdash; that keeps the record.
                        </p>
                        <?php echo admin_delete_button('people', $person_id, $person['name']); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">The groups are church roles</h2>
                        <p class="small text-body-secondary mb-0">
                            The groups in the box are where a person sits in the church. They are not the
                            roles on the website: who may sign in to this panel is decided under Users,
                            and only an admin there can change it.
                        </p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Who is on this page</h2>
                        <p class="small text-body-secondary mb-0">
                            A student is a person like any other, so they are added here and pointed at
                            their family and their department. That way the family page can list its own
                            students, and the department page can show who is in the team.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../app/includes/admin/footer.php'; ?>
