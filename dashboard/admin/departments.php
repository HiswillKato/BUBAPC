<?php
/**
 * BUBAPC - Admin: the list of departments
 *
 * A department is a part of the church: worship, media, prayer, students and
 * so on. The order box on each row decides the order they are shown in, and
 * the eye button takes one off the website without deleting it.
 */

require_once __DIR__ . '/../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../app/includes/admin/parts.php';

$active_module = 'departments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = post_text('action');
    $department_id = (int) ($_POST['id'] ?? 0);
    $department = admin_row($conn, 'departments', $department_id);

    if (!$department) {
        flash('error', 'That department is no longer here. It may already have been deleted.');
        redirect(admin_url('departments'));
    }

    if ($action === 'delete') {
        $statement = $conn->prepare("DELETE FROM departments WHERE id = ?");
        $statement->bind_param('i', $department_id);
        $statement->execute();
        $statement->close();

        log_action($conn, 'department_deleted', 'The department "' . $department['name'] . '"');
        flash('success', 'The department "' . $department['name'] . '" has been deleted.');
    } elseif ($action === 'toggle') {
        $new_active = (int) $department['active'] === 1 ? 0 : 1;

        $statement = $conn->prepare("UPDATE departments SET active = ? WHERE id = ?");
        $statement->bind_param('ii', $new_active, $department_id);
        $statement->execute();
        $statement->close();

        flash('success', $new_active === 1
            ? '"' . $department['name'] . '" is now on the website.'
            : '"' . $department['name'] . '" is now hidden from the website.');
    } elseif ($action === 'set_order') {
        $new_order = max(0, min(999, (int) ($_POST['display_order'] ?? 0)));

        $statement = $conn->prepare("UPDATE departments SET display_order = ? WHERE id = ?");
        $statement->bind_param('ii', $new_order, $department_id);
        $statement->execute();
        $statement->close();

        flash('success', 'The order for "' . $department['name'] . '" has been saved.');
    }

    redirect(admin_url('departments'));
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$where = '';
$bind_types = '';
$bind_values = [];
if ($search !== '') {
    $where = ' WHERE departments.name LIKE ? OR departments.description LIKE ? OR departments.typed LIKE ?';
    $bind_types = 'sss';
    $like = '%' . $search . '%';
    $bind_values = [$like, $like, $like];
}

if ($bind_values) {
    $statement = $conn->prepare("SELECT COUNT(*) FROM departments" . $where);
    $statement->bind_param($bind_types, ...$bind_values);
    $statement->execute();
    $total_departments = (int) $statement->get_result()->fetch_assoc()['COUNT(*)'];
    $statement->close();
} else {
    $total_departments = db_count($conn, "SELECT COUNT(*) FROM departments" . $where);
}

$per_page = 25;
$current_page = page_number();
$pagination = paginate($total_departments, $per_page, $current_page);

$statement = $conn->prepare(
    "SELECT departments.*, people.name AS linked_leader
     FROM departments
     LEFT JOIN people ON people.id = departments.leader"
    . $where . " ORDER BY departments.display_order ASC, departments.name ASC LIMIT ? OFFSET ?"
);
$offset = $pagination['offset'];
$statement->bind_param($bind_types . 'ii', ...array_merge($bind_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$departments = [];
while ($row = $result->fetch_assoc()) {
    $departments[] = $row;
}
$statement->close();

$page_title = 'Departments';
$page_heading = 'Departments';
$page_actions = '<a class="btn btn-primary" href="' . admin_url('departments/form.php') . '">'
    . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add department</a>';

require_once __DIR__ . '/../../app/includes/admin/header.php';
?>

<?php admin_filter_bar(admin_url('departments'), $search); ?>

<?php admin_result_bar($pagination, ['q' => $search], 'departments'); ?>

<?php if (!$departments): ?>
    <?php
    empty_state(
        'fa-sitemap',
        $search !== '' ? 'No departments match that' : 'No departments yet',
        $search !== ''
            ? 'Try a different word, or clear the filter to see everything.'
            : 'The parts of the church, and how somebody joins each one. A visitor reads this page to '
                . 'find out where they would fit in.',
        '<a class="btn btn-primary" href="' . admin_url('departments/form.php') . '">'
        . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add the first department</a>',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" class="ps-3">Department</th>
                        <th scope="col">Led by</th>
                        <th scope="col" style="width:8rem">Order</th>
                        <th scope="col">Shown</th>
                        <th scope="col" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $department): ?>
                        <tr>
                            <td class="ps-3">
                                <a class="fw-semibold d-block text-decoration-none"
                                   href="<?php echo admin_url('departments/form.php?id=' . (int) $department['id']); ?>">
                                    <?php echo e($department['name']); ?>
                                </a>
                                <span class="small text-body-secondary">
                                    <?php echo e(excerpt($department['description'], 60)); ?>
                                </span>
                            </td>
                            <td class="small text-body-secondary"><?php echo e($department['linked_leader'] !== null ? $department['linked_leader'] : $department['typed']); ?></td>
                            <td><?php echo admin_order_field('departments', $department['id'], $department['display_order']); ?></td>
                            <td>
                                <span class="badge rounded-pill <?php echo (int) $department['active'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                    <?php echo (int) $department['active'] === 1 ? 'Shown' : 'Hidden'; ?>
                                </span>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <?php
                                echo admin_row_actions('departments', $department['id'], [
                                    'active' => $department['active'],
                                    'label' => 'the department "' . $department['name'] . '"',
                                ]);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../app/includes/admin/footer.php'; ?>
