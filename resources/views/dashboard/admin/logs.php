<?php
/**
 * BUBAPC - Admin: what has been happening on the website
 *
 * A plain, read-only list of the things log_action() has written: what
 * changed, who changed it, and where from. It exists so a question like
 * "who took the photo off the homepage?" can be answered after the fact
 * without anybody having to watch the site.
 *
 * There is no editing here on purpose. The log is a record, and a record
 * that can be quietly rewritten is not a record. The only change the page
 * allows is emptying it, which is a POST and asks first.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../../../app/includes/admin/parts.php';

$active_module = 'logs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    /* An editor can read the log but cannot wipe it: the log is the only
       trace of a change made by somebody with more access than they have,
       so the button that destroys it belongs to the website admin only. */
    if (!is_admin()) {
        flash('error', 'Only somebody with administrator access can clear the log.');
        redirect(admin_url('logs'));
    }

    if (post_text('action') === 'clear') {
        $conn->query("DELETE FROM logs");
        log_action($conn, 'log_cleared', 'The activity log was cleared');
        flash('success', 'The activity log has been cleared.');
    }

    redirect(admin_url('logs'));
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$filter = isset($_GET['filter']) ? (string) $_GET['filter'] : '';

/* The filter is a list of the event names that are actually in the table, so
   the dropdown can never offer a filter that returns nothing. Building the
   list from the table is also why a new log_action() call needs no edit here. */
