<?php
/**
 * BUBAPC - Admin: add or change one department
 *
 * The leader is chosen from the people the website already knows about, so
 * their page is linked from this department and their name follows them if it
 * is ever changed. The box underneath is the escape hatch for a leader who is
 * not on the website: not every leader wants a page of their own, and typing
 * the name in still works exactly as it did before.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'departments';

$department_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$department = $department_id ? adminrow($conn, 'departments', $department_id) : [];

if ($department_id && !$department) {
    flash('error', 'That department is no longer here.');
    redirect(adminurl('departments'));
}

$is_new = $department_id === 0;

$values = array_merge([
    'name' => '',
    'slug' => '',
    'leader_id' => 0,
    'leader' => '',
    'description' => '',
    'active' => 1,
    'display_order' => 0,
], $department);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifycsrf();

    $values['name'] = posttext('name');
    $values['leader'] = posttext('leader');
    $values['description'] = posttext('description');
    $values['display_order'] = (int) ($_POST['display_order'] ?? 0);
    $values['active'] = postcheckbox('active') ? 1 : 0;

    admincheckrequired($values['name'], 'The name of the department', $errors);
    admincheckrequired($values['description'], 'The description', $errors);

    /* A leader chosen from the list has to be a real person, or a department
       would end up pointing at nothing. 0 means "type the name below". */
    $values['leader_id'] = admincheckgroupchoice(
        $conn, 'people', (int) ($_POST['leader_id'] ?? 0), 'The leader', $errors
    );

    /* Choosing a person and typing a name as well is not an error, it is just
       the typed name being ignored. Say so rather than making the editor
       remember which one wins. */
    if ($values['leader_id'] > 0 && $values['leader'] !== '') {
        $values['leader'] = '';
    }

    if (!$errors) {
        /* The address is made from the name and never typed, so it cannot arrive
           with a capital or a space in it. slug() adds a number when another
           department already has this one. On an edit the address that is already
           saved is kept, so a corrected name does not break links to the page. */
        $values['slug'] = $is_new
            ? slug($conn, 'departments', $values['name'])
            : $department['slug'];


        if ($is_new) {
            $statement = $conn->prepare(
                "INSERT INTO departments (name, slug, leader, typed, description, active, display_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            /* The order of these letters has to match the order of the
               question marks: name, slug, leader, typed, description,
               active, display_order. */
            $statement->bind_param(
                'ssissii',
                $values['name'],
                $values['slug'],
                $values['leader_id'],
                $values['leader'],
                $values['description'],
                $values['active'],
                $values['display_order']
            );
            $statement->execute();
            $department_id = (int) dblastid($conn);
            $statement->close();

            logaction($conn, 'department_created', 'The department "' . $values['name'] . '"');
            flash('success', 'The department "' . $values['name'] . '" has been added.');
        } else {
            $statement = $conn->prepare(
                "UPDATE departments
                 SET name = ?, slug = ?, leader = ?, typed = ?, description = ?,
                     active = ?, display_order = ?
                 WHERE id = ?"
            );
            /* Same order as the insert, then the id of the row to change. */
            $statement->bind_param(
                'ssissiii',
                $values['name'],
                $values['slug'],
                $values['leader_id'],
                $values['leader'],
                $values['description'],
                $values['active'],
                $values['display_order'],
                $department_id
            );
            $statement->execute();
            $statement->close();

            logaction($conn, 'department_updated', 'The department "' . $values['name'] . '"');
            flash('success', 'The department "' . $values['name'] . '" has been saved.');
        }

        redirect(adminurl('departments'));
    }
}

$page_title = $is_new ? 'Add department' : 'Edit department';
$page_heading = $is_new ? 'Add a department' : 'Edit department';
$page_actions = '<a class="btn btn-outline-secondary" href="' . adminurl('departments') . '">'
    . '<i class="fa-solid fa-list me-1" aria-hidden="true"></i>Back to the list</a>';

require_once __DIR__ . '/../../partials/admin/header.php';
?>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">

            <?php adminerrors($errors); ?>

            <form method="post" novalidate>
                <?php echo csrffield(); ?>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <?php
                        admintextfield('name', 'Name of the department', $values['name'], '',
                            'Worship & Creative Arts');
                        adminleaderfield((int) $values['leader_id'], $values['leader'], leaderchoices($conn));
                        admintextarea('description', 'What it does, and how to join', $values['description'],
                            'Leave an empty line between paragraphs. This is the page a visitor reads when they '
                            . 'are asking where they would fit in.', 8);
                        admintextfield('display_order', 'Order on the page', (string) $values['display_order'],
                            'Lower numbers come first. You can also change this from the list.', '0');
                        admincheckbox('active', 'Show this department on the website', (int) $values['active'] === 1);
                        adminformbuttons(adminurl('departments'), $is_new ? 'Add department' : 'Save changes');
                        ?>
                    </div>
                </div>
            </form>

            <?php if (!$is_new): ?>
                <div class="card border-danger rounded-4 mt-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Delete this department</h2>
                        <p class="small text-body-secondary mb-3">
                            It comes off the website at once. If you only want it off for a while, use the
                            switch on the form instead.
                        </p>
                        <?php echo admindeletebutton('departments', $department_id, 'the department "' . $department['name'] . '"'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../partials/admin/footer.php'; ?>
