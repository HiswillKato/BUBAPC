<?php
/**
 * BUBAPC - Admin: who has been looking at the website
 *
 * A rough idea of whether anybody is reading the site. One row per browser,
 * counted by day rather than by page view, because a church member refreshing
 * a page three times is still one person.
 *
 * What this page is NOT: there is no cookie, no fingerprint and no way to
 * follow anybody from here. The address is hashed by visitor_key() before it
 * is stored, so the column shown below is not an address and cannot be turned
 * back into one. This answers "is anyone visiting?", never "who was that?".
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../../../app/includes/admin/parts.php';

$active_module = 'visitors';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    /* Deleting rows is a website-admin action, not an editor's: an editor is
       trusted with content, and wiping the record of who has been here is a
       different kind of power. */
    if (!is_admin()) {
        flash('error', 'Only an administrator can do that.');
        redirect(admin_url('visitors'));
    }

    if (post_text('action') === 'sweep') {
        $removed = visitor_sweep($conn);
        flash('success', $removed === 1
            ? 'One old visit was removed.'
            : $removed . ' old visits were removed.');
    }

    redirect(admin_url('visitors'));
}

$counts = visitor_counts($conn);

/* One row per day, taken from `seen`, so the chart needs no table of its own
   and cannot disagree with the list below it. */
$statement = $conn->query(
    "SELECT DATE(seen) AS day, COUNT(*) AS total
     FROM visitors
     WHERE seen >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY DATE(seen)
     ORDER BY day ASC"
);
$days = [];
while ($row = $statement->fetch_assoc()) {
    $days[$row['day']] = (int) $row['total'];
}
$statement->close();

/* The 30 days are drawn as bars against the busiest day rather than a scale of
   one row per visitor, so a quiet Sunday next to a busy one is readable at a
   glance and a bar is never thinner than 2%. */
