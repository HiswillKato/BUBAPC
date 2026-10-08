<?php
/**
 * BUBAPC - About the church
 *
 * The story of the church, what we believe and where we are going. The words
 * are in this file rather than in the database, because they are the one part
 * of the website that is written once and almost never changes. Everything
 * that does change - programs, events, people, departments - is read from
 * the database so the admin can keep it up to date.
 */

require_once __DIR__ . '/../app/init.php';

$page_title = 'About the church';
$page_description = 'Who we are, what we believe and what we are trying to do at '
    . SITE_FULL_NAME . '.';
$canonical = 'about';

require_once __DIR__ . '/../app/includes/public/header.php';

page_header(
    'About ' . SITE_NAME,
    SITE_FULL_NAME . ' is a student church on the Busitema University campus. '
    . 'This is who we are, what we hold on to, and what we are reaching for.',
    [['Home', url('')], ['About', '']]
);

/* The two quick facts under the band, so a visitor knows straight away how
   big the church is and when it meets. */
$program_days = count(weekly_program_by_day($conn));
$leader_total = db_count($conn, "SELECT COUNT(*) FROM people
                                WHERE status = 1 AND category <> 'student' AND category <> 'alumnus'");
?>

    <section class="py-5">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <p class="text-uppercase fw-bold small text-gold-dark mb-2">Who we are</p>
                    <h2 class="mb-3">A church for students, and for everyone who comes with them</h2>
                    <p class="lead">
                        <?php echo e(SITE_NAME); ?> stands for
                        <strong><?php echo e(SITE_FULL_NAME); ?></strong>. We gather
                        students from every course and every year, and with them the
                        friends and families who have walked with them.
                    </p>
                    <p class="text-body-secondary">
                        Our reason for existing is written in our motto:
                        <strong><?php echo e(SITE_TAGLINE); ?></strong>. Every student who
                        walks through our door is worth a voice, and every voice is worth
                        hearing &mdash; that is what we mean when we say we are a voice to
                        generations.
                    </p>
                    <p class="text-body-secondary mb-0">
                        We are a Pentecostal church, which means we believe the Bible is
                        the Word of God, we believe in the power of the Holy Spirit, and we
                        believe that worship belongs to everybody present, not only to the
                        people on the platform.
                    </p>
                </div>

                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <p class="text-uppercase fw-bold small text-gold-dark mb-3">At a glance</p>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <span class="text-body-secondary">Where we meet</span>
                                    <span class="fw-semibold text-end"><?php echo e(SITE_LOCATION !== '' ? SITE_LOCATION : 'Busitema, Uganda'); ?></span>
                                </li>
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <span class="text-body-secondary">Days with meetings</span>
                                    <span class="fw-semibold"><?php echo (int) $program_days; ?></span>
                                </li>
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <span class="text-body-secondary">Leaders serving</span>
                                    <span class="fw-semibold"><?php echo (int) $leader_total; ?></span>
                                </li>
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <span class="text-body-secondary">Theme for the year</span>
                                    <span class="fw-semibold text-end"><?php echo e(SITE_TAGLINE); ?></span>
                                </li>
                            </ul>
                            <a class="btn btn-primary w-100 mt-3" href="<?php echo url('programs'); ?>">
                                <i class="fa-solid fa-clock me-1" aria-hidden="true"></i>See the weekly program
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-body-tertiary">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <?php
                    section_heading(
                        'What we hold on to',
                        'What we believe',
                        'These are the truths we teach, and the ones we are asked to stand for.',
                        2
                    );
                    ?>

                    <div class="accordion accordion-flush" id="beliefs">
                        <?php
                        /* Each belief is a heading and its answer, so the accordion
                           keeps the page short while everything stays one click away. */
                        $beliefs = [
                            [
                                'The Bible is the Word of God',
                                'We read it, we teach it and we build our lives on it. It is the final authority '
                                . 'for what we believe, how we live and how we treat one another.',
                            ],
                            [
                                'Jesus Christ is Lord',
                                'We confess Jesus Christ as Lord and Saviour, and we tell anyone who asks '
                                . 'honestly why we follow Him.',
                            ],
                            [
                                'The power of the Holy Spirit',
                                'We are a Pentecostal church. We believe the Holy Spirit is at work among us '
                                . 'today, and that His gifts are given to build up the church and to serve people.',
                            ],
                            [
                                'Baptism and the new life',
                                'We believe in the baptism of the Holy Spirit, and in baptism in water as the '
                                . 'public picture of a life that has turned to Christ.',
                            ],
                            [
                                'The Word and prayer together',
                                'We do not come to a service without expecting to hear from God. Preaching, '
                                . 'worship and prayer are not separate parts of our week; they are the shape of it.',
                            ],
                            [
                                'The family of God',
                                'No student is too new to be known and no believer is too ordinary to be used. '
                                . 'We belong to one another, and we carry one another.',
                            ],
                        ];
                        ?>
                        <?php foreach ($beliefs as $number => $belief): ?>
                            <div class="accordion-item bg-body rounded-4 mb-2 shadow-sm">
                                <h3 class="accordion-header">
                                    <button class="accordion-button<?php echo $number === 0 ? '' : ' collapsed'; ?>"
                                            type="button" data-bs-toggle="collapse"
                                            data-bs-target="#belief-<?php echo (int) $number; ?>"
                                            aria-expanded="<?php echo $number === 0 ? 'true' : 'false'; ?>"
                                            aria-controls="belief-<?php echo (int) $number; ?>">
                                        <?php echo e($belief[0]); ?>
                                    </button>
                                </h3>
                                <div id="belief-<?php echo (int) $number; ?>"
                                     class="accordion-collapse collapse<?php echo $number === 0 ? ' show' : ''; ?>">
                                    <div class="accordion-body text-body-secondary">
                                        <?php echo e($belief[1]); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <?php section_heading('Where we are going', 'Our vision and mission', ''); ?>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <span class="badge rounded-pill text-bg-warning mb-3">
                                <i class="fa-solid fa-eye me-1" aria-hidden="true"></i>Our vision
                            </span>
                            <h3 class="h5">A generation that knows God</h3>
                            <p class="text-body-secondary mb-0">
                                To see every student on this campus know Jesus Christ personally,
                                and to go out and tell somebody else about what they have found.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <span class="badge rounded-pill text-bg-warning mb-3">
                                <i class="fa-solid fa-bullseye me-1" aria-hidden="true"></i>Our mission
                            </span>
                            <h3 class="h5">A home for every student</h3>
                            <p class="text-body-secondary mb-0">
                                To worship, to learn and to be known &mdash; in the services, in the
                                departments, in the families and in the small groups that meet through
                                the week.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <span class="badge rounded-pill text-bg-warning mb-3">
                                <i class="fa-solid fa-heart me-1" aria-hidden="true"></i>Our values
                            </span>
                            <h3 class="h5">The Word, the Spirit, one another</h3>
                            <p class="text-body-secondary mb-0">
                                We take the Bible seriously, we expect the Holy Spirit to work, and we
                                treat people &mdash; students, staff, visitors &mdash; as family.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-brand-soft">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h2 class="mb-3">Come and see for yourself</h2>
                    <p class="text-body-secondary mb-4">
                        The best way to understand a church is to sit in on a service.
                        You are welcome at any of our meetings, and you do not need to
                        arrive with anything but yourself.
                    </p>
                    <div class="d-flex justify-content-center flex-wrap gap-2">
                        <a class="btn btn-gold btn-lg" href="<?php echo url('programs'); ?>">Weekly program</a>
                        <a class="btn btn-outline-primary btn-lg" href="<?php echo url('contact'); ?>">Contact us</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; ?>
