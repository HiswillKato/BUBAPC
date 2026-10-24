<?php
/**
 * BUBAPC - Admin: the daily devotional list
 *
 * These are the addresses that came in through the form in the footer and the
 * box on register.php. Every morning, the devotional dated for that day is
 * emailed to the addresses that are switched on here, by bin/send-devotional.php
 * run from cron.
 *
 * Switching an address off stops the mail but keeps the row, so it can be
 * switched back on. Deleting is a POST, and the button asks first, because an
 * address that is deleted by accident is gone for good.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'subscribers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifycsrf();

    $action = posttext('action');
    $subscriber_id = (int) ($_POST['id'] ?? 0);
    $subscriber = adminrow($conn, 'subscribers', $subscriber_id);

    if (!$subscriber) {
        flash('error', 'That address is no longer on the list.');
        redirect(adminurl('subscribers'));
    }

    if ($action === 'delete') {
        $statement = $conn->prepare("DELETE FROM subscribers WHERE id = ?");
        $statement->bind_param('i', $subscriber_id);
        $statement->execute();
        $statement->close();

        flash('success', $subscriber['email'] . ' has been taken off the list.');
    } elseif ($action === 'toggle') {
        $new_status = (int) $subscriber['status'] === 1 ? 0 : 1;

        $statement = $conn->prepare("UPDATE subscribers SET status = ? WHERE id = ?");
        $statement->bind_param('ii', $new_status, $subscriber_id);
        $statement->execute();
        $statement->close();

        flash('success', $new_status === 1
            ? $subscriber['email'] . ' will get the devotional again.'
            : $subscriber['email'] . ' will not get the devotional any more. The address is kept, so it can be switched back on.');
    }

    redirect(adminurl('subscribers'));
}

/* The export is a download rather than a change, so it is a plain link. Only
   the addresses that are actually switched on are in the file. */