$chart = [];
$busiest = $days ? max($days) : 0;
for ($back = 29; $back >= 0; $back--) {
    $day = date('Y-m-d', strtotime('-' . $back . ' days'));
    $total = $days[$day] ?? 0;
    $chart[] = [
        'day'    => $day,
        'total'  => $total,
        'height' => $total === 0 ? 0 : max(2, (int) round(($total / max(1, $busiest)) * 100)),
    ];
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$where = [];
$bind_types = '';
$bind_values = [];
if ($search !== '') {
    $where[] = '(agent LIKE ? OR path LIKE ?)';
    $bind_types .= 'ss';
    $like = '%' . $search . '%';
    $bind_values[] = $like;
    $bind_values[] = $like;
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

if ($bind_values) {
    $statement = $conn->prepare("SELECT COUNT(*) FROM visitors v" . $where_sql);
    $statement->bind_param($bind_types, ...$bind_values);
    $statement->execute();
    $total_visitors = (int) $statement->get_result()->fetch_assoc()['COUNT(*)'];
    $statement->close();
} else {
    $total_visitors = db_count($conn, 'SELECT COUNT(*) FROM visitors v' . $where_sql);
}

$per_page = 50;
$current_page = page_number();
$pagination = paginate($total_visitors, $per_page, $current_page);

$statement = $conn->prepare(
    "SELECT v.id, v.agent, v.path, v.visits, v.seen, v.created
     FROM visitors v
     $where_sql
     ORDER BY v.seen DESC, v.id DESC
     LIMIT ? OFFSET ?"
);
$offset = $pagination['offset'];
$statement->bind_param($bind_types . 'ii', ...array_merge($bind_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$visitors = [];
while ($row = $result->fetch_assoc()) {
    $visitors[] = $row;
}
$statement->close();

$page_title = 'Visitors';
$page_heading = 'Visitors';
$page_actions = (is_admin()
    ? '<form method="post" action="' . admin_url('visitors') . '" data-confirm="Delete the visits older '
        . 'than ' . (int) VISITOR_KEEP_DAYS . ' days?">'
        . csrf_field()
        . '<input type="hidden" name="action" value="sweep">'
        . '<button type="submit" class="btn btn-outline-secondary">'
        . '<i class="fa-solid fa-broom me-1" aria-hidden="true"></i>Tidy up old visits</button></form>'
    : '');

require_once __DIR__ . '/../../../../app/includes/admin/header.php';
?>

<div class="row g-3 mb-4">
    <?php
    $tiles = [
        ['fa-user-group', 'Visitors today', $counts['today'], 'counted at some point today'],
        ['fa-calendar-week', 'Visitors this week', $counts['week'], 'counted in the last seven days'],
    ];
    ?>
    <?php foreach ($tiles as $tile): ?>
        <div class="col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <i class="fa-solid <?php echo e($tile[0]); ?> fa-xl text-gold" aria-hidden="true"></i>
                        <span class="h3 mb-0"><?php echo e($tile[2]); ?></span>
                    </div>
                    <p class="fw-semibold mb-0 mt-2"><?php echo e($tile[1]); ?></p>
                    <p class="small text-body-secondary mb-0"><?php echo e($tile[3]); ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-1">
            <i class="fa-solid fa-chart-column me-2 text-gold" aria-hidden="true"></i>The last 30 days
        </h2>
        <p class="small text-body-secondary mb-4">
            One bar per day, showing how many visitors came to the website. A tall bar and an empty
            one next to each other is worth noticing: a sermon on a Sunday or an announcement on a
            Wednesday will do it.
        </p>

        <?php if ($busiest === 0): ?>
            <p class="text-body-secondary mb-0">Nobody has been counted yet. This fills up as people visit.</p>
        <?php else: ?>
            <div class="d-flex align-items-end gap-1" style="height:8rem" role="img"
                 aria-label="Visitors per day for the last 30 days. The busiest day had <?php echo e($busiest); ?>.">
                <?php foreach ($chart as $day): ?>
                    <div class="h-100 d-flex align-items-end flex-grow-1"
                         title="<?php echo format_date($day['day'], 'j M Y'); ?>: <?php echo e($day['total']); ?>">
                        <div class="w-100 rounded-top<?php echo $day['total'] > 0 ? ' bg-gold' : ' bg-body-secondary'; ?>"
                             style="height:<?php echo $day['total'] > 0 ? e($day['height']) : '3px'; ?>;<?php echo $day['total'] > 0 ? '' : 'opacity:.25'; ?>"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex justify-content-between small text-body-secondary mt-2">
                <span><?php echo format_date($chart[0]['day'], 'j M'); ?></span>
                <span><?php echo format_date($chart[count($chart) - 1]['day'], 'j M'); ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>

<form class="row g-2 align-items-center mb-3" method="get" action="<?php echo admin_url('visitors'); ?>" role="search">
    <div class="col-12 col-md">
        <label class="visually-hidden" for="visitor-search">Search the list</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
            <input type="search" class="form-control" id="visitor-search" name="q"
                   value="<?php echo e($search); ?>" placeholder="Search the browser or the page" maxlength="100">
        </div>
    </div>
    <div class="col-12 col-md-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a class="btn btn-outline-secondary" href="<?php echo admin_url('visitors'); ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php admin_result_bar($pagination, $search !== '' ? ['q' => $search] : [], 'visitors'); ?>

<?php if (!$visitors): ?>
    <?php
    empty_state(
        'fa-chart-simple',
        $search !== '' ? 'Nothing matches that' : 'Nobody has been counted yet',
        $search !== ''
            ? 'Try part of the browser name or the page address.'
            : 'This counts visitors who reach the website. It fills up on its own as people visit.',
        $search !== ''
            ? '<a class="btn btn-outline-secondary" href="' . admin_url('visitors') . '">Show everything</a>'
            : '<a class="btn btn-outline-secondary" href="' . url('') . '" target="_blank" rel="noopener">'
              . '<i class="fa-solid fa-up-right-from-square me-1" aria-hidden="true"></i>Open the website</a>',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Last seen</th>
                        <th scope="col">Days counted</th>
                        <th scope="col">Last page</th>
                        <th scope="col">Browser</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visitors as $visitor): ?>
                        <tr>
                            <td class="text-nowrap">
                                <?php echo e(ago($visitor['seen'])); ?>
                                <span class="d-block small text-body-secondary"><?php echo format_date($visitor['seen'], 'j M Y'); ?></span>
                            </td>
                            <td>
                                <span class="badge rounded-pill text-bg-light border"><?php echo e($visitor['visits']); ?></span>
                            </td>
                            <td class="text-body-secondary small">
                                <?php echo $visitor['path'] !== '' ? e($visitor['path']) : 'unknown'; ?>
                            </td>
                            <td class="text-body-secondary small">
                                <?php echo e(browser_name($visitor['agent'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-info d-flex align-items-start gap-2 mt-4" role="note">
        <i class="fa-solid fa-circle-info mt-1" aria-hidden="true"></i>
        <div>
            <strong>These numbers are a rough guide, not a head count.</strong>
            Two people sharing one computer are counted once, and one person using both a phone and
            a laptop is counted twice. Nothing here follows anybody from page to page.
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../../../app/includes/admin/footer.php'; ?>