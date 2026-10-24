<?php
/**
 * BUBAPC - Events
 *
 * One file, two jobs. With a slug in the address (/events/christmas-retreat,
 * which .htaccess turns into ?slug=christmas-retreat) it shows a single event.
 * Without one it shows the list.
 *
 * The slug is always looked up with a prepared statement, and only a
 * published event is ever shown: a draft must not be readable from its address.
 */

require_once __DIR__ . '/../../../app/init.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$per_page = 9;

/* ---------------------------------------------------------------------
   1. LOOK FOR ONE EVENT WHEN A SLUG WAS GIVEN
   --------------------------------------------------------------------- */
if ($slug !== '') {
    $statement = $conn->prepare("SELECT * FROM events WHERE slug = ? AND status = 'published' LIMIT 1");
    $statement->bind_param('s', $slug);
    $statement->execute();
    $result = $statement->get_result();
    $event = $result->fetch_assoc();
    $statement->close();

    if (!$event) {
        http_response_code(404);
        $error_code = 404;
        $error_title = 'Event not found';
        $error_message = 'That event is not on our website. It may have been removed, '
            . 'or it may still be a draft. Try the events list instead.';
        require __DIR__ . '/../partials/error-page.php';
        exit;
    }

    $page_title = $event['title'];
    $page_description = excerpt(strip_tags(html_entity_decode($event['description'] ?: $event['title'])), 160);
    $canonical = 'events/' . $event['slug'];

    require_once __DIR__ . '/../partials/public/header.php';

    pageHeader(
        $event['title'],
        $event['theme'] !== '' ? $event['theme'] : 'You are warmly invited.',
        [['Home', url('')], ['Events', url('events')], [$event['title'], '']]
    );

    $event_link = url('events/' . $event['slug']);
    $moment = eventMoment($event);
    $is_past = !empty($event['start_date']) && $event['start_date'] < date('Y-m-d');
    ?>

    <section class="py-5">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-8">

                    <?php if (!empty($event['image'])): ?>
                        <img src="<?php echo uploadUrl($event['image']); ?>" alt="<?php echo e($event['title']); ?>"
                             class="img-fluid rounded-4 shadow-sm mb-4 w-100">
                    <?php endif; ?>

                    <?php if (!empty($event['description'])): ?>
                        <div class="fs-5">
                            <?php echo paragraphs($event['description']); ?>
                        </div>
                    <?php else: ?>
                        <p class="fs-5">
                            More details about this gathering will be posted here soon.
                            If you would like to know more in the meantime, please
                            <a href="<?php echo url('contact'); ?>">get in touch</a>.
                        </p>
                    <?php endif; ?>

                    <div class="mt-4"><?php shareBar($event['title'], $event_link); ?></div>

                    <a class="btn btn-outline-primary mt-4" href="<?php echo url('events'); ?>">
                        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>All events
                    </a>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <p class="text-uppercase fw-bold small text-gold-dark mb-3">The details</p>

                            <ul class="list-group list-group-flush">
                                <li class="list-group-item px-0 d-flex gap-3">
                                    <i class="fa-solid fa-calendar-day text-brand" aria-hidden="true"></i>
                                    <div>
                                        <p class="fw-semibold mb-0">Date</p>
                                        <span class="small text-body-secondary">
                                            <?php echo formatDate($event['start_date'], 'l, j F Y'); ?>
                                            <?php if (!empty($event['end_date']) && $event['end_date'] !== $event['start_date']): ?>
                                                to <?php echo formatDate($event['end_date'], 'j F Y'); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </li>

                                <?php if (!empty($event['start_time']) || !empty($event['end_time'])): ?>
                                    <li class="list-group-item px-0 d-flex gap-3">
                                        <i class="fa-solid fa-clock text-brand" aria-hidden="true"></i>
                                        <div>
                                            <p class="fw-semibold mb-0">Time</p>
                                            <span class="small text-body-secondary">
                                                <?php echo formatTimeRange($event['start_time'], $event['end_time']); ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php endif; ?>

                                <?php if (!empty($event['venue'])): ?>
                                    <li class="list-group-item px-0 d-flex gap-3">
                                        <i class="fa-solid fa-location-dot text-brand" aria-hidden="true"></i>
                                        <div>
                                            <p class="fw-semibold mb-0">Where</p>
                                            <span class="small text-body-secondary"><?php echo e($event['venue']); ?></span>
                                        </div>
                                    </li>
                                <?php endif; ?>
                            </ul>

                            <?php if (!$is_past && $moment !== ''): ?>
                                <div class="d-flex flex-wrap gap-2 mt-3" data-countdown="<?php echo e($moment); ?>"></div>
                            <?php elseif ($is_past): ?>
                                <p class="small text-body-secondary mt-3 mb-0">
                                    <i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>
                                    This event has already taken place. Thank you to everyone who came.
                                </p>
                            <?php endif; ?>

                            <a class="btn btn-primary w-100 mt-3" href="<?php echo url('contact'); ?>">
                                <i class="fa-solid fa-circle-question me-1" aria-hidden="true"></i>Ask about this event
                            </a>
                        </div>
                    </div>

                    <a class="btn btn-soft btn-sm w-100 mt-3" href="<?php echo url('programs'); ?>">
                        See the weekly program
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php
    require_once __DIR__ . '/../partials/public/footer.php';
    exit;
}

