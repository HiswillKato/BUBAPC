<?php
/**
 * BUBAPC - Admin: the list of events
 *
 * A GET shows the list. A POST does one of three things: delete an event,
 * publish or unpublish it, or nothing at all. Those three are the small jobs
 * an editor does ten times a day, so they are buttons on the list rather than
 * something you have to open the form for.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../../../app/includes/admin/parts.php';

$active_module = 'events';

/* ---------------------------------------------------------------------
   1. THE THREE BUTTONS ON EACH ROW
   ---------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = post_text('action');
    $event_id = (int) ($_POST['id'] ?? 0);
    $event = admin_row($conn, 'events', $event_id);

    if (!$event) {
        flash('error', 'That event is no longer here. It may already have been deleted.');
        redirect(admin_url('events'));
    }

    if ($action === 'delete') {
        /* The picture goes too, otherwise the uploads folder slowly fills up
           with files nothing points at any more. */
        delete_uploaded_image($event['image']);

        $statement = $conn->prepare("DELETE FROM events WHERE id = ?");
        $statement->bind_param('i', $event_id);
        $statement->execute();
        $statement->close();

        log_action($conn, 'event_deleted', 'The event "' . $event['title'] . '"');
        flash('success', 'The event "' . $event['title'] . '" has been deleted.');
    } elseif ($action === 'toggle_status') {
        $new_status = $event['status'] === 'published' ? 'draft' : 'published';

        $statement = $conn->prepare("UPDATE events SET status = ? WHERE id = ?");
        $statement->bind_param('si', $new_status, $event_id);
        $statement->execute();
        $statement->close();

        flash('success', $new_status === 'published'
            ? 'The event "' . $event['title'] . '" is now on the website.'
            : 'The event "' . $event['title'] . '" is now a draft. Nobody can see it.');
    }

    redirect(admin_url('events'));
}

/* ---------------------------------------------------------------------
   2. THE SEARCH BOX AND THE PAGE OF RESULTS
   ---------------------------------------------------------------------- */
$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
if (!in_array($status, ['published', 'draft'], true)) {
    $status = '';
}

$where = [];
$bind_types = '';
$bind_values = [];

if ($search !== '') {
    /* The LIKE has its % marks in the value, not in the SQL, so the
       wildcard is part of the word that is being searched for. */
    $where[] = '(title LIKE ? OR theme LIKE ? OR venue LIKE ?)';
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

$count_sql = "SELECT COUNT(*) FROM events" . $where_sql;
if ($bind_values) {
    $statement = $conn->prepare($count_sql);
    $statement->bind_param($bind_types, ...$bind_values);
    $statement->execute();
    $result = $statement->get_result();
    $total_events = (int) $result->fetch_assoc()['COUNT(*)'];
    $statement->close();
} else {
    $total_events = db_count($conn, $count_sql);
}

$per_page = 15;
$current_page = page_number();
$pagination = paginate($total_events, $per_page, $current_page);

$list_sql = "SELECT * FROM events" . $where_sql
    . " ORDER BY start_date DESC, start_time DESC LIMIT ? OFFSET ?";

$statement = $conn->prepare($list_sql);
$offset = $pagination['offset'];
$list_types = $bind_types . 'ii';
$list_values = array_merge($bind_values, [$per_page, $offset]);
$statement->bind_param($list_types, ...$list_values);
$statement->execute();
$result = $statement->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}
$statement->close();

$page_title = 'Events';
$page_heading = 'Events';
$page_actions = '<a class="btn btn-primary" href="' . admin_url('events/form.php') . '">'
    . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add event</a>';

require_once __DIR__ . '/../../../../app/includes/admin/header.php';
?>

<?php admin_filter_bar(admin_url('events'), $search, $status, ['published' => 'Published', 'draft' => 'Draft']); ?>

<?php admin_result_bar($pagination, ['q' => $search, 'status' => $status], 'events'); ?>

<?php if (!$events): ?>
    <?php
    empty_state(
        'fa-calendar-plus',
        $search !== '' || $status !== '' ? 'No events match that' : 'No events yet',
        $search !== '' || $status !== ''
            ? 'Try a different word, or clear the filter to see everything.'
            : 'Retreats, conventions and special services go here. A visitor sees them on the '
                . 'homepage and on the events page, and only the ones you publish.',
        '<a class="btn btn-primary" href="' . admin_url('events/form.php') . '">'
        . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add the first event</a>',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" class="ps-3">Event</th>
                        <th scope="col">When</th>
                        <th scope="col">Where</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?php echo upload_url($event['image']); ?>" alt=""
                                         width="48" height="48" class="rounded-3 object-fit-cover flex-shrink-0">
                                    <div>
                                        <a class="fw-semibold d-block text-decoration-none"
                                           href="<?php echo admin_url('events/form.php?id=' . (int) $event['id']); ?>">
                                            <?php echo e($event['title']); ?>
                                        </a>
                                        <?php if ($event['theme'] !== ''): ?>
                                            <span class="small text-body-secondary"><?php echo e($event['theme']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="small text-nowrap">
                                <?php echo event_date_label($event); ?>
                            </td>
                            <td class="small text-body-secondary"><?php echo e($event['venue']); ?></td>
                            <td><?php echo status_badge($event['status']); ?></td>
                            <td class="text-end pe-3 text-nowrap">
                                <?php
                                echo admin_row_actions('events', $event['id'], [
                                    'status' => $event['status'],
                                    'label' => 'the event "' . $event['title'] . '"',
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
