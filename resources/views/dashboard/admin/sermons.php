<?php
/**
 * BUBAPC - Admin: the list of sermons
 *
 * A sermon can have a video. The list shows a small play symbol on the rows
 * that have one, so it is obvious at a glance which messages a visitor can
 * watch as well as read about.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'sermons';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = post_text('action');
    $sermon_id = (int) ($_POST['id'] ?? 0);
    $sermon = admin_row($conn, 'sermons', $sermon_id);

    if (!$sermon) {
        flash('error', 'That sermon is no longer here. It may already have been deleted.');
        redirect(admin_url('sermons'));
    }

    if ($action === 'delete') {
        $statement = $conn->prepare("DELETE FROM sermons WHERE id = ?");
        $statement->bind_param('i', $sermon_id);
        $statement->execute();
        $statement->close();

        log_action($conn, 'sermon_deleted', 'The sermon "' . $sermon['title'] . '"');
        flash('success', 'The sermon "' . $sermon['title'] . '" has been deleted.');
    } elseif ($action === 'toggle_status') {
        $new_status = $sermon['status'] === 'published' ? 'draft' : 'published';

        $statement = $conn->prepare("UPDATE sermons SET status = ? WHERE id = ?");
        $statement->bind_param('si', $new_status, $sermon_id);
        $statement->execute();
        $statement->close();

        flash('success', $new_status === 'published'
            ? '"' . $sermon['title'] . '" is now on the website.'
            : '"' . $sermon['title'] . '" is now a draft. Nobody can see it.');
    }

    redirect(admin_url('sermons'));
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
    $where[] = '(title LIKE ? OR preacher LIKE ? OR scripture LIKE ? OR description LIKE ?)';
    $like = '%' . $search . '%';
    $bind_types .= 'ssss';
    $bind_values[] = $like;
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
    $statement = $conn->prepare("SELECT COUNT(*) FROM sermons" . $where_sql);
    $statement->bind_param($bind_types, ...$bind_values);
    $statement->execute();
    $total_sermons = (int) $statement->get_result()->fetch_assoc()['COUNT(*)'];
    $statement->close();
} else {
    $total_sermons = db_count($conn, "SELECT COUNT(*) FROM sermons" . $where_sql);
}

$per_page = 20;
$current_page = page_number();
$pagination = paginate($total_sermons, $per_page, $current_page);

$statement = $conn->prepare(
    "SELECT * FROM sermons" . $where_sql . " ORDER BY sermon_date DESC, id DESC LIMIT ? OFFSET ?"
);
$offset = $pagination['offset'];
$statement->bind_param($bind_types . 'ii', ...array_merge($bind_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$sermons = [];
while ($row = $result->fetch_assoc()) {
    $sermons[] = $row;
}
$statement->close();

$page_title = 'Sermons';
$page_heading = 'Sermons';
$page_actions = '<a class="btn btn-primary" href="' . admin_url('sermons/form.php') . '">'
    . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add sermon</a>';

require_once __DIR__ . '/../../partials/admin/header.php';
?>

<?php admin_filter_bar(admin_url('sermons'), $search, $status, ['published' => 'Published', 'draft' => 'Draft']); ?>

<?php admin_result_bar($pagination, ['q' => $search, 'status' => $status], 'sermons'); ?>

<?php if (!$sermons): ?>
    <?php
    empty_state(
        'fa-music',
        $search !== '' || $status !== '' ? 'No sermons match that' : 'No sermons yet',
        $search !== '' || $status !== ''
            ? 'Try the preacher&rsquo;s name or part of the title.'
            : 'Add a sermon with its date, preacher and scripture. If there is a recording on YouTube, '
                . 'paste the address and it will be embedded on the page.',
        '<a class="btn btn-primary" href="' . admin_url('sermons/form.php') . '">'
        . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add the first sermon</a>',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" class="ps-3">Date</th>
                        <th scope="col">Sermon</th>
                        <th scope="col">Preacher</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sermons as $sermon): ?>
                        <tr>
                            <td class="ps-3 text-nowrap small"><?php echo format_date($sermon['sermon_date']); ?></td>
                            <td>
                                <a class="fw-semibold d-block text-decoration-none"
                                   href="<?php echo admin_url('sermons/form.php?id=' . (int) $sermon['id']); ?>">
                                    <?php echo e($sermon['title']); ?>
                                </a>
                                <span class="small text-body-secondary">
                                    <?php echo e($sermon['scripture']); ?>
                                    <?php if ($sermon['video_url'] !== ''): ?>
                                        <i class="fa-solid fa-circle-play ms-2" title="Has a video" aria-hidden="true"></i>
                                        <span class="visually-hidden">Has a video</span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="small text-body-secondary"><?php echo e($sermon['preacher']); ?></td>
                            <td><?php echo status_badge($sermon['status']); ?></td>
                            <td class="text-end pe-3 text-nowrap">
                                <?php
                                echo admin_row_actions('sermons', $sermon['id'], [
                                    'status' => $sermon['status'],
                                    'label' => 'the sermon "' . $sermon['title'] . '"',
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
