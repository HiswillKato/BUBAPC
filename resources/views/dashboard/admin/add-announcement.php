<?php
/**
 * BUBAPC - Admin: add or change one announcement
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'announcements';

$announcement_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$announcement = $announcement_id ? adminRow($conn, 'announcements', $announcement_id) : [];

if ($announcement_id && !$announcement) {
    flash('error', 'That announcement is no longer here.');
    redirect(adminUrl('announcements'));
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
    verifyCsrf();

    $values['title'] = postText('title');
    $values['content'] = postText('content');
    $values['status'] = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';

    adminCheckRequired($values['title'], 'The title', $errors);
    adminCheckRequired($values['content'], 'The notice', $errors);
    $values['publish_date'] = adminCheckMoment($_POST['publish_date'] ?? '', 'The date it appears on', $errors);

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
            $announcement_id = (int) dbLastId($conn);
            $statement->close();

            logAction($conn, 'announcement_created', 'The announcement "' . $values['title'] . '"');
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

            logAction($conn, 'announcement_updated', 'The announcement "' . $values['title'] . '"');
            flash('success', 'The announcement "' . $values['title'] . '" has been saved.');
        }

        redirect(adminUrl('announcements'));
    }
}

$page_title = $is_new ? 'Add announcement' : 'Edit announcement';
$page_heading = $is_new ? 'Add an announcement' : 'Edit announcement';
$page_actions = '<a class="btn btn-outline-secondary" href="' . adminUrl('announcements') . '">'
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
                        adminTextField('title', 'Title', $values['title'], '', 'Registration is open for the Convention');
                        adminDateField('publish_date', 'The date it appears on', $values['publish_date'],
                            'Backdating is fine: it only decides where the notice sits in the order.');
                        adminTextarea('content', 'The notice', $values['content'],
                            'Leave an empty line between paragraphs. Shown on the home page and on the notices page.', 8);
                        adminStatusField($values['status']);
                        adminFormButtons(adminUrl('announcements'), $is_new ? 'Add announcement' : 'Save changes');
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
                        <?php echo adminDeleteButton('announcements', $announcement_id, 'the announcement "' . $announcement['title'] . '"'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../partials/admin/footer.php'; ?>
