<?php
/**
 * BUBAPC - Admin: add or change one photograph
 *
 * A gallery row without a picture is nothing, so on a new photograph the file
 * box is not optional. On an existing one the picture is already there and the
 * file box only replaces it.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'gallery';

$photo_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$photo = $photo_id ? adminRow($conn, 'gallery', $photo_id) : [];

if ($photo_id && !$photo) {
    flash('error', 'That photograph is no longer here.');
    redirect(adminUrl('gallery'));
}

$is_new = $photo_id === 0;

$values = array_merge([
    'title' => '',
    'caption' => '',
    'image' => '',
    'sort_order' => 0,
    'status' => 1,
], $photo);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $values['title'] = postText('title');
    $values['caption'] = postText('caption');
    $values['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
    $values['status'] = postCheckbox('status') ? 1 : 0;

    if ($is_new && (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE)) {
        $errors[] = 'Please choose a photograph to upload.';
    }

    if (!$errors) {
        $image = saveImage(
            $_FILES['image'] ?? null,
            postCheckbox('remove_image'),
            'gallery',
            $photo['image'] ?? ''
        );
        $values['image'] = $image['path'];
        if ($image['error'] !== '') {
            $errors[] = $image['error'];
        }
    }

    if (!$errors) {
        if ($is_new) {
            $statement = $conn->prepare(
                "INSERT INTO gallery (title, caption, image, sort_order, status) VALUES (?, ?, ?, ?, ?)"
            );
            $statement->bind_param(
                'sssii',
                $values['title'],
                $values['caption'],
                $values['image'],
                $values['sort_order'],
                $values['status']
            );
            $statement->execute();
            $photo_id = (int) dbLastId($conn);
            $statement->close();

            logAction($conn, 'photo_created', 'A photograph was added');
            flash('success', 'The photograph has been added to the gallery.');
        } else {
            $statement = $conn->prepare(
                "UPDATE gallery SET title = ?, caption = ?, image = ?, sort_order = ?, status = ? WHERE id = ?"
            );
            $statement->bind_param(
                'sssiii',
                $values['title'],
                $values['caption'],
                $values['image'],
                $values['sort_order'],
                $values['status'],
                $photo_id
            );
            $statement->execute();
            $statement->close();

            logAction($conn, 'photo_updated', 'A photograph was changed');
            flash('success', 'The photograph has been saved.');
        }

        redirect(adminUrl('gallery'));
    }
}

$page_title = $is_new ? 'Add photograph' : 'Edit photograph';
$page_heading = $is_new ? 'Add a photograph' : 'Edit photograph';
$page_actions = '<a class="btn btn-outline-secondary" href="' . adminUrl('gallery') . '">'
    . '<i class="fa-solid fa-images me-1" aria-hidden="true"></i>Back to the gallery</a>';

require_once __DIR__ . '/../../partials/admin/header.php';
?>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">

            <?php adminErrors($errors); ?>

            <form method="post" enctype="multipart/form-data" novalidate>
                <?php echo csrfField(); ?>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <?php
                        adminTextField('title', 'Title', $values['title'],
                            'Not always needed, but it is what somebody reads first.', 'Sunday main service');
                        adminTextField('caption', 'Caption', $values['caption'],
                            'One line about who is in the picture or what was happening.', 'The room at ten o&rsquo;clock');
                        ?>

                        <h2 class="h6 text-uppercase text-body-secondary mb-3">The photograph</h2>
                        <?php
                        if ($is_new) {
                            adminImageField('image', 'Photograph', '',
                                'Needed. A wide picture looks best, because the gallery is a grid of small boxes.');
                        } else {
                            adminImageField('image', 'Replace this photograph', $values['image'],
                                'A wide picture looks best, because the gallery is a grid of small boxes.');
                        }
                        adminTextField('sort_order', 'Order on the page', (string) $values['sort_order'],
                            'Lower numbers come first. You can also change this from the gallery.', '0');
                        adminCheckbox('status', 'Show this photograph on the website', (int) $values['status'] === 1,
                            'Switch it off to keep a picture without taking it away.');
                        adminFormButtons(adminUrl('gallery'), $is_new ? 'Add to the gallery' : 'Save changes');
                        ?>
                    </div>
                </div>
            </form>

            <?php if (!$is_new): ?>
                <div class="card border-danger rounded-4 mt-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Delete this photograph</h2>
                        <p class="small text-body-secondary mb-3">
                            The picture is deleted from the website as well as from the gallery. This cannot
                            be undone, so keep your own copy.
                        </p>
                        <?php echo adminDeleteButton('gallery', $photo_id, 'this photograph'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../partials/admin/footer.php'; ?>
