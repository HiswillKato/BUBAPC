<?php
/**
 * BUBAPC - Admin: add or change one sermon
 *
 * The video box takes any YouTube address, not the long embed code. Paste the
 * address you copied from the browser and it is turned into a proper player
 * when the page is built. If the box is left empty the sermon is still shown,
 * as words only.
 */

require_once __DIR__ . '/../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../app/includes/admin/parts.php';

$active_module = 'sermons';

$sermon_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$sermon = $sermon_id ? admin_row($conn, 'sermons', $sermon_id) : [];

if ($sermon_id && !$sermon) {
    flash('error', 'That sermon is no longer here.');
    redirect(admin_url('sermons'));
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
    'status' => 'draft',
], $sermon);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values['title'] = post_text('title');
    $values['preacher'] = post_text('preacher');
    $values['description'] = post_text('description');
    $values['scripture'] = post_text('scripture');
    $values['video_url'] = post_text('video_url');
    $values['status'] = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';

    admin_check_required($values['title'], 'The title', $errors);
    admin_check_required($values['preacher'], 'The preacher', $errors);
    $values['sermon_date'] = admin_check_moment($_POST['sermon_date'] ?? '', 'The date', $errors);

    /* A pasted YouTube address is checked here rather than in the browser, so
       a bad address is caught while the editor is still on the form. Asking
       for the embed address and being handed nothing back means it was not a
       YouTube link at all. */
    if ($values['video_url'] !== '' && youtube_embed_url($values['video_url']) === '') {
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
            $sermon_id = (int) db_last_id($conn);
            $statement->close();

            log_action($conn, 'sermon_created', 'The sermon "' . $values['title'] . '"');
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

            log_action($conn, 'sermon_updated', 'The sermon "' . $values['title'] . '"');
            flash('success', 'The sermon "' . $values['title'] . '" has been saved.');
        }

        redirect(admin_url('sermons'));
    }
}

$page_title = $is_new ? 'Add sermon' : 'Edit sermon';
$page_heading = $is_new ? 'Add a sermon' : 'Edit sermon';
$page_actions = '<a class="btn btn-outline-secondary" href="' . admin_url('sermons') . '">'
    . '<i class="fa-solid fa-list me-1" aria-hidden="true"></i>Back to the list</a>';

require_once __DIR__ . '/../../app/includes/admin/header.php';
?>

    <div class="row g-4">
        <div class="col-lg-8">

            <?php admin_errors($errors); ?>

            <form method="post" novalidate>
                <?php echo csrf_field(); ?>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <?php
                        admin_text_field('title', 'Title', $values['title'], '', 'A Voice, Not a Volume', 'data-slug-source');
                        admin_text_field('preacher', 'Preacher', $values['preacher'], '', 'Bishop Samuel Okello');
                        admin_date_field('sermon_date', 'Date preached', $values['sermon_date']);
                        admin_text_field('scripture', 'Scripture', $values['scripture'], 'For example Isaiah 40:1-5', 'Isaiah 40:1-5');
                        admin_textarea('description', 'Notes about this message', $values['description'],
                            'Leave an empty line between paragraphs. Shown on the website under the video.', 10);
                        ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 text-uppercase text-body-secondary mb-3">Recording</h2>
                        <?php
                        admin_text_field('video_url', 'YouTube address', $values['video_url'],
                            'Paste the address of the video, not the embed code. Leave empty if there is no recording.',
                            'https://www.youtube.com/watch?v=');
                        ?>
                        <?php $preview_embed_url = youtube_embed_url($values['video_url']); ?>
                        <?php if ($preview_embed_url !== ''): ?>
                            <div class="ratio ratio-16x9 mb-3">
                                <iframe src="<?php echo e($preview_embed_url); ?>"
                                        title="Preview of the recording" allowfullscreen
                                        allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                            </div>
                            <p class="small text-body-secondary">That is how the video will appear on the website.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 text-uppercase text-body-secondary mb-3">On the website</h2>
                        <?php admin_slug_field(url('sermons'), $values['slug']); ?>
                        <?php admin_status_field($values['status']); ?>
                    </div>
                </div>

                <?php admin_form_buttons(admin_url('sermons'), $is_new ? 'Add sermon' : 'Save changes'); ?>
            </form>
        </div>

        <div class="col-lg-4">
            <?php if (!$is_new): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-3">On the website</h2>
                        <p class="small text-body-secondary mb-2">
                            Address:
                            <a class="d-block text-break" target="_blank" rel="noopener"
                               href="<?php echo url('sermons/' . $sermon['slug']); ?>">
                                <?php echo url('sermons/' . $sermon['slug']); ?>
                            </a>
                        </p>
                        <p class="small text-body-secondary mb-0">Added <?php echo e(ago($sermon['created'])); ?>.</p>
                    </div>
                </div>

                <div class="card border-danger rounded-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Delete this sermon</h2>
                        <p class="small text-body-secondary">
                            It comes off the website at once and the picture is deleted. This cannot be undone.
                        </p>
                        <?php echo admin_delete_button('sermons', $sermon_id, 'the sermon "' . $sermon['title'] . '"'); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Finding the video address</h2>
                        <ol class="small text-body-secondary mb-0 ps-3">
                            <li>Open the video on YouTube.</li>
                            <li>Click <em>Share</em>, then <em>Copy</em>.</li>
                            <li>Paste it into the box above.</li>
                        </ol>
                        <p class="small text-body-secondary mt-3 mb-0">
                            The player on the website is put in by the website itself. Never paste the
                            &lt;iframe&gt; code into the box.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../app/includes/admin/footer.php'; ?>
