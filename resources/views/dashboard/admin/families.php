<?php
/**
 * BUBAPC - Admin: the list of families
 *
 * A family is a group of students that meets through the week. The pages are
 * near twins of the departments, because a family is to a student what a
 * department is to the church, and the two are kept in step on purpose.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'families';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = post_text('action');
    $family_id = (int) ($_POST['id'] ?? 0);
    $family = admin_row($conn, 'families', $family_id);

    if (!$family) {
        flash('error', 'That family is no longer here. It may already have been deleted.');
        redirect(admin_url('families'));
    }

    if ($action === 'delete') {
        $statement = $conn->prepare("DELETE FROM families WHERE id = ?");
        $statement->bind_param('i', $family_id);
        $statement->execute();
        $statement->close();

        log_action($conn, 'family_deleted', 'The family "' . $family['name'] . '"');
        flash('success', 'The family "' . $family['name'] . '" has been deleted.');
    } elseif ($action === 'toggle') {
        $new_active = (int) $family['active'] === 1 ? 0 : 1;

        $statement = $conn->prepare("UPDATE families SET active = ? WHERE id = ?");
        $statement->bind_param('ii', $new_active, $family_id);
        $statement->execute();
        $statement->close();

        flash('success', $new_active === 1
            ? '"' . $family['name'] . '" is now on the website.'
            : '"' . $family['name'] . '" is now hidden from the website.');
    } elseif ($action === 'set_order') {
        $new_order = max(0, min(999, (int) ($_POST['display_order'] ?? 0)));

        $statement = $conn->prepare("UPDATE families SET display_order = ? WHERE id = ?");
        $statement->bind_param('ii', $new_order, $family_id);
        $statement->execute();
        $statement->close();

        flash('success', 'The order for "' . $family['name'] . '" has been saved.');
    }

    redirect(admin_url('families'));
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$where = '';
$bind_types = '';
$bind_values = [];
if ($search !== '') {
    $where = ' WHERE families.name LIKE ? OR families.description LIKE ? OR families.typed LIKE ?';
    $bind_types = 'sss';
    $like = '%' . $search . '%';
    $bind_values = [$like, $like, $like];
}

if ($bind_values) {
    $statement = $conn->prepare("SELECT COUNT(*) FROM families" . $where);
    $statement->bind_param($bind_types, ...$bind_values);
    $statement->execute();
    $total_families = (int) $statement->get_result()->fetch_assoc()['COUNT(*)'];
    $statement->close();
} else {
    $total_families = db_count($conn, "SELECT COUNT(*) FROM families" . $where);
}

$per_page = 25;
$current_page = page_number();
$pagination = paginate($total_families, $per_page, $current_page);

$statement = $conn->prepare(
    "SELECT families.*, people.name AS linked_leader
     FROM families
     LEFT JOIN people ON people.id = families.leader"
    . $where . " ORDER BY families.display_order ASC, families.name ASC LIMIT ? OFFSET ?"
);
$offset = $pagination['offset'];
$statement->bind_param($bind_types . 'ii', ...array_merge($bind_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$families = [];
while ($row = $result->fetch_assoc()) {
    $families[] = $row;
}
$statement->close();

$page_title = 'Families';
$page_heading = 'Families';
$page_actions = '<a class="btn btn-primary" href="' . admin_url('families/form.php') . '">'
    . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add family</a>';

require_once __DIR__ . '/../../partials/admin/header.php';
?>

<?php admin_filter_bar(admin_url('families'), $search); ?>

<?php admin_result_bar($pagination, ['q' => $search], 'families'); ?>

<?php if (!$families): ?>
    <?php
    empty_state(
        'fa-users',
        $search !== '' ? 'No families match that' : 'No families yet',
        $search !== ''
            ? 'Try a different word, or clear the filter to see everything.'
            : 'The families of students that meet through the week. A first year student reads this page '
                . 'to find the one that will feel like home.',
        '<a class="btn btn-primary" href="' . admin_url('families/form.php') . '">'
        . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add the first family</a>',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" class="ps-3">Family</th>
                        <th scope="col">Led by</th>
                        <th scope="col" style="width:8rem">Order</th>
                        <th scope="col">Shown</th>
                        <th scope="col" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($families as $family): ?>
                        <tr>
                            <td class="ps-3">
                                <a class="fw-semibold d-block text-decoration-none"
                                   href="<?php echo admin_url('families/form.php?id=' . (int) $family['id']); ?>">
                                    <?php echo e($family['name']); ?>
                                </a>
                                <span class="small text-body-secondary">
                                    <?php echo e(excerpt($family['description'], 60)); ?>
                                </span>
                            </td>
                            <td class="small text-body-secondary"><?php echo e($family['linked_leader'] !== null ? $family['linked_leader'] : $family['typed']); ?></td>
                            <td><?php echo admin_order_field('families', $family['id'], $family['display_order']); ?></td>
                            <td>
                                <span class="badge rounded-pill <?php echo (int) $family['active'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                    <?php echo (int) $family['active'] === 1 ? 'Shown' : 'Hidden'; ?>
                                </span>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <?php
                                echo admin_row_actions('families', $family['id'], [
                                    'active' => $family['active'],
                                    'label' => 'the family "' . $family['name'] . '"',
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

<?php require_once __DIR__ . '/../../partials/admin/footer.php'; ?>
