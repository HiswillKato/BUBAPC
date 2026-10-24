<?php
/**
 * BUBAPC - Admin: the list of devotionals
 *
 * The column an editor is judged on here is the date, because a devotional is
 * written for one particular day. That is why the list is sorted by date and
 * not by when it was typed.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'devotionals';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = post_text('action');
    $devotional_id = (int) ($_POST['id'] ?? 0);
    $devotional = admin_row($conn, 'devotionals', $devotional_id);

    if (!$devotional) {
        flash('error', 'That devotional is no longer here. It may already have been deleted.');
        redirect(admin_url('devotionals'));
    }

    if ($action === 'delete') {
        $statement = $conn->prepare("DELETE FROM devotionals WHERE id = ?");
        $statement->bind_param('i', $devotional_id);
        $statement->execute();
        $statement->close();

        log_action($conn, 'devotional_deleted', 'The devotional "' . $devotional['title'] . '"');
        flash('success', 'The devotional "' . $devotional['title'] . '" has been deleted.');
    } elseif ($action === 'toggle_status') {
        $new_status = $devotional['status'] === 'published' ? 'draft' : 'published';

        $statement = $conn->prepare("UPDATE devotionals SET status = ? WHERE id = ?");
        $statement->bind_param('si', $new_status, $devotional_id);
        $statement->execute();
        $statement->close();

        flash('success', $new_status === 'published'
            ? '"' . $devotional['title'] . '" is now on the website.'
            : '"' . $devotional['title'] . '" is now a draft. Nobody can see it.');
    }

    redirect(admin_url('devotionals'));
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
    $where[] = '(title LIKE ? OR message LIKE ? OR scripture_reference LIKE ?)';
    $like = '%' . $search . '%';
    $bind_types .= 'sss';
    $bind_values[] = $like;
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
    $statement = $conn->prepare("SELECT COUNT(*) FROM devotionals" . $where_sql);
    $statement->bind_param($bind_types, ...$bind_values);
    $statement->execute();
    $total_devotionals = (int) $statement->get_result()->fetch_assoc()['COUNT(*)'];
    $statement->close();
} else {
    $total_devotionals = db_count($conn, "SELECT COUNT(*) FROM devotionals" . $where_sql);
}

$per_page = 20;
$current_page = page_number();
$pagination = paginate($total_devotionals, $per_page, $current_page);

$statement = $conn->prepare(
    "SELECT * FROM devotionals" . $where_sql . " ORDER BY devotional_date DESC, id DESC LIMIT ? OFFSET ?"
);
$offset = $pagination['offset'];
$statement->bind_param($bind_types . 'ii', ...array_merge($bind_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$devotionals = [];
while ($row = $result->fetch_assoc()) {
    $devotionals[] = $row;
}
$statement->close();

$page_title = 'Devotionals';
$page_heading = 'Devotionals';
$page_actions = '<a class="btn btn-primary" href="' . admin_url('devotionals/form.php') . '">'
    . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add devotional</a>';

require_once __DIR__ . '/../../partials/admin/header.php';
?>

<?php admin_filter_bar(admin_url('devotionals'), $search, $status, ['published' => 'Published', 'draft' => 'Draft']); ?>

<?php admin_result_bar($pagination, ['q' => $search, 'status' => $status], 'devotionals'); ?>

<?php if (!$devotionals): ?>
    <?php
    empty_state(
        'fa-book-bible',
        $search !== '' || $status !== '' ? 'No devotionals match that' : 'No devotionals yet',
        $search !== '' || $status !== ''
            ? 'Try a different word, or clear the filter to see everything.'
            : 'A devotional is a short message for one day. The home page shows the one for today, and '
                . 'the devotional page keeps the whole archive.',
        '<a class="btn btn-primary" href="' . admin_url('devotionals/form.php') . '">'
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
                        <th scope="col" class="ps-3">For the day</th>
                        <th scope="col">Title</th>
                        <th scope="col">Scripture</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devotionals as $devotional): ?>
                        <?php
                        /* A reader is not interested in whether it is a
                           future date; a writer very much is. */
                        $is_future = $devotional['devotional_date'] > date('Y-m-d');
                        ?>
                        <tr>
                            <td class="ps-3 text-nowrap">
                                <?php echo format_date($devotional['devotional_date'], 'j M Y'); ?>
                                <?php if ($is_future): ?>
                                    <span class="badge rounded-pill text-bg-info ms-1">Later</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="fw-semibold d-block text-decoration-none"
                                   href="<?php echo admin_url('devotionals/form.php?id=' . (int) $devotional['id']); ?>">
                                    <?php echo e($devotional['title']); ?>
                                </a>
                            </td>
                            <td class="small text-body-secondary"><?php echo e($devotional['scripture_reference']); ?></td>
                            <td><?php echo status_badge($devotional['status']); ?></td>
                            <td class="text-end pe-3 text-nowrap">
                                <?php
                                echo admin_row_actions('devotionals', $devotional['id'], [
                                    'status' => $devotional['status'],
                                    'label' => 'the devotional "' . $devotional['title'] . '"',
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
