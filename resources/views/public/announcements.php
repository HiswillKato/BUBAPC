<?php
/**
 * BUBAPC - Announcements
 *
 * The short notices the church publishes. Each one is given an anchor
 * (#announcement-4) so a notice can be linked to directly from a message or an
 * email, which is why a notice does not need a page of its own.
 */

require_once __DIR__ . '/../../../app/init.php';

$page_title = 'Announcements';
$page_description = 'News and notices from ' . SITE_NAME . '.';
$canonical = 'announcements';

require_once __DIR__ . '/../partials/public/header.php';

page_header(
    'Announcements',
    'News from the church, newest first. Anything you need to know about a '
    . 'meeting, a trip or a change will be posted here.',
    [['Home', url('')], ['Media', url('sermons')], ['Announcements', '']]
);

$announcements = recent_announcements($conn, 50);
?>

    <section class="py-5">
        <div class="container">
            <?php if (!$announcements): ?>
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <?php
                        empty_state('fa-bullhorn', 'There is no news at the moment',
                            'When there is something worth telling you, it will be posted here. '
                            . 'In the meantime, come and join us in person.',
                            '<a class="btn btn-primary" href="' . url('programs') . '">See the weekly program</a>');
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($announcements as $notice): ?>
                        <div class="col-lg-8" id="announcement-<?php echo (int) $notice['id']; ?>">
                            <article class="card border-0 shadow-sm rounded-4 mb-4">
                                <div class="card-body p-4">
                                    <p class="small text-uppercase fw-bold text-gold-dark mb-2">
                                        <i class="fa-solid fa-bullhorn me-1" aria-hidden="true"></i>
                                        <?php echo format_date($notice['publish_date'], 'j F Y'); ?>
                                    </p>

                                    <h2 class="h4 mb-2"><?php echo e($notice['title']); ?></h2>

                                    <div class="text-body-secondary">
                                        <?php echo paragraphs($notice['content']); ?>
                                    </div>

                                    <div class="mt-3"><?php share_bar($notice['title'], url('announcements#announcement-' . $notice['id'])); ?></div>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

<?php require_once __DIR__ . '/../partials/public/footer.php'; ?>
