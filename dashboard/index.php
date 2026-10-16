<?php
/**
 * BUBAPC - The member's own page
 *
 * Where a church member lands after registering or signing in. There is not
 * much here on purpose: a member may read this and edit their own details, and
 * that is all. Anything to do with running the website lives in /dashboard/admin/, which
 * require_staff() keeps them out of.
 *
 * The point of the page is that the church has a list of its own members with
 * the details they gave, and a member can correct them without phoning the
 * office.
 */

require_once __DIR__ . '/../app/init.php';

/* A signed in person may use this page whichever kind of account they have. A
   member is the ordinary case; a member of staff who wanders in simply edits
   their own name and number, which is the same form and harmless. */
require_login();

$member = current_user();

/* Some accounts have a page in the directory behind them - a student or a
   leader - and those people can edit that page here. A website admin, or a
   member who signed up themselves, has none, and is shown the account only. */
$person = current_person($conn);

$page_title = 'My account';
require_once __DIR__ . '/../app/includes/public/header.php';
?>

    <section class="py-5">
        <div class="container">
            <?php page_header(
                'Your account',
                'Signed in as ' . $member['full_name'] . '.',
                [['Home', url('')], ['My account', null]]
            ); ?>

            <?php show_flashes(); ?>

            <div class="row g-4">
                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow rounded-4">
                        <div class="card-body p-4 p-md-5">
                            <h2 class="h5 mb-4">Your details</h2>

                            <dl class="row mb-0">
                                <dt class="col-sm-4 text-body-secondary fw-normal">Name</dt>
                                <dd class="col-sm-8"><?php echo e($member['full_name']); ?></dd>

                                <dt class="col-sm-4 text-body-secondary fw-normal">Email</dt>
                                <dd class="col-sm-8"><?php echo e($member['email']); ?></dd>

                                <dt class="col-sm-4 text-body-secondary fw-normal">Phone</dt>
                                <dd class="col-sm-8">
                                    <?php echo $member['phone'] !== '' ? e($member['phone']) : '<span class="text-body-secondary">Not given yet</span>'; ?>
                                </dd>

                                <dt class="col-sm-4 text-body-secondary fw-normal">Member since</dt>
                                <dd class="col-sm-8">
                                    <?php
                                    /* created is a database column, so a
                                       short date written by the database is
                                       already in the right order to be
                                       formatted. */
                                    echo e(date('j F Y', strtotime($member['created'])));
                                    ?>
                                </dd>
                            </dl>

                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <?php if ($person): ?>
                                    <a href="<?php echo url('dashboard/person'); ?>" class="btn btn-primary">
                                        <i class="fa-solid fa-id-card me-1" aria-hidden="true"></i>
                                        My church profile
                                    </a>
                                    <a href="<?php echo url('dashboard/profile'); ?>" class="btn btn-outline-primary">
                                        <i class="fa-solid fa-user-pen me-1" aria-hidden="true"></i>
                                        Account &amp; password
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo url('dashboard/profile'); ?>" class="btn btn-primary">
                                        <i class="fa-solid fa-user-pen me-1" aria-hidden="true"></i>
                                        Change my details
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="card border-0 shadow rounded-4">
                        <div class="card-body p-4">
                            <h2 class="h6 text-uppercase small text-body-secondary mb-3">Church</h2>

                            <ul class="list-unstyled mb-0 d-grid gap-2">
                                <?php if ($person): ?>
                                    <li>
                                        <a class="d-flex align-items-center gap-2 text-decoration-none"
                                           href="<?php echo url('people/' . $person['slug']); ?>">
                                            <i class="fa-solid fa-id-badge text-body-secondary" aria-hidden="true"></i>
                                            My page on the site
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <li>
                                    <a class="d-flex align-items-center gap-2 text-decoration-none"
                                       href="<?php echo url('people'); ?>">
                                        <i class="fa-solid fa-users text-body-secondary" aria-hidden="true"></i>
                                        Meet the church
                                    </a>
                                </li>
                                <li>
                                    <a class="d-flex align-items-center gap-2 text-decoration-none"
                                       href="<?php echo url('events'); ?>">
                                        <i class="fa-solid fa-calendar-day text-body-secondary" aria-hidden="true"></i>
                                        Upcoming events
                                    </a>
                                </li>
                                <li>
                                    <a class="d-flex align-items-center gap-2 text-decoration-none"
                                       href="<?php echo url('devotionals'); ?>">
                                        <i class="fa-solid fa-book-bible text-body-secondary" aria-hidden="true"></i>
                                        Daily devotional
                                    </a>
                                </li>
                                <li>
                                    <a class="d-flex align-items-center gap-2 text-decoration-none"
                                       href="<?php echo url('contact'); ?>">
                                        <i class="fa-solid fa-phone text-body-secondary" aria-hidden="true"></i>
                                        Contact the church
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <form method="post" action="<?php echo url('logout'); ?>" class="mt-3">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <i class="fa-solid fa-right-from-bracket me-1" aria-hidden="true"></i>
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; ?>
