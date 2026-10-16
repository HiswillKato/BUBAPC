<?php
/**
 * BUBAPC - About the church
 *
 * The story of the church. The words
 * are in this file rather than in the database, because they are the one part
 * of the website that is written once and almost never changes. Everything
 * that does change - programs, events, people, departments - is read from
 * the database so the admin can keep it up to date.
 */

require_once __DIR__ . '/../app/init.php';

$page_title = 'About the church';
$page_description = 'Who we are and what you will find when you visit '
    . SITE_FULL_NAME . '.';
$canonical = 'about';

require_once __DIR__ . '/../app/includes/public/header.php';

page_header(
    'About ' . SITE_NAME,
    SITE_FULL_NAME . ' is a student church on the Busitema University campus. '
    . 'This is who we are, and this is what you will find when you visit.',
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
