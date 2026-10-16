<?php
/**
 * BUBAPC - The admin dashboard, the first page inside /admin
 *
 * Who reads this page: a pastor or a secretary who knows the church well and
 * knows nothing about websites. So the page answers two questions and stops:
 * how much has been written, and what is happening today. Each number is a link,
 * and the menu on the left lists everything, so there is no second list of links
 * on this page to work through.
 *
 * That is why the numbers carry no sentence and no button. They used to, and
 * the extra words were saying nothing the number did not already say: a card
 * reading "Sermons, 12, 2 written but not on the website yet, Show the list"
 * is three ways of being told one thing, and the link was already in the menu.
 * A card now shows a name, a number, and - only when it is not zero - a short
 * count of what is waiting.
 *
 * What is deliberately not on this page: the activity log, the visitor counts,
 * the wrong-password counter and the published/unpublished table. Those are how
 * the website is looked after, not what the church is doing, so they live on
 * their own pages in the menu, where somebody who wants them can open them
 * without reading past them to reach the devotionals. The log in particular used
 * to sit at the bottom of this page, which made the first screen of the admin
 * panel a place where the system reports on itself.
 *
 * Every number comes from the tables themselves, so nothing here can go out of
 * date. Nothing on this page saves anything, so there is no POST handler.
 */

require_once __DIR__ . '/../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../app/includes/admin/parts.php';

$active_module = 'dashboard';
$page_title = 'Dashboard';
$page_heading = 'Dashboard';
$page_actions = '<a class="btn btn-primary" href="' . admin_url('devotionals/form.php') . '">'
    . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Write a devotional</a>';

require_once __DIR__ . '/../../app/includes/admin/header.php';

$admin = current_user();

/* How much of each thing is written, and how much of it visitors can see.
   The tables are not all built the same way: some have a published/draft
   status and some have a simple on/off switch, so each one is asked the
   question in the words that table understands. The names come from the list
   below, not from the browser, so the SQL is fixed.

   The list holds only the things the page shows. Departments, families and
   photographs used to be counted here as well, for a table further down that
   has since gone; they are still in the menu, and counting a table nobody is
   shown the answer to is three queries of nothing. */
$count_tables = [
    'events'        => ['status', "'published'"],
    'devotionals'   => ['status', "'published'"],
    'sermons'       => ['status', "'published'"],
    'announcements' => ['status', "'published'"],
    'people'        => ['status', '1'],
];