if (isset($_GET['export'])) {
    $statement = $conn->query("SELECT email, created FROM subscribers
                               WHERE status = 1 ORDER BY email ASC");
    $rows = [];
    while ($row = $statement->fetch_assoc()) {
        $rows[] = $row;
    }
    $statement->close();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bubapc-devotional-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');

    /* The empty $escape is passed on purpose. PHP has said for a while that the
       default will change, and on this version not passing it prints a
       deprecation notice - which lands in the middle of the file being written
       and ends up inside the downloaded spreadsheet. An empty string is the
       value PHP itself now uses as the default, and it also stops a backslash
       in an address from being turned into an escape character. */
    fputcsv($output, ['email', 'added'], ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($output, [$row['email'], $row['created']], ',', '"', '');
    }
    fclose($output);
    exit;
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$filter = isset($_GET['filter']) ? (string) $_GET['filter'] : '';
if (!in_array($filter, ['active', 'inactive'], true)) {
    $filter = '';
}

$where = [];
$bind_types = '';
$bind_values = [];

if ($search !== '') {
    $where[] = 'email LIKE ?';
    $bind_types .= 's';
    $bind_values[] = '%' . $search . '%';
}
if ($filter !== '') {
    $where[] = 'status = ?';
    $bind_types .= 'i';
    $bind_values[] = $filter === 'active' ? 1 : 0;
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

if ($bind_values) {
    $statement = $conn->prepare("SELECT COUNT(*) FROM subscribers" . $where_sql);
    $statement->bind_param($bind_types, ...$bind_values);
    $statement->execute();
    $total_subscribers = (int) $statement->get_result()->fetch_assoc()['COUNT(*)'];
    $statement->close();
} else {
    $total_subscribers = dbcount($conn, "SELECT COUNT(*) FROM subscribers" . $where_sql);
}

$active_count = dbcount($conn, "SELECT COUNT(*) FROM subscribers WHERE status = 1");

$per_page = 50;
$current_page = pagenumber();
$pagination = paginate($total_subscribers, $per_page, $current_page);

$statement = $conn->prepare(
    "SELECT * FROM subscribers" . $where_sql . " ORDER BY status DESC, email ASC LIMIT ? OFFSET ?"
);
$offset = $pagination['offset'];
$statement->bind_param($bind_types . 'ii', ...array_merge($bind_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$subscribers = [];
while ($row = $result->fetch_assoc()) {
    $subscribers[] = $row;
}
$statement->close();

$page_title = 'Devotional list';
$page_heading = 'Devotional list';
$page_actions = $active_count > 0
    ? '<a class="btn btn-outline-secondary" href="' . adminurl('subscribers?export=1') . '">'
        . '<i class="fa-solid fa-file-csv me-1" aria-hidden="true"></i>Download as a spreadsheet</a>'
    : '';

require_once __DIR__ . '/../../partials/admin/header.php';
?>

<?php if ($active_count === 0): ?>
    <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
        <i class="fa-solid fa-circle-info mt-1" aria-hidden="true"></i>
        <div>
            <strong>Nobody is on the list yet.</strong>
            The form in the footer of every page adds addresses here, and so does the box on the
            register page. Every morning, the devotional written for that day is sent out
            automatically to everyone switched on.
        </div>
    </div>
<?php endif; ?>

<?php
$filter_query = ['q' => $search];
if ($filter !== '') {
    $filter_query['filter'] = $filter;
}
?>
<form class="row g-2 align-items-center mb-3" method="get" action="<?php echo adminurl('subscribers'); ?>" role="search">
    <div class="col-12 col-md">
        <label class="visually-hidden" for="filter-search">Search</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
            <input type="search" class="form-control" id="filter-search" name="q"
                   value="<?php echo e($search); ?>" placeholder="Search by email address" maxlength="100">
        </div>
    </div>
    <div class="col-12 col-md-auto">
        <label class="visually-hidden" for="filter-state">On or off the list</label>
        <select class="form-select" id="filter-state" name="filter" data-auto-submit>
            <option value="">Everyone</option>
            <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Getting mail</option>
            <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Switched off</option>
        </select>
    </div>
    <div class="col-12 col-md-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($search !== '' || $filter !== ''): ?>
            <a class="btn btn-outline-secondary" href="<?php echo adminurl('subscribers'); ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php adminresultbar($pagination, $filter_query, 'addresses'); ?>

<?php if (!$subscribers): ?>
    <?php
    emptystate(
        'fa-paper-plane',
        $search !== '' || $filter !== '' ? 'No addresses match that' : 'The list is empty',
        $search !== '' || $filter !== ''
            ? 'Try part of the address, or clear the filter to see everyone.'
            : 'When somebody puts their email address in the box at the bottom of any page, it lands here.',
        '<a class="btn btn-outline-secondary" href="' . url('contact') . '" target="_blank" rel="noopener">'
        . '<i class="fa-solid fa-up-right-from-square me-1" aria-hidden="true"></i>Open the website</a>',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-sm">
                <thead>
                    <tr>
                        <th scope="col" class="ps-3">Email address</th>
                        <th scope="col">Added</th>
                        <th scope="col">Sending</th>
                        <th scope="col" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscribers as $subscriber): ?>
                        <tr>
                            <td class="ps-3">
                                <a href="mailto:<?php echo e($subscriber['email']); ?>"><?php echo e($subscriber['email']); ?></a>
                            </td>
                            <td class="small text-body-secondary text-nowrap">
                                <?php echo formatdate($subscriber['created'], 'j M Y'); ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?php echo (int) $subscriber['status'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                    <?php echo (int) $subscriber['status'] === 1 ? 'Yes' : 'No'; ?>
                                </span>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <?php
                                echo adminactiongroup(
                                    admintogglebutton('subscribers', $subscriber['id'], $subscriber['status'], 'Send to them', 'Stop sending')
                                    . admindeletebutton('subscribers', $subscriber['id'], $subscriber['email'])
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
