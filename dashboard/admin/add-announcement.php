<?php
/**
 * BUBAPC - Admin: add or change one announcement
 */

require_once __DIR__ . '/../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../app/includes/admin/parts.php';

$active_module = 'announcements';

$announcement_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$announcement = $announcement_id ? admin_row($conn, 'announcements', $announcement_id) : [];

if ($announcement_id && !$announcement) {
    flash('error', 'That announcement is no longer here.');
    redirect(admin_url('announcements'));
}

$is_new = $announcement_id === 0;

$values = array_merge([
    'title' => '',
    'slug' => '',
    'content' => '',
    'publish_date' => date('Y-m-d'),
    'status' => 'published',
], $announcement);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values['title'] = post_text('title');
    $values['content'] = post_text('content');
    $values['status'] = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';

    admin_check_required($values['title'], 'The title', $errors);
    admin_check_required($values['content'], 'The notice', $errors);
    $values['publish_date'] = admin_check_moment($_POST['publish_date'] ?? '', 'The date it appears on', $errors);

    if (!$errors) {
        /* The address is made from the title and never typed, so it cannot arrive
           with a capital or a space in it. slug() adds a number when another
           record already has this one. On an edit the address that is already saved
           is kept, because a notice may already have been shared as a link. */
        $values['slug'] = $is_new
            ? slug($conn, 'announcements', $values['title'])
            : $announcement['slug'];


        if ($is_new) {
            $statement = $conn->prepare(
                "INSERT INTO announcements (title, slug, content, publish_date, status)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $statement->bind_param(
                'sssss',
                $values['title'],
                $values['slug'],
                $values['content'],
                $values['publish_date'],
                $values['status']
            );
            $statement->execute();
            $announcement_id = (int) db_last_id($conn);
            $statement->close();

            log_action($conn, 'announcement_created', 'The announcement "' . $values['title'] . '"');
            flash('success', 'The announcement "' . $values['title'] . '" has been added.');
        } else {
            $statement = $conn->prepare(
                "UPDATE announcements
                 SET title = ?, slug = ?, content = ?, publish_date = ?, status = ?
                 WHERE id = ?"
            );
            $statement->bind_param(
                'sssssi',
                $values['title'],
                $values['slug'],
                $values['content'],
                $values['publish_date'],
                $values['status'],
                $announcement_id
            );
            $statement->execute();
            $statement->close();

            log_action($conn, 'announcement_updated', 'The announcement "' . $values['title'] . '"');
            flash('success', 'The announcement "' . $values['title'] . '" has been saved.');
        }

        redirect(admin_url('announcements'));
    }
}

$page_title = $is_new ? 'Add announcement' : 'Edit announcement';
$page_heading = $is_new ? 'Add an announcement' : 'Edit announcement';
$page_actions = '<a class="btn btn-outline-secondary" href="' . admin_url('announcements') . '">'
    . '<i class="fa-solid fa-list me-1" aria-hidden="true"></i>Back to the list</a>';

require_once __DIR__ . '/../../app/includes/admin/header.php';
?>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">

            <?php admin_errors($errors); ?>

            <form method="post" novalidate>
                <?php echo csrf_field(); ?>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <?php
                        admin_text_field('title', 'Title', $values['title'], '', 'Registration is open for the Convention');
                        admin_date_field('publish_date', 'The date it appears on', $values['publish_date'],
                            'Backdating is fine: it only decides where the notice sits in the order.');
                        admin_textarea('content', 'The notice', $values['content'],
                            'Leave an empty line between paragraphs. Shown on the home page and on the notices page.', 8);
                        admin_status_field($values['status']);
                        admin_form_buttons(admin_url('announcements'), $is_new ? 'Add announcement' : 'Save changes');
                        ?>
                    </div>
                </div>
            </form>

            <?php if (!$is_new): ?>
                <div class="card border-danger rounded-4 mt-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Delete this announcement</h2>
                        <p class="small text-body-secondary mb-3">
                            It comes off the website at once. This cannot be undone.
                        </p>
                        <?php echo admin_delete_button('announcements', $announcement_id, 'the announcement "' . $announcement['title'] . '"'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../app/includes/admin/footer.php'; ?>
