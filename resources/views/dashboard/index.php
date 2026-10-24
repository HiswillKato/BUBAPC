<?php
/**
 * BUBAPC - The member's own page
 *
 * Where a church member lands after registering or signing in. There is not
 * much here on purpose: a member may read this and edit their own details, and
 * that is all. Anything to do with running the website lives in /dashboard/admin/, which
 * the admin gate keeps them out of.
 *
 * The point of the page is that the church has a list of its own members with
 * the details they gave, and a member can correct them without phoning the
 * office.
 *
 * The layout is the one a dashboard is read in: the account on the left, the
 * handful of things a member actually comes back for on the right, and no
 * sentence explaining either. Everything a signed in person can reach is one
 * tap away on a phone, because the two cards stack rather than shrink.
 */

require_once __DIR__ . '/../../../app/init.php';

/* A signed in person may use this page whichever kind of account they have. A
   member is the ordinary case; an authorised member who wanders in simply edits
   their own name and number, which is the same form and harmless. */
requirelogin();

$member = currentuser();

/* Some accounts have a page in the directory behind them - a student or a
   leader - and those people can edit that page here. A website admin, or a
   member who signed up themselves, has none, and is shown the account only. */
$person = currentperson($conn);

/* The church links, in the order somebody would use them. A person's own page
   leads the list because that is the thing about this website they own. */
$shortcuts = [];
if ($person) {
    $shortcuts[] = ['fa-id-badge', 'My page on the website', url('people/' . $person['slug'])];
}
$shortcuts[] = ['fa-users', 'Meet the church', url('people')];
$shortcuts[] = ['fa-calendar-days', 'Upcoming events', url('events')];
$shortcuts[] = ['fa-book-bible', 'Daily devotional', url('devotional')];
$shortcuts[] = ['fa-phone', 'Contact the church', url('contact')];

$page_title = 'My account';
require_once __DIR__ . '/../partials/public/header.php';
?>

    <section class="py-4 py-lg-5">
        <div class="container">
            <?php pageheader('My account', '', [['Home', url('')], ['My account', null]]); ?>

            <?php showflashes(); ?>

            <div class="row g-4">
                <div class="col-12 col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <?php echo avatar($member['image'] ?? '', $member['full_name'], 72); ?>
                                <div class="min-width-0">
                                    <h2 class="h5 mb-0"><?php echo e($member['full_name']); ?></h2>
                                    <p class="text-body-secondary text-break mb-0"><?php echo e($member['email']); ?></p>
                                </div>
                            </div>

                            <dl class="row mb-0 mt-4">
                                <dt class="col-sm-4 text-body-secondary fw-normal">Phone</dt>
                                <dd class="col-sm-8">
                                    <?php if (!empty($member['phone'])): ?>
                                        <?php echo e($member['phone']); ?>
                                    <?php else: ?>
                                        <span class="text-body-secondary">Not given yet</span>
                                    <?php endif; ?>
                                </dd>

                                <dt class="col-sm-4 text-body-secondary fw-normal mb-0">Member since</dt>
                                <dd class="col-sm-8 mb-0"><?php echo formatdate($member['created'], 'j F Y'); ?></dd>
                            </dl>

                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <?php if ($person): ?>
                                    <a href="<?php echo url('dashboard/person'); ?>" class="btn btn-primary">
                                        <i class="fa-solid fa-id-card me-1" aria-hidden="true"></i>
                                        My church profile
                                    </a>
                                    <a href="<?php echo url('dashboard/profile'); ?>" class="btn btn-outline-primary">
                                        <i class="fa-solid fa-key me-1" aria-hidden="true"></i>
                                        Change password
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo url('dashboard/profile'); ?>" class="btn btn-primary">
                                        <i class="fa-solid fa-user-pen me-1" aria-hidden="true"></i>
                                        Edit my details
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body pb-2">
                            <h2 class="h6 text-uppercase small text-body-secondary mb-0">Shortcuts</h2>
                        </div>

                        <div class="list-group list-group-flush">
                            <?php foreach ($shortcuts as $shortcut): ?>
                                <a class="list-group-item list-group-item-action d-flex align-items-center gap-3"
                                   href="<?php echo $shortcut[2]; ?>">
                                    <i class="fa-solid <?php echo e($shortcut[0]); ?> text-gold" aria-hidden="true"></i>
                                    <span class="flex-grow-1"><?php echo e($shortcut[1]); ?></span>
                                    <i class="fa-solid fa-chevron-right small text-body-secondary" aria-hidden="true"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <form method="post" action="<?php echo url('logout'); ?>" class="mt-3">
                        <?php echo csrffield(); ?>
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <i class="fa-solid fa-right-from-bracket me-1" aria-hidden="true"></i>
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../partials/public/footer.php'; ?>