$counts = [];
foreach ($count_tables as $table => $column) {
    $live_value = $column[1];
    $row = db_one($conn, "SELECT COUNT(*) AS total,
                                 SUM(`$column[0]` = $live_value) AS live
                          FROM `$table`");
    $counts[$table] = [
        'total' => (int) ($row['total'] ?? 0),
        'live' => (int) ($row['live'] ?? 0),
        'hidden' => (int) ($row['total'] ?? 0) - (int) ($row['live'] ?? 0),
    ];
}

$unread_messages = (int) db_one($conn, "SELECT COUNT(*) AS total FROM messages WHERE seen = 0")['total'];
$subscribers = (int) db_one($conn, "SELECT COUNT(*) AS total FROM subscribers WHERE status = 1")['total'];

/* The two things worth saying in a sentence, because a number cannot carry
   them: when the next thing happens, and what visitors are being shown today. */
$next_event = db_one($conn, "SELECT * FROM events
                             WHERE status = 'published' AND start_date >= CURDATE()
                             ORDER BY start_date ASC, start_time ASC LIMIT 1");
$today_devotional = db_one($conn, "SELECT * FROM devotionals
                                   WHERE status = 'published' AND devotional_date <= CURDATE()
                                   ORDER BY devotional_date DESC LIMIT 1");

/* Good morning is only right half the day, and the dashboard is often the
   first thing anybody sees, so it says hello properly. */
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

/* One number per thing, with the count of what is waiting attached only when
   there is something waiting. The keys are: icon, name, number, the short note,
   the module to link to, and the colour. "not shown" is said that way because
   it is the phrase the church uses; "draft" is what the database calls it. */
$cards = [
    ['fa-book-bible', 'Devotionals', $counts['devotionals']['total'],
        $counts['devotionals']['hidden'], 'devotionals', 'primary'],
    ['fa-music', 'Sermons', $counts['sermons']['total'],
        $counts['sermons']['hidden'], 'sermons', 'primary'],
    ['fa-calendar-day', 'Events', $counts['events']['total'],
        $counts['events']['hidden'], 'events', 'warning'],
    ['fa-bullhorn', 'Announcements', $counts['announcements']['total'],
        $counts['announcements']['hidden'], 'announcements', 'warning'],
    ['fa-users', 'People', $counts['people']['total'],
        $counts['people']['hidden'], 'people', 'success'],
    ['fa-envelope-open-text', 'Messages', $unread_messages,
        0, 'messages', $unread_messages > 0 ? 'danger' : 'secondary'],
    ['fa-paper-plane', 'Devotional subscribers', $subscribers,
        0, 'subscribers', 'secondary'],
];
?>

    <p class="text-body-secondary mb-4">
        <?php echo e($greeting . ', ' . $admin['full_name']); ?>
        &middot; <?php echo format_date(date('Y-m-d'), 'l, j F Y'); ?>
    </p>

    <div class="row g-3 mb-4">
        <?php foreach ($cards as $card): ?>
            <div class="col-6 col-md-4 col-xl-3">
                <a class="card stat-card border-0 shadow-sm rounded-4 h-100 text-decoration-none text-body"
                   href="<?php echo admin_url($card[4]); ?>">
                    <div class="card-body d-flex flex-column gap-1">
                        <span class="d-flex align-items-center gap-2">
                            <span class="stat-icon bg-<?php echo e($card[5]); ?> bg-opacity-10 text-<?php echo e($card[5]); ?>"
                                  aria-hidden="true">
                                <i class="fa-solid <?php echo e($card[0]); ?>"></i>
                            </span>
                            <span class="fw-semibold"><?php echo e($card[1]); ?></span>
                        </span>

                        <span class="fs-2 fw-bold lh-1"><?php echo e(number_format($card[2])); ?></span>

                        <?php /* Shown only when there is something to say, so a
                               card with nothing outstanding stays a single
                               number. The messages card has no note because its
                               number is itself the thing that is waiting. */
                        if ($card[4] === 'messages' && $card[2] > 0): ?>
                            <span class="small text-body-secondary">waiting for a reply</span>
                        <?php elseif ($card[3] > 0): ?>
                            <span class="small text-body-secondary">
                                <?php echo e($card[3]); ?> not shown
                            </span>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">
                        <i class="fa-solid fa-calendar-day me-2 text-gold" aria-hidden="true"></i>Today
                    </h2>

                    <?php if ($next_event): ?>
                        <p class="mb-3">
                            <strong>Next: <?php echo e($next_event['title']); ?></strong>
                            <span class="text-body-secondary">
                                &middot; <?php echo event_date_label($next_event); ?>
                                <?php if ($next_event['venue'] !== ''): ?>
                                    &middot; <?php echo e($next_event['venue']); ?>
                                <?php endif; ?>
                            </span>
                        </p>
                    <?php else: ?>
                        <p class="mb-3 text-body-secondary">
                            Nothing is in the calendar yet.
                        </p>
                    <?php endif; ?>

                    <?php if ($today_devotional): ?>
                        <p class="mb-0">
                            <strong>Devotional: <?php echo e($today_devotional['title']); ?></strong>
                            <span class="text-body-secondary">
                                &middot; <?php echo format_date($today_devotional['devotional_date'], 'j F'); ?>
                            </span>
                        </p>
                    <?php else: ?>
                        <p class="mb-0 text-body-secondary">
                            No devotional is on the website today.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../../app/includes/admin/footer.php'; ?>
