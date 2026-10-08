<?php
/**
 * BUBAPC - The photo gallery
 *
 * A plain grid of photographs. Clicking one opens Bootstrap's own modal, so
 * there is no lightbox script and no lightbox CSS to look after: the markup
 * below is all it takes, and it works on a keyboard and on a phone.
 */

require_once __DIR__ . '/../app/init.php';

$page_title = 'Gallery';
$page_description = 'Photographs from the life of ' . SITE_NAME . '.';
$canonical = 'gallery';

require_once __DIR__ . '/../app/includes/public/header.php';

page_header(
    'Gallery',
    'What our services, retreats and ordinary weeks look like. Click any photograph '
    . 'to see it larger.',
    [['Home', url('')], ['Media', url('sermons')], ['Gallery', '']]
);

$per_page = 24;
$current_page = page_number();
$pagination = paginate(db_count($conn, "SELECT COUNT(*) FROM gallery WHERE status = 1"), $per_page, $current_page);

$list_sql = "SELECT * FROM gallery WHERE status = 1
             ORDER BY sort_order ASC, id DESC LIMIT ? OFFSET ?";
$offset = $pagination['offset'];

$statement = $conn->prepare($list_sql);
$statement->bind_param('ii', $per_page, $offset);
$statement->execute();
$result = $statement->get_result();

$photos = [];
while ($row = $result->fetch_assoc()) {
    $photos[] = $row;
}
$statement->close();
?>

    <section class="py-5">
        <div class="container">
            <?php render_result_count($pagination, 'photos'); ?>

            <?php if (!$photos): ?>
                <div class="row justify-content-center mt-4">
                    <div class="col-lg-8">
                        <?php
                        empty_state('fa-images', 'The gallery is empty at the moment',
                            'Photographs from our services, retreats and ordinary weeks will appear here '
                            . 'as soon as they are added.');
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3 mt-1">
                    <?php foreach ($photos as $photo): ?>
                        <?php
                        /* The words shown above the photograph in the modal. The
                           button carries them as data-gallery-caption, which is
                           the hook main.js is listening for. */
                        $photo_words = trim($photo['title'] !== '' ? $photo['title'] : $photo['caption']);
                        ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <button class="btn p-0 border-0 bg-transparent w-100 text-start" type="button"
                                    data-bs-toggle="modal" data-bs-target="#gallery-photo"
                                    data-gallery-image="<?php echo upload_url($photo['image']); ?>"
                                    data-gallery-caption="<?php echo e($photo_words); ?>">
                                <span class="d-block ratio ratio-1x1 rounded-4 overflow-hidden zoomable">
                                    <img src="<?php echo upload_url($photo['image']); ?>"
                                         alt="<?php echo e($photo['caption'] !== '' ? $photo['caption'] : $photo['title']); ?>"
                                         class="object-fit-cover">
                                </span>
                                <?php if ($photo_words !== ''): ?>
                                    <span class="d-block small text-start mt-2">
                                        <?php if ($photo['title'] !== ''): ?>
                                            <span class="fw-semibold d-block"><?php echo e($photo['title']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($photo['caption'] !== '' && $photo['caption'] !== $photo['title']): ?>
                                            <span class="text-body-secondary"><?php echo e($photo['caption']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php render_pagination($pagination); ?>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($photos): ?>
        <!-- The one modal every photograph opens. main.js puts the chosen
             picture and its words into it, and Bootstrap does the rest. -->
        <div class="modal fade" id="gallery-photo" tabindex="-1" aria-labelledby="gallery-photo-caption" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header">
                        <h2 class="modal-title h6 mb-0" id="gallery-photo-caption"></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <img src="<?php echo asset('images/placeholder.svg'); ?>" alt=""
                             class="img-fluid rounded-bottom-4">
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; ?>
