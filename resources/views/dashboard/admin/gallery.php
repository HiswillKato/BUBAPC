<?php
/**
 * BUBAPC - Admin: the gallery
 *
 * The gallery is a set of pictures, so this page is a grid of pictures rather
 * than a table. Each one carries its order and a switch, which is all a
 * photographer needs to put a good set in front of a visitor.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'gallery';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifycsrf();

    $action = posttext('action');
    $photo_id = (int) ($_POST['id'] ?? 0);
    $photo = adminrow($conn, 'gallery', $photo_id);

    if (!$photo) {
        flash('error', 'That photograph is no longer here. It may already have been deleted.');
        redirect(adminurl('gallery'));
    }

    if ($action === 'delete') {
        deleteuploadedimage($photo['image']);

        $statement = $conn->prepare("DELETE FROM gallery WHERE id = ?");
        $statement->bind_param('i', $photo_id);
        $statement->execute();
        $statement->close();

        logaction($conn, 'photo_deleted', 'A photograph' . ($photo['title'] !== '' ? ' titled "' . $photo['title'] . '"' : '') . ' was deleted');
        flash('success', 'The photograph' . ($photo['title'] !== '' ? ' "' . $photo['title'] . '"' : '') . ' has been deleted.');
    } elseif ($action === 'toggle') {
        $new_status = (int) $photo['status'] === 1 ? 0 : 1;

        $statement = $conn->prepare("UPDATE gallery SET status = ? WHERE id = ?");
        $statement->bind_param('ii', $new_status, $photo_id);
        $statement->execute();
        $statement->close();

        flash('success', $new_status === 1
            ? 'The photograph is now shown on the website.'
            : 'The photograph is now hidden from the website.');
    } elseif ($action === 'set_order') {
        $new_order = max(0, min(999, (int) ($_POST['display_order'] ?? 0)));

        $statement = $conn->prepare("UPDATE gallery SET sort_order = ? WHERE id = ?");
        $statement->bind_param('ii', $new_order, $photo_id);
        $statement->execute();
        $statement->close();

        flash('success', 'The order has been saved.');
    }

    redirect(adminurl('gallery'));
}

$show_hidden = isset($_GET['show']) && $_GET['show'] === 'hidden';

$where = $show_hidden ? ' WHERE status = 0' : '';
$total_photos = dbcount($conn, "SELECT COUNT(*) FROM gallery" . $where);

$per_page = 24;
$current_page = pagenumber();
$pagination = paginate($total_photos, $per_page, $current_page);

$statement = $conn->prepare(
    "SELECT * FROM gallery" . $where . " ORDER BY sort_order ASC, id ASC LIMIT ? OFFSET ?"
);
$offset = $pagination['offset'];
$statement->bind_param('ii', $per_page, $offset);
$statement->execute();
$result = $statement->get_result();

$photos = [];
while ($row = $result->fetch_assoc()) {
    $photos[] = $row;
}
$statement->close();

$page_title = 'Gallery';
$page_heading = 'Gallery';
$page_actions = '<a class="btn btn-primary" href="' . adminurl('gallery/form.php') . '">'
    . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add photograph</a>';

require_once __DIR__ . '/../../partials/admin/header.php';
?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <ul class="nav nav-pills gap-2 mb-0">
            <li class="nav-item">
                <a class="nav-link<?php echo $show_hidden ? '' : ' active'; ?>" href="<?php echo adminurl('gallery'); ?>">
                    All photographs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link<?php echo $show_hidden ? ' active' : ''; ?>"
                   href="<?php echo adminurl('gallery?show=hidden'); ?>">Hidden only</a>
            </li>
        </ul>
        <p class="text-body-secondary small mb-0">
            <?php echo e($total_photos); ?> <?php echo $total_photos === 1 ? 'photograph' : 'photographs'; ?>
            <?php echo $show_hidden ? 'hidden' : 'on the website'; ?>
        </p>
    </div>

<?php if (!$photos): ?>
    <?php
    emptystate(
        'fa-images',
        $show_hidden ? 'Nothing is hidden' : 'No photographs yet',
        $show_hidden
            ? 'Every photograph is on the website.'
            : 'The gallery is the page visitors look at to see what the church actually looks like. '
                . 'Photographs from a Sunday, a convention or a students&rsquo; meeting are all worth having.',
        '<a class="btn btn-primary" href="' . adminurl('gallery/form.php') . '">'
        . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add a photograph</a>',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="row g-3 mb-4">
        <?php foreach ($photos as $photo): ?>
            <div class="col-sm-6 col-md-4 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <a href="<?php echo adminurl('gallery/form.php?id=' . (int) $photo['id']); ?>"
                       class="ratio ratio-4x3">
                        <img src="<?php echo uploadurl($photo['image']); ?>" alt=""
                             class="rounded-top-4 object-fit-cover w-100">
                    </a>
                    <div class="card-body p-3">
                        <p class="fw-semibold small mb-1">
                            <?php echo e($photo['title'] !== '' ? $photo['title'] : 'Untitled'); ?>
                        </p>
                        <?php if ($photo['caption'] !== ''): ?>
                            <p class="small text-body-secondary mb-2"><?php echo e(excerpt($photo['caption'], 60)); ?></p>
                        <?php else: ?>
                            <p class="small text-body-secondary mb-2">No caption</p>
                        <?php endif; ?>

                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <?php echo adminorderfield('gallery', $photo['id'], $photo['sort_order']); ?>
                            <span class="badge rounded-pill <?php echo (int) $photo['status'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                <?php echo (int) $photo['status'] === 1 ? 'Shown' : 'Hidden'; ?>
                            </span>
                        </div>

                        <?php
                            echo adminactiongroup(
                                admintogglebutton('gallery', $photo['id'], $photo['status'], 'Show', 'Hide')
                                . admindeletebutton('gallery', $photo['id'], 'this photograph'),
                                'justify-content-start'
                            );
                            ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php renderpagination($pagination, ['show' => $show_hidden ? 'hidden' : '']); ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../../partials/admin/footer.php'; ?>
