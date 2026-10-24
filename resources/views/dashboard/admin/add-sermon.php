<?php
/**
 * BUBAPC - Admin: add or change one sermon
 *
 * The video box takes any YouTube address, not the long embed code. Paste the
 * address you copied from the browser and it is turned into a proper player
 * when the page is built. If the box is left empty the sermon is still shown,
 * as words only.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'sermons';

$sermon_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$sermon = $sermon_id ? adminRow($conn, 'sermons', $sermon_id) : [];

if ($sermon_id && !$sermon) {
    flash('error', 'That sermon is no longer here.');
    redirect(adminUrl('sermons'));
}

$is_new = $sermon_id === 0;

$values = array_merge([
    'title' => '',
    'slug' => '',
    'preacher' => '',
    'description' => '',
    'scripture' => '',
    'sermon_date' => date('Y-m-d'),
    'video_url' => '',
    'status' => 'published',
], $sermon);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $values['title'] = postText('title');
    $values['preacher'] = postText('preacher');
    $values['description'] = postText('description');
    $values['scripture'] = postText('scripture');
    $values['video_url'] = postText('video_url');
    $values['status'] = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';

    adminCheckRequired($values['title'], 'The title', $errors);
    adminCheckRequired($values['preacher'], 'The preacher', $errors);
    $values['sermon_date'] = adminCheckMoment($_POST['sermon_date'] ?? '', 'The date', $errors);

    /* A pasted YouTube address is checked here rather than in the browser, so
       a bad address is caught while the editor is still on the form. Asking
       for the embed address and being handed nothing back means it was not a
       YouTube link at all. */
    if ($values['video_url'] !== '' && youtubeEmbedUrl($values['video_url']) === '') {
        $errors[] = 'The video address does not look like a YouTube link. It should look like '
            . 'https://www.youtube.com/watch?v=... and nothing else.';
    }

    if (!$errors) {
        /* The address is made from the title and never typed, so it cannot arrive
           with a capital or a space in it. slug() adds a number when another
           record already has this one. On an edit the address that is already saved
           is kept, because it has been printed in a bulletin and shared, and a
           corrected title must not break those links. */
        $values['slug'] = $is_new
            ? slug($conn, 'sermons', $values['title'])
            : $sermon['slug'];


        if ($is_new) {
            $statement = $conn->prepare(
                "INSERT INTO sermons (title, slug, preacher, description, scripture, sermon_date, video_url, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $statement->bind_param(
                'ssssssss',
                $values['title'],
                $values['slug'],
                $values['preacher'],
                $values['description'],
                $values['scripture'],
                $values['sermon_date'],
                $values['video_url'],
                $values['status']
            );
            $statement->execute();
            $sermon_id = (int) dbLastId($conn);
            $statement->close();

            logAction($conn, 'sermon_created', 'The sermon "' . $values['title'] . '"');
            flash('success', 'The sermon "' . $values['title'] . '" has been added.');
        } else {
            $statement = $conn->prepare(
                "UPDATE sermons
                 SET title = ?, slug = ?, preacher = ?, description = ?, scripture = ?, sermon_date = ?,
                     video_url = ?, status = ?
                 WHERE id = ?"
            );
            $statement->bind_param(
                'ssssssssi',
                $values['title'],
                $values['slug'],
                $values['preacher'],
                $values['description'],
                $values['scripture'],
                $values['sermon_date'],
                $values['video_url'],
                $values['status'],
                $sermon_id
            );
            $statement->execute();
            $statement->close();

            logAction($conn, 'sermon_updated', 'The sermon "' . $values['title'] . '"');
            flash('success', 'The sermon "' . $values['title'] . '" has been saved.');
        }

        redirect(adminUrl('sermons'));
    }
}

$page_title = $is_new ? 'Add sermon' : 'Edit sermon';
$page_heading = $is_new ? 'Add a sermon' : 'Edit sermon';
$page_actions = '<a class="btn btn-outline-secondary" href="' . adminUrl('sermons') . '">'
    . '<i class="fa-solid fa-list me-1" aria-hidden="true"></i>Back to the list</a>';

require_once __DIR__ . '/../../partials/admin/header.php';
?>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">

            <?php adminErrors($errors); ?>

            <form method="post" novalidate>
                <?php echo csrfField(); ?>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <?php
                        adminTextField('title', 'Title', $values['title'], '', 'A Voice, Not a Volume');
                        adminTextField('preacher', 'Preacher', $values['preacher'], '', 'Bishop Samuel Okello');
                        adminDateField('sermon_date', 'Date preached', $values['sermon_date']);
                        adminTextField('scripture', 'Scripture', $values['scripture'], 'For example Isaiah 40:1-5', 'Isaiah 40:1-5');
                        adminTextarea('description', 'Notes about this message', $values['description'],
                            'Leave an empty line between paragraphs. Shown on the website under the video.', 10);
                        ?>

                        <h2 class="h6 text-uppercase text-body-secondary mb-3">Recording</h2>
                        <?php
                        adminTextField('video_url', 'YouTube address', $values['video_url'],
                            'Paste the video\'s web address from YouTube. Leave empty if there is no recording.',
                            'https://www.youtube.com/watch?v=');
                        ?>
                        <?php $preview_embed_url = youtubeEmbedUrl($values['video_url']); ?>
                        <?php if ($preview_embed_url !== ''): ?>
                            <div class="ratio ratio-16x9 mb-3">
                                <iframe src="<?php echo e($preview_embed_url); ?>"
                                        title="Preview of the recording" allowfullscreen
                                        allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                            </div>
                            <p class="small text-body-secondary">That is how the video will appear on the website.</p>
                        <?php endif; ?>

                        <?php
                        adminStatusField($values['status']);
                        adminFormButtons(adminUrl('sermons'), $is_new ? 'Add sermon' : 'Save changes');
                        ?>
                    </div>
                </div>
            </form>

            <?php if (!$is_new): ?>
                <div class="card border-danger rounded-4 mt-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Delete this sermon</h2>
                        <p class="small text-body-secondary mb-3">
                            It comes off the website at once. This cannot be undone.
                        </p>
                        <?php echo adminDeleteButton('sermons', $sermon_id, 'the sermon "' . $sermon['title'] . '"'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../partials/admin/footer.php'; ?>