$statement = $conn->query("SELECT action, COUNT(*) AS total
                           FROM logs GROUP BY action ORDER BY total DESC, action ASC");
$action_names = [];
while ($row = $statement->fetch_assoc()) {
    $action_names[$row['action']] = (int) $row['total'];
}
$statement->close();

$filter = $filter !== '' && isset($action_names[$filter]) ? $filter : '';

/* Build the WHERE clause first and add ORDER BY and LIMIT to it afterwards.
   Appending a search to finished SQL would put it after "LIMIT ?" and be a
   syntax error, which is the whole reason this is written in this order. */
$where = [];
$bind_types = '';
$bind_values = [];

if ($search !== '') {
    $where[] = '(l.details LIKE ? OR l.action LIKE ? OR l.ip LIKE ?)';
    $bind_types .= 'sss';
    $search_like = '%' . $search . '%';
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
}
if ($filter !== '') {
    $where[] = 'l.action = ?';
    $bind_types .= 's';
    $bind_values[] = $filter;
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

if ($bind_values) {
    $statement = $conn->prepare("SELECT COUNT(*) FROM logs l" . $where_sql);
    $statement->bind_param($bind_types, ...$bind_values);
    $statement->execute();
    $total_logs = (int) $statement->get_result()->fetch_assoc()['COUNT(*)'];
    $statement->close();
} else {
    $total_logs = db_count($conn, 'SELECT COUNT(*) FROM logs l' . $where_sql);
}

$per_page = 50;
$current_page = page_number();
$pagination = paginate($total_logs, $per_page, $current_page);

/* The person's name is joined in rather than read from a second query, so the
   list costs one question however many rows there are. A row with no actor is
   a visitor who has never signed in, which the COALESCE says in as much. */
$statement = $conn->prepare(
    "SELECT l.id, l.actor, l.action, l.details, l.ip, l.created,
            COALESCE(p.name, '') AS full_name,
            COALESCE(p.username, '') AS username,
            COALESCE(p.role, '') AS role
     FROM logs l
     LEFT JOIN people p ON p.id = l.actor
     $where_sql
     ORDER BY l.created DESC, l.id DESC
     LIMIT ? OFFSET ?"
);
$offset = $pagination['offset'];
$statement->bind_param($bind_types . 'ii', ...array_merge($bind_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}
$statement->close();

/* How many failed sign-ins in the last day. This is the one number on the
   screen worth watching: a burst of them is either somebody who has
   forgotten their password or somebody guessing, and the details below say
   which addresses and which names were tried. */
$refused_today = (int) db_one(
    $conn,
    "SELECT COUNT(*) AS total FROM logs
     WHERE action = 'login_refused' AND created >= CURDATE()"
)['total'];

$page_title = 'Activity log';
$page_heading = 'Activity log';
$page_actions = (is_admin() && $total_logs > 0)
    ? '<form method="post" action="' . admin_url('logs') . '" data-confirm="Clear the whole log? '
        . 'This cannot be undone." class="d-inline">'
        . csrf_field()
        . '<input type="hidden" name="action" value="clear">'
        . '<button type="submit" class="btn btn-outline-danger">'
        . '<i class="fa-solid fa-broom me-1" aria-hidden="true"></i>Clear the log</button></form>'
    : '';

require_once __DIR__ . '/../../../../app/includes/admin/header.php';
?>

<?php if ($refused_today > 0): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
        <i class="fa-solid fa-triangle-exclamation mt-1" aria-hidden="true"></i>
        <div>
            <strong><?php echo e($refused_today); ?> wrong password<?php echo $refused_today === 1 ? '' : 's'; ?> today.</strong>
            Somebody who has simply forgotten their password is locked out for
            <?php echo e(LOGIN_LOCK_MINUTES); ?> minutes. If the number keeps climbing, it is worth looking at
            the list below.
        </div>
    </div>
<?php endif; ?>

<div class="row g-2 align-items-center mb-3">
    <div class="col-12 col-lg">
        <form class="input-group" method="get" action="<?php echo admin_url('logs'); ?>" role="search">
            <?php if ($filter !== ''): ?>
                <input type="hidden" name="filter" value="<?php echo e($filter); ?>">
            <?php endif; ?>
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
            <label class="visually-hidden" for="log-search">Search the log</label>
            <input type="search" class="form-control" id="log-search" name="q"
                   value="<?php echo e($search); ?>" placeholder="Search what happened, or an address"
                   maxlength="100">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>

    <?php if ($action_names): ?>
        <div class="col-12 col-lg-auto">
            <form method="get" action="<?php echo admin_url('logs'); ?>">
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="q" value="<?php echo e($search); ?>">
                <?php endif; ?>
                <label class="visually-hidden" for="log-filter">One kind of event</label>
                <select class="form-select" id="log-filter" name="filter" data-auto-submit>
                    <option value="">Everything (<?php echo e($total_logs); ?>)</option>
                    <?php foreach ($action_names as $action_name => $action_total): ?>
                        <option value="<?php echo e($action_name); ?>" <?php echo $filter === $action_name ? 'selected' : ''; ?>>
                            <?php echo log_action_label($action_name); ?> (<?php echo e($action_total); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php
$filter_query = [];
if ($search !== '') {
    $filter_query['q'] = $search;
}
if ($filter !== '') {
    $filter_query['filter'] = $filter;
}
admin_result_bar($pagination, $filter_query, 'entries');
?>

<?php if (!$logs): ?>
    <?php
    empty_state(
        'fa-clipboard-list',
        $search !== '' || $filter !== '' ? 'Nothing matches that' : 'Nothing has happened yet',
        $search !== '' || $filter !== ''
            ? 'Try a different word, or show everything.'
            : 'Anything anybody changes on the website is written here as it happens, so this list '
              . 'fills up on its own. A sign-in is recorded too.',
        $search !== '' || $filter !== ''
            ? '<a class="btn btn-outline-secondary" href="' . admin_url('logs') . '">Show everything</a>'
            : '',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Who</th>
                        <th scope="col">What happened</th>
                        <th scope="col">From</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        /* A sign-in that failed has no name to show, because the
                           name was never proved, so the attempt itself is shown
                           instead. Same for an address with no account. */
                        $is_refusal = $log['action'] === 'login_refused';
                        $who = $log['full_name'] !== ''
                            ? $log['full_name']
                            : ($log['actor'] > 0 ? 'Account #' . (int) $log['actor'] : '');
                        ?>
                        <tr<?php echo $is_refusal ? ' class="table-warning"' : ''; ?>>
                            <td class="text-nowrap">
                                <?php echo format_date($log['created'], 'j M Y'); ?>
                                <span class="d-block small text-body-secondary"><?php echo format_date($log['created'], 'H:i'); ?></span>
                            </td>
                            <td>
                                <?php if ($who !== ''): ?>
                                    <?php echo e($who); ?>
                                    <span class="d-block small text-body-secondary"><?php echo e($log['username']); ?></span>
                                <?php elseif ($is_refusal): ?>
                                    <span class="text-body-secondary">Nobody signed in</span>
                                <?php else: ?>
                                    <span class="text-body-secondary">A visitor</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="d-block">
                                    <?php echo log_action_label($log['action']); ?>
                                    <?php if ($is_refusal): ?>
                                        <i class="fa-solid fa-ban text-danger ms-1" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </span>
                                <?php if ($log['details'] !== ''): ?>
                                    <span class="d-block small text-body-secondary"><?php echo e($log['details']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-body-secondary small text-nowrap">
                                <?php echo $log['ip'] !== '' ? e($log['ip']) : 'unknown'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../../../app/includes/admin/footer.php'; ?>