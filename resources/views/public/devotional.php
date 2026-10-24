<?php
/**
 * BUBAPC - Today's devotional
 *
 * The one page a visitor is most likely to open every day, so it stays
 * simple: the message, the scripture it is built on, a little further study
 * and the share buttons. Everything here comes from current_devotional(),
 * which is the same function the homepage uses, so the two can never disagree
 * about which message is today's.
 */

require_once __DIR__ . '/../../../app/init.php';

$page_title = 'Today’s devotional';
$page_description = 'A short daily message from the Word of God at ' . SITE_NAME . '.';
$canonical = 'devotional';

require_once __DIR__ . '/../../../app/includes/public/header.php';

$devotional = current_devotional($conn);

if ($devotional) {
    $page_title = $devotional['title'];
    $page_description = excerpt(strip_tags(html_entity_decode($devotional['message'])), 160);
}

/* The heading and the last breadcrumb are the same words, so the title is
   worked out once and used twice. The breadcrumb ends on the message being
   read, the same way the archive's single-message page does. */
$devotional_title = $devotional ? $devotional['title'] : 'Today’s devotional';

page_header(
    $devotional_title,
    $devotional
        ? 'Written for ' . format_date($devotional['devotional_date'], 'l, j F Y') . '. Read it slowly, and keep what God shows you.'
        : 'One short message from the Word of God, here every morning.',
    [['Home', url('')], ['Devotional', url('devotionals')], [$devotional_title, '']]
);
?>

    <section class="py-5">
        <div class="container">

            <?php if ($devotional): ?>
                <?php render_devotional($devotional, url('devotionals/' . $devotional['slug'])); ?>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-5 pt-4 border-top">
                    <a class="btn btn-outline-primary" href="<?php echo url('devotionals'); ?>">
                        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Devotional archive
                    </a>
                    <a class="btn btn-primary" href="<?php echo url('contact'); ?>">
                        <i class="fa-solid fa-receipt me-1" aria-hidden="true"></i>Ask us to pray with you
                    </a>
                </div>
            <?php else: ?>
                <div class="row justify-content-center">
                    <div class="col-lg-7">
                        <?php
                        empty_state('fa-book-bible', 'Today’s devotional is being written',
                            'We write a short message for every day, drawn from the Bible and from what God '
                            . 'has been doing at ' . SITE_NAME . '. Ours for today is on its way.',
                            '<a class="btn btn-primary" href="' . url('devotionals') . '">Read an earlier message</a>');
                        ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>

<?php require_once __DIR__ . '/../../../app/includes/public/footer.php'; ?>
