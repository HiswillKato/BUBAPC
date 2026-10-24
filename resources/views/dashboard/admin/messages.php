<?php
/**
 * BUBAPC - Admin: messages from the contact page
 *
 * These are the messages people send through the contact form. Opening one
 * marks it as read, which is why the unread ones sit at the top and carry a
 * blue dot.
 *
 * There is no reply button on purpose. Replying happens in the church's own
 * email program, and pretending otherwise here would only give half a reply.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'messages';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = post_text('action');
    $message_id = (int) ($_POST['id'] ?? 0);
    $message = admin_row($conn, 'messages', $message_id);

    if (!$message) {
        flash('error', 'That message is no longer here. It may already have been deleted.');
        redirect(admin_url('messages'));
    }

    if ($action === 'delete') {
        $statement = $conn->prepare("DELETE FROM messages WHERE id = ?");
        $statement->bind_param('i', $message_id);
        $statement->execute();
        $statement->close();

        flash('success', 'The message from ' . $message['name'] . ' has been deleted.');
    } elseif ($action === 'toggle') {
        $new_read = (int) $message['seen'] === 1 ? 0 : 1;

        $statement = $conn->prepare("UPDATE messages SET seen = ? WHERE id = ?");
        $statement->bind_param('ii', $new_read, $message_id);
        $statement->execute();
        $statement->close();

        flash('success', $new_read === 1
            ? 'Marked as read.'
            : 'Marked as unread.');
    } elseif ($action === 'read_all') {
        $statement = $conn->prepare("UPDATE messages SET seen = 1 WHERE seen = 0");
        $statement->execute();
        $changed = $statement->affected_rows;
        $statement->close();

        flash('success', $changed === 1 ? 'One message marked as read.' : $changed . ' messages marked as read.');
    }

    redirect(admin_url('messages'));
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$filter = isset($_GET['filter']) ? (string) $_GET['filter'] : '';
if (!in_array($filter, ['unread', 'read'], true)) {
    $filter = '';
}

$where = [];
$bind_types = '';
$bind_values = [];

if ($search !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
    $like = '%' . $search . '%';
    $bind_types .= 'ssss';
    $bind_values[] = $like;
    $bind_values[] = $like;
    $bind_values[] = $like;
    $bind_values[] = $like;
}
if ($filter !== '') {
    $where[] = 'seen = ?';
    $bind_types .= 'i';
    $bind_values[] = $filter === 'read' ? 1 : 0;
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

if ($bind_values) {
    $statement = $conn->prepare("SELECT COUNT(*) FROM messages" . $where_sql);
    $statement->bind_param($bind_types, ...$bind_values);
    $statement->execute();
    $total_messages = (int) $statement->get_result()->fetch_assoc()['COUNT(*)'];
    $statement->close();
} else {
    $total_messages = db_count($conn, "SELECT COUNT(*) FROM messages" . $where_sql);
}

$unread_count = db_count($conn, "SELECT COUNT(*) FROM messages WHERE seen = 0");

$per_page = 20;
$current_page = page_number();
$pagination = paginate($total_messages, $per_page, $current_page);

$statement = $conn->prepare(
    "SELECT * FROM messages" . $where_sql . " ORDER BY created DESC, id DESC LIMIT ? OFFSET ?"
);
$offset = $pagination['offset'];
$statement->bind_param($bind_types . 'ii', ...array_merge($bind_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$statement->close();

$page_title = 'Messages';
$page_heading = 'Messages';
$page_actions = $unread_count > 0
    ? '<form method="post" action="' . admin_url('messages') . '" class="d-inline">'
        . csrf_field()
        . '<input type="hidden" name="action" value="read_all">'
        . '<button type="submit" class="btn btn-outline-secondary">'
        . '<i class="fa-solid fa-envelope-open me-1" aria-hidden="true"></i>Mark all as read</button>'
        . '</form>'
    : '';

require_once __DIR__ . '/../../partials/admin/header.php';
?>

<?php
$filter_query = ['q' => $search];
if ($filter !== '') {
    $filter_query['filter'] = $filter;
}
?>
<form class="row g-2 align-items-center mb-3" method="get" action="<?php echo admin_url('messages'); ?>" role="search">
    <div class="col-12 col-md">
        <label class="visually-hidden" for="filter-search">Search</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
            <input type="search" class="form-control" id="filter-search" name="q"
                   value="<?php echo e($search); ?>" placeholder="Search by name, address or words" maxlength="100">
        </div>
    </div>
    <div class="col-12 col-md-auto">
        <label class="visually-hidden" for="filter-state">Read or unread</label>
        <select class="form-select" id="filter-state" name="filter" data-auto-submit>
            <option value="">All messages</option>
            <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread only</option>
            <option value="read" <?php echo $filter === 'read' ? 'selected' : ''; ?>>Read only</option>
        </select>
    </div>
    <div class="col-12 col-md-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($search !== '' || $filter !== ''): ?>
            <a class="btn btn-outline-secondary" href="<?php echo admin_url('messages'); ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($unread_count > 0): ?>
    <p class="small text-body-secondary">
        <i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>
        <?php echo e($unread_count); ?> unread <?php echo $unread_count === 1 ? 'message' : 'messages'; ?>.
    </p>
<?php endif; ?>

<?php admin_result_bar($pagination, $filter_query, 'messages'); ?>

<?php if (!$messages): ?>
    <?php
    empty_state(
        'fa-envelope-open-text',
        $search !== '' || $filter !== '' ? 'No messages match that' : 'No messages yet',
        $search !== '' || $filter !== ''
            ? 'Try a different word, or clear the filter to see everything.'
            : 'Nothing has come through the contact form. When somebody writes, it lands here with their '
                . 'name and email address so you can write back.',
        '<a class="btn btn-outline-secondary" href="' . url('contact') . '" target="_blank" rel="noopener">'
        . '<i class="fa-solid fa-up-right-from-square me-1" aria-hidden="true"></i>Open the contact page</a>',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-sm">
                <thead>
                    <tr>
                        <th scope="col" class="ps-3">From</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Message</th>
                        <th scope="col" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message): ?>
                        <tr class="<?php echo (int) $message['seen'] === 0 ? 'table-warning' : ''; ?>">
                            <td class="ps-3">
                                <?php if ((int) $message['seen'] === 0): ?>
                                    <span class="d-inline-block rounded-circle bg-primary me-1"
                                          style="width:.6rem;height:.6rem" aria-hidden="true"></span>
                                    <span class="visually-hidden">Unread</span>
                                <?php endif; ?>
                                <span class="fw-semibold"><?php echo e($message['name']); ?></span>
                                <br>
                                <a class="small" href="mailto:<?php echo e($message['email']); ?>">
                                    <?php echo e($message['email']); ?>
                                </a>
                                <br>
                                <span class="small text-body-secondary"><?php echo e(ago($message['created'])); ?></span>
                            </td>
                            <td class="small">
                                <?php echo e($message['subject'] !== '' ? $message['subject'] : 'No subject'); ?>
                            </td>
                            <td class="small text-body-secondary">
                                <?php echo e(excerpt($message['message'], 90)); ?>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <?php
                                echo admin_action_group(
                                    '<a class="btn btn-sm btn-soft" href="'
                                    . admin_url('messages/view.php?id=' . (int) $message['id']) . '">'
                                    . '<i class="fa-solid fa-envelope-open" aria-hidden="true"></i>'
                                    . '<span class="visually-hidden">Read in full</span></a>'
                                    . admin_toggle_button('messages', $message['id'], $message['seen'], 'Mark as read', 'Mark as unread')
                                    . admin_delete_button('messages', $message['id'], 'the message from ' . $message['name'])
                                );
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
