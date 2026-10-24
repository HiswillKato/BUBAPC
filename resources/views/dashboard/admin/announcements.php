<?php
/**
 * BUBAPC - Admin: the list of announcements
 *
 * Announcements are the short notices at the top of the home page. They are
 * ordered by the day they appear, so the newest one is always first.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../../../app/includes/admin/parts.php';

$active_module = 'announcements';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = post_text('action');
    $announcement_id = (int) ($_POST['id'] ?? 0);
    $announcement = admin_row($conn, 'announcements', $announcement_id);

    if (!$announcement) {
        flash('error', 'That announcement is no longer here. It may already have been deleted.');
        redirect(admin_url('announcements'));
    }

    if ($action === 'delete') {
        $statement = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $statement->bind_param('i', $announcement_id);
        $statement->execute();
        $statement->close();

        log_action($conn, 'announcement_deleted', 'The announcement "' . $announcement['title'] . '"');
        flash('success', 'The announcement "' . $announcement['title'] . '" has been deleted.');
    } elseif ($action === 'toggle_status') {
        $new_status = $announcement['status'] === 'published' ? 'draft' : 'published';

        $statement = $conn->prepare("UPDATE announcements SET status = ? WHERE id = ?");
        $statement->bind_param('si', $new_status, $announcement_id);
        $statement->execute();
        $statement->close();

        flash('success', $new_status === 'published'
            ? '"' . $announcement['title'] . '" is now on the website.'
            : '"' . $announcement['title'] . '" is now a draft. Nobody can see it.');
    }

    redirect(admin_url('announcements'));
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
if (!in_array($status, ['published', 'draft'], true)) {
    $status = '';
}

$where = [];
$bind_types = '';
$bind_values = [];

if ($search !== '') {
    $where[] = '(title LIKE ? OR content LIKE ?)';
    $like = '%' . $search . '%';
    $bind_types .= 'ss';
    $bind_values[] = $like;
    $bind_values[] = $like;
}
if ($status !== '') {
    $where[] = 'status = ?';
    $bind_types .= 's';
    $bind_values[] = $status;
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

if ($bind_values) {
    $statement = $conn->prepare("SELECT COUNT(*) FROM announcements" . $where_sql);
    $statement->bind_param($bind_types, ...$bind_values);
    $statement->execute();
    $total_announcements = (int) $statement->get_result()->fetch_assoc()['COUNT(*)'];
    $statement->close();
} else {
    $total_announcements = db_count($conn, "SELECT COUNT(*) FROM announcements" . $where_sql);
}

$per_page = 20;
$current_page = page_number();
$pagination = paginate($total_announcements, $per_page, $current_page);

$statement = $conn->prepare(
    "SELECT * FROM announcements" . $where_sql . " ORDER BY publish_date DESC, id DESC LIMIT ? OFFSET ?"
);
$offset = $pagination['offset'];
$statement->bind_param($bind_types . 'ii', ...array_merge($bind_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$announcements = [];
while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}
$statement->close();

$page_title = 'Announcements';
$page_heading = 'Announcements';
$page_actions = '<a class="btn btn-primary" href="' . admin_url('announcements/form.php') . '">'
    . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add announcement</a>';

require_once __DIR__ . '/../../../../app/includes/admin/header.php';
?>

<?php admin_filter_bar(admin_url('announcements'), $search, $status, ['published' => 'Published', 'draft' => 'Draft']); ?>

<?php admin_result_bar($pagination, ['q' => $search, 'status' => $status], 'announcements'); ?>

<?php if (!$announcements): ?>
    <?php
    empty_state(
        'fa-bullhorn',
        $search !== '' || $status !== '' ? 'No announcements match that' : 'No announcements yet',
        $search !== '' || $status !== ''
            ? 'Try a different word, or clear the filter to see everything.'
            : 'Announcements are the short notices at the top of the home page: registration, a vacancy, '
                . 'a change of time. They stay there until you delete them.',
        '<a class="btn btn-primary" href="' . admin_url('announcements/form.php') . '">'
        . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Write the first one</a>',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" class="ps-3">Appears on</th>
                        <th scope="col">Notice</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $announcement): ?>
                        <tr>
                            <td class="ps-3 text-nowrap small"><?php echo format_date($announcement['publish_date']); ?></td>
                            <td>
                                <a class="fw-semibold d-block text-decoration-none"
                                   href="<?php echo admin_url('announcements/form.php?id=' . (int) $announcement['id']); ?>">
                                    <?php echo e($announcement['title']); ?>
                                </a>
                                <span class="small text-body-secondary"><?php echo e(excerpt($announcement['content'], 90)); ?></span>
                            </td>
                            <td><?php echo status_badge($announcement['status']); ?></td>
                            <td class="text-end pe-3 text-nowrap">
                                <?php
                                echo admin_row_actions('announcements', $announcement['id'], [
                                    'status' => $announcement['status'],
                                    'label' => 'the announcement "' . $announcement['title'] . '"',
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

<?php require_once __DIR__ . '/../../../../app/includes/admin/footer.php'; ?>
