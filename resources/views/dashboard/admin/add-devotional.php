<?php
/**
 * BUBAPC - Admin: add or change one devotional
 *
 * The date box is the one people get wrong. It is the day the devotional is
 * FOR, not the day it was typed, so a message written on Monday for the
 * following Wednesday goes in Wednesday's box. That is what lets several be
 * written in one sitting and the website pick the right one every morning.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'devotionals';

$devotional_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$devotional = $devotional_id ? adminRow($conn, 'devotionals', $devotional_id) : [];

if ($devotional_id && !$devotional) {
    flash('error', 'That devotional is no longer here.');
    redirect(adminUrl('devotionals'));
}

$is_new = $devotional_id === 0;

$values = array_merge([
    'title' => '',
    'slug' => '',
    'message' => '',
    'scripture_reference' => '',
    'scripture_text' => '',
    'further_study' => '',
    'devotional_date' => date('Y-m-d'),
    'status' => 'published',
], $devotional);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $values['title'] = postText('title');
    $values['message'] = postText('message');
    $values['scripture_reference'] = postText('scripture_reference');
    $values['scripture_text'] = postText('scripture_text');
    $values['further_study'] = postText('further_study');
    $values['status'] = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';

    adminCheckRequired($values['title'], 'The title', $errors);
    adminCheckRequired($values['message'], 'The message', $errors);
    $values['devotional_date'] = adminCheckMoment($_POST['devotional_date'] ?? '', 'The date it is for', $errors);

    if (!$errors) {
        /* The address is made from the title and never typed, so it cannot arrive
           with a capital or a space in it. slug() adds a number when another
           record already has this one. On an edit the address that is already saved
           is kept, because it has been printed in a bulletin and shared, and a
           corrected title must not break those links. */
        $values['slug'] = $is_new
            ? slug($conn, 'devotionals', $values['title'])
            : $devotional['slug'];


        if ($is_new) {
            $statement = $conn->prepare(
                "INSERT INTO devotionals (title, slug, message, scripture_reference, scripture_text, further_study, devotional_date, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $statement->bind_param(
                'ssssssss',
                $values['title'],
                $values['slug'],
                $values['message'],
                $values['scripture_reference'],
                $values['scripture_text'],
                $values['further_study'],
                $values['devotional_date'],
                $values['status']
            );
            $statement->execute();
            $devotional_id = (int) dbLastId($conn);
            $statement->close();

            logAction($conn, 'devotional_created', 'The devotional "' . $values['title'] . '"');
            flash('success', 'The devotional "' . $values['title'] . '" has been added.');
        } else {
            $statement = $conn->prepare(
                "UPDATE devotionals
                 SET title = ?, slug = ?, message = ?, scripture_reference = ?, scripture_text = ?,
                     further_study = ?, devotional_date = ?, status = ?
                 WHERE id = ?"
            );
            $statement->bind_param(
                'ssssssssi',
                $values['title'],
                $values['slug'],
                $values['message'],
                $values['scripture_reference'],
                $values['scripture_text'],
                $values['further_study'],
                $values['devotional_date'],
                $values['status'],
                $devotional_id
            );
            $statement->execute();
            $statement->close();

            logAction($conn, 'devotional_updated', 'The devotional "' . $values['title'] . '"');
            flash('success', 'The devotional "' . $values['title'] . '" has been saved.');
        }

        redirect(adminUrl('devotionals'));
    }
}

$page_title = $is_new ? 'Add devotional' : 'Edit devotional';
$page_heading = $is_new ? 'Add a devotional' : 'Edit devotional';
$page_actions = '<a class="btn btn-outline-secondary" href="' . adminUrl('devotionals') . '">'
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
                        adminTextField('title', 'Title', $values['title'], '', 'The Loudest Silence');
                        adminDateField('devotional_date', 'The day this is for', $values['devotional_date'],
                            'Not the day you are typing it. A devotional for tomorrow can be written today.');
                        adminTextarea('message', 'The message', $values['message'],
                            'Leave an empty line between paragraphs. It is read on its own page on the website.', 18);
                        ?>

                        <h2 class="h6 text-uppercase text-body-secondary mb-3">Scripture</h2>
                        <div class="row">
                            <div class="col-md-5">
                                <?php
                                adminTextField('scripture_reference', 'Reference', $values['scripture_reference'],
                                    'For example Psalm 46:10', 'Psalm 46:10');
                                ?>
                            </div>
                            <div class="col-md-7">
                                <?php
                                adminTextarea('scripture_text', 'The words of the verse', $values['scripture_text'], '', 3);
                                ?>
                            </div>
                            <div class="col-12">
                                <?php
                                adminTextarea('further_study', 'Further study', $values['further_study'],
                                    'One reference per line. These are printed as a small list under the message.', 4);
                                ?>
                            </div>
                        </div>

                        <?php
                        adminStatusField($values['status']);
                        adminFormButtons(adminUrl('devotionals'), $is_new ? 'Add devotional' : 'Save changes');
                        ?>
                    </div>
                </div>
            </form>

            <?php if (!$is_new): ?>
                <div class="card border-danger rounded-4 mt-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Delete this devotional</h2>
                        <p class="small text-body-secondary mb-3">
                            It comes off the website at once. This cannot be undone.
                        </p>
                        <?php echo adminDeleteButton('devotionals', $devotional_id, 'the devotional "' . $devotional['title'] . '"'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../partials/admin/footer.php'; ?>
