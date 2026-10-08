<?php
/**
 * BUBAPC - The homepage
 *
 * This is the first page a visitor sees. It stays welcoming even before the
 * church has added any content: the verse of the day comes from a small list
 * kept in app/functions.php, so it always has something to show, and the
 * program and event sections politely say "coming soon" instead of breaking.
 *
 * As the admin panel is used, each section fills itself in:
 *   - weekly_program_by_day() lists the services from the programs table
 *   - next_events() shows the upcoming events as cards with a countdown
 *   - current_devotional() brings today's message to the front door
 * The page never guesses or mixes its own content into those sections.
 *
 * Every class in the markup is a Bootstrap class, except the two custom
 * colour utilities that belong to the BUBAPC palette: bg-brand and text-gold.
 */

require_once __DIR__ . '/../app/init.php';

$page_title   = 'Home';
$page_description = 'Welcome to ' . SITE_FULL_NAME . ' — ' . SITE_TAGLINE . '.';
$body_class   = 'page-home';

require_once __DIR__ . '/../app/includes/public/header.php';

/* Everything the page shows is read once, at the top, and then used in one
   place each. Reading any of it twice would run the same query twice. */
$today_verse      = verse_of_the_day();
$programs_by_day  = weekly_program_by_day($conn);
$has_program      = count($programs_by_day) > 0;
$upcoming_events  = next_events($conn, 3);
$today_devotional = current_devotional($conn);
$notices          = recent_announcements($conn, 3);
?>

    <!-- ============ HERO ============ -->
    <section class="bg-brand text-white py-5">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <h1 class="display-5 mb-3">
                        Welcome to
                        <span class="text-warning"><?php echo e(SITE_NAME); ?></span>
                    </h1>
                    <p class="lead mb-4">
                        <?php echo e(SITE_FULL_NAME); ?> &mdash; <?php echo e(SITE_TAGLINE); ?>.
                        We are a family of students growing in the knowledge of God
                        and making Him known on every campus and in every home.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-gold btn-lg" href="<?php echo url('devotional'); ?>">
                            <i class="fa-solid fa-book-bible me-1" aria-hidden="true"></i>Today&rsquo;s Devotional
                        </a>
                        <a class="btn btn-outline-light btn-lg" href="<?php echo url('events'); ?>">
                            <i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>Upcoming Events
                        </a>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card border-0 shadow rounded-4 text-body">
                        <div class="card-body p-4">
                            <p class="text-uppercase fw-bold small text-gold-dark mb-3">
                                <i class="fa-solid fa-quote-left me-2" aria-hidden="true"></i>Verse of the Day
                            </p>
                            <blockquote class="mb-3">
                                <p class="fs-5 fst-italic mb-2">&ldquo;<?php echo e($today_verse['text']); ?>&rdquo;</p>
                                <footer class="small fw-bold text-brand">
                                    <?php echo e($today_verse['reference']); ?>
                                    <span class="fw-normal text-body-secondary">(<?php echo e($today_verse['version']); ?>)</span>
                                </footer>
                            </blockquote>
                            <a class="btn btn-soft btn-sm" href="<?php echo url('devotional'); ?>">
                                Read today&rsquo;s devotional
                                <i class="fa-solid fa-arrow-right-long ms-1" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ THIS WEEK ============ -->
    <section class="py-5">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <?php section_heading('Our weekly program', 'This week at ' . SITE_NAME, 'Every meeting you can join us at, in one place.'); ?>
                <a class="btn btn-outline-primary align-self-end" href="<?php echo url('programs'); ?>">
                    Full program <i class="fa-solid fa-arrow-right-long ms-1" aria-hidden="true"></i>
                </a>
            </div>

            <?php if (!$has_program): ?>
                <?php
                empty_state('fa-clock', 'The weekly program is coming soon',
                    'We are putting the finishing touches on our schedule. Please check back shortly.');
                ?>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($programs_by_day as $day_label => $day_programs): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm rounded-4">
                                <div class="card-body p-4">
                                    <p class="text-uppercase fw-bold small text-gold-dark mb-3"><?php echo e($day_label); ?></p>
                                    <ul class="list-unstyled d-grid gap-3 mb-0">
                                        <?php foreach ($day_programs as $program): ?>
                                            <li class="d-flex align-items-center gap-3">
                                                <span class="badge rounded-pill text-bg-primary px-3 py-2">
                                                    <?php echo format_time($program['start_time']); ?>
                                                </span>
                                                <div>
                                                    <p class="fw-semibold mb-0"><?php echo e($program['title'] ?: 'Service'); ?></p>
                                                    <?php if (!empty($program['end_time'])): ?>
                                                        <p class="small text-body-secondary mb-0">
                                                            <?php echo format_time($program['end_time']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============ UPCOMING EVENTS ============ -->
    <section class="py-5 bg-body-tertiary">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <?php section_heading('Upcoming events', 'Mark your calendar', 'Everything the church is gathering for.'); ?>
                <a class="btn btn-outline-primary align-self-end" href="<?php echo url('events'); ?>">
                    All events <i class="fa-solid fa-arrow-right-long ms-1" aria-hidden="true"></i>
                </a>
            </div>

            <?php if (!$upcoming_events): ?>
                <?php
                empty_state('fa-calendar-plus', 'No events scheduled yet',
                    'Watch this space — the next gathering will be announced here as soon as it is planned.');
                ?>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($upcoming_events as $index => $event): ?>
                        <div class="col-md-6 col-lg-4">
                            <article class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                                <div class="ratio ratio-16x9 zoomable">
                                    <img src="<?php echo upload_url($event['image']); ?>" alt=""
                                         class="object-fit-cover">
                                </div>
                                <div class="card-body p-4">
                                    <?php if ($event['theme'] !== ''): ?>
                                        <span class="badge rounded-pill badge-gold mb-2">
                                            <i class="fa-solid fa-star me-1" aria-hidden="true"></i>
                                            <?php echo e($event['theme']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <h3 class="h5 mb-1">
                                        <a class="stretched-link text-decoration-none" href="<?php echo url('events/' . $event['slug']); ?>">
                                            <?php echo e($event['title']); ?>
                                        </a>
                                    </h3>
                                    <p class="small text-body-secondary mb-2">
                                        <i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>
                                        <?php echo event_date_label($event); ?>
                                        <?php if (!empty($event['venue'])): ?>
                                            <br><i class="fa-solid fa-location-dot me-1" aria-hidden="true"></i>
                                            <?php echo e($event['venue']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!empty($event['description'])): ?>
                                        <p class="mb-3"><?php echo e(excerpt($event['description'], 110)); ?></p>
                                    <?php endif; ?>
                                    <?php
                                    /* The nearest event gets the ticking countdown; the others
                                       only need the date, because a row of countdown boxes is
                                       too much noise to read. */
                                    $moment = event_moment($event);
                                    if ($index === 0 && $moment !== ''):
                                    ?>
                                        <div class="d-flex flex-wrap gap-2 pt-2" data-countdown="<?php echo e($moment); ?>"></div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============ TODAY'S DEVOTIONAL ============ -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <?php
                    if ($today_devotional):
                        section_heading(
                            $today_devotional['title'],
                            'Devotional for ' . format_date($today_devotional['devotional_date'], 'j F Y'),
                            ''
                        );
                    else:
                        section_heading('A quiet word every morning', 'Devotional', '');
                    endif;
                    ?>

                    <?php if ($today_devotional): ?>
                        <?php if (!empty($today_devotional['scripture_text'])): ?>
                            <div class="card border-0 border-start border-4 border-gold bg-gold-soft rounded-4 mb-4">
                                <div class="card-body">
                                    <p class="fst-italic mb-2">&ldquo;<?php echo e($today_devotional['scripture_text']); ?>&rdquo;</p>
                                    <p class="small fw-bold mb-0 text-gold-dark"><?php echo e($today_devotional['scripture_reference']); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <p class="lead"><?php echo e(excerpt($today_devotional['message'], 220)); ?></p>

                        <a class="btn btn-primary" href="<?php echo url('devotionals/' . $today_devotional['slug']); ?>">
                            Read the whole message
                            <i class="fa-solid fa-arrow-right-long ms-1" aria-hidden="true"></i>
                        </a>
                    <?php else: ?>
                        <p class="lead">
                            A short message from the Word of God, written for today and read
                            by students all over the world. Ours is on its way.
                        </p>
                        <a class="btn btn-primary" href="<?php echo url('devotionals'); ?>">
                            Open the devotional archive
                            <i class="fa-solid fa-arrow-right-long ms-1" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <p class="text-uppercase fw-bold small text-gold-dark mb-3">From the church</p>

                            <?php if (!$notices): ?>
                                <p class="text-body-secondary mb-0">
                                    There is no news to report at the moment. Come and join us in
                                    person &mdash; you will be made very welcome.
                                </p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($notices as $notice): ?>
                                        <li class="list-group-item px-0 py-3">
                                            <a class="fw-semibold text-decoration-none d-block"
                                               href="<?php echo url('announcements#announcement-' . $notice['id']); ?>">
                                                <?php echo e($notice['title']); ?>
                                            </a>
                                            <span class="small text-body-secondary">
                                                <?php echo format_date($notice['publish_date']); ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <a class="btn btn-soft btn-sm mt-3" href="<?php echo url('announcements'); ?>">
                                All announcements
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ HOW TO FIND US ============ -->
    <section class="bg-brand-soft py-5">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <?php section_heading('We would love to worship with you', 'Plan your visit', ''); ?>
                    <p class="text-body-secondary mb-4">
                        <?php if (SITE_LOCATION !== ''): ?>
                            You will find us at <strong><?php echo e(SITE_LOCATION); ?></strong>.
                        <?php endif; ?>
                        Whether you are a student fresh on campus or a long-time friend,
                        there is a seat waiting for you.
                    </p>
                    <div class="d-flex justify-content-center flex-wrap gap-2">
                        <a class="btn btn-gold btn-lg" href="<?php echo url('contact'); ?>">
                            <i class="fa-solid fa-location-dot me-1" aria-hidden="true"></i>Find us &amp; get in touch
                        </a>
                        <a class="btn btn-outline-primary btn-lg" href="<?php echo url('leadership'); ?>">
                            Meet our leadership
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; ?>