/* ---------------------------------------------------------------------
   2. THE LIST OF EVENTS
   --------------------------------------------------------------------- */
$page_title = 'Events';
$page_description = 'Retreats, conventions, special services and everything else '
    . SITE_NAME . ' gathers for.';
$canonical = 'events';

require_once __DIR__ . '/../partials/public/header.php';

pageHeader(
    'Events',
    'Everything we gather for, from the big conventions to the quiet evenings '
    . 'that change people. All of it is open to you.',
    [['Home', url('')], ['Program', url('programs')], ['Events', '']]
);

/* Upcoming by default, because that is what a visitor is looking for. The
   ?when= value is checked against a known list before it is used. */
$when = isset($_GET['when']) ? (string) $_GET['when'] : 'upcoming';
if (!in_array($when, ['upcoming', 'past', 'all'], true)) {
    $when = 'upcoming';
}

$date_filter = '';
if ($when === 'upcoming') {
    $date_filter = ' AND start_date >= CURDATE()';
} elseif ($when === 'past') {
    $date_filter = ' AND start_date < CURDATE()';
}

$count_sql = "SELECT COUNT(*) FROM events WHERE status = 'published'" . $date_filter;
$list_sql = "SELECT * FROM events WHERE status = 'published'" . $date_filter
    . ' ORDER BY start_date ' . ($when === 'past' ? 'DESC' : 'ASC') . ', start_time ASC LIMIT ? OFFSET ?';

$current_page = pageNumber();
$pagination = paginate(dbCount($conn, $count_sql), $per_page, $current_page);

$statement = $conn->prepare($list_sql);
$offset = $pagination['offset'];
$statement->bind_param('ii', $per_page, $offset);
$statement->execute();
$result = $statement->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}
$statement->close();
?>

    <section class="py-5">
        <div class="container">

            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <ul class="nav nav-pills gap-2 mb-0">
                    <li class="nav-item">
                        <a class="nav-link<?php echo $when === 'upcoming' ? ' active' : ''; ?>"
                           href="<?php echo url('events'); ?>">Upcoming</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $when === 'past' ? ' active' : ''; ?>"
                           href="<?php echo url('events?when=past'); ?>">Past events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $when === 'all' ? ' active' : ''; ?>"
                           href="<?php echo url('events?when=all'); ?>">Everything</a>
                    </li>
                </ul>
                <a class="btn btn-outline-primary align-self-end" href="<?php echo url('contact'); ?>">
                    <i class="fa-solid fa-bell me-1" aria-hidden="true"></i>Ask to be reminded
                </a>
            </div>

            <?php renderResultCount($pagination, 'events'); ?>

            <?php if (!$events): ?>
                <div class="row justify-content-center mt-4">
                    <div class="col-lg-8">
                        <?php
                        if ($when === 'upcoming') {
                            emptyState('fa-calendar-plus', 'Nothing on the calendar yet',
                                'Every event we plan is announced here first. There is nothing coming up at the '
                                . 'moment, but please check back soon.',
                                '<a class="btn btn-primary" href="' . url('programs') . '">See the weekly program</a>');
                        } elseif ($when === 'past') {
                            emptyState('fa-photo-film', 'No past events to show',
                                'Once an event is over it is filed here, so you can see what you missed.');
                        } else {
                            emptyState('fa-calendar-days', 'No events at all yet',
                                'The first event will appear on this page as soon as it is added.');
                        }
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4 mt-1">
                    <?php foreach ($events as $event): ?>
                        <div class="col-md-6 col-lg-4">
                            <article class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                                <div class="ratio ratio-16x9 zoomable">
                                    <img src="<?php echo uploadUrl($event['image']); ?>" alt=""
                                         class="object-fit-cover">
                                </div>
                                <div class="card-body p-4 d-flex flex-column">
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <?php if ($event['theme'] !== ''): ?>
                                            <span class="badge rounded-pill badge-gold"><?php echo e($event['theme']); ?></span>
                                        <?php endif; ?>
                                        <span class="badge rounded-pill text-bg-secondary">
                                            <?php echo formatDate($event['start_date'], 'M Y'); ?>
                                        </span>
                                    </div>

                                    <h2 class="h5 mb-1">
                                        <a class="text-decoration-none stretched-link" href="<?php echo url('events/' . $event['slug']); ?>">
                                            <?php echo e($event['title']); ?>
                                        </a>
                                    </h2>

                                    <p class="small text-body-secondary mb-2">
                                        <i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>
                                        <?php echo eventDateLabel($event); ?>
                                        <?php if (!empty($event['venue'])): ?>
                                            <br><i class="fa-solid fa-location-dot me-1" aria-hidden="true"></i>
                                            <?php echo e($event['venue']); ?>
                                        <?php endif; ?>
                                    </p>

                                    <?php if (!empty($event['description'])): ?>
                                        <p class="text-body-secondary small mb-0"><?php echo e(excerpt($event['description'], 130)); ?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php renderPagination($pagination, ['when' => $when]); ?>
            <?php endif; ?>
        </div>
    </section>

<?php require_once __DIR__ . '/../partials/public/footer.php'; ?>
