<?php
/**
 * BUBAPC - Admin: add or change one event
 *
 * Without an id in the address this adds a new event. With an id it changes
 * the one that is already there, and the Delete button appears at the bottom
 * instead of the Add button.
 */

require_once __DIR__ . '/../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../app/includes/admin/parts.php';

$active_module = 'events';

$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$event = $event_id ? admin_row($conn, 'events', $event_id) : [];

if ($event_id && !$event) {
    flash('error', 'That event is no longer here.');
    redirect(admin_url('events'));
}

$is_new = $event_id === 0;

/* Start from what is saved, so a form that comes back with a mistake in it
   still shows everything the editor had already typed. */
$values = array_merge([
    'title' => '',
    'theme' => '',
    'description' => '',
    'venue' => '',
    'start_date' => date('Y-m-d'),
    'start_time' => '',
    'end_date' => '',
    'end_time' => '',
    'image' => '',
    'status' => 'draft',
    'slug' => '',
], $event);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values['title'] = post_text('title');
    $values['theme'] = post_text('theme');
    $values['description'] = post_text('description');
    $values['venue'] = post_text('venue');
    $values['status'] = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';

    admin_check_required($values['title'], 'The title', $errors);
    $values['start_date'] = admin_check_moment($_POST['start_date'] ?? '', 'The start date', $errors);
    $values['start_time'] = admin_check_moment($_POST['start_time'] ?? '', 'The start time', $errors, 'time', false);
    $values['end_date'] = admin_check_moment($_POST['end_date'] ?? '', 'The end date', $errors, 'date', false);
    $values['end_time'] = admin_check_moment($_POST['end_time'] ?? '', 'The end time', $errors, 'time', false);

    /* An event that ends before it begins is a typing mistake, not a plan. */
    if ($values['start_date'] !== '' && $values['end_date'] !== '' && $values['end_date'] < $values['start_date']) {
        $errors[] = 'The end date is before the start date.';
    }
    if ($values['start_date'] !== '' && $values['end_date'] === $values['start_date']
        && $values['start_time'] !== '' && $values['end_time'] !== ''
        && $values['end_time'] < $values['start_time']) {
        $errors[] = 'On one day, the end time is before the start time.';
    }

    if (!$errors) {
        /* The address is made from the title and never typed, so it cannot arrive
           with a capital or a space in it. slug() adds a number when another
           event already has this one. On an edit the address that is already saved
           is kept, because it has been printed in a bulletin and shared, and a
           corrected title must not break those links. */
        $values['slug'] = $is_new
            ? slug($conn, 'events', $values['title'])
            : $event['slug'];

        $image = save_image(
            $_FILES['image'] ?? null,
            post_checkbox('remove_image'),
            'events',
            $event['image'] ?? ''
        );
        $values['image'] = $image['path'];
        if ($image['error'] !== '') {
            flash('error', 'The event was saved, but the picture was not: ' . $image['error']);
        }

        if ($is_new) {
            /* NULLIF(?, '') turns a box that was left empty into a real NULL.
               MySQL will not accept an empty string in a DATE or TIME column,
               and a missing time is NULL rather than "00:00" in the database. */
            $statement = $conn->prepare(
                "INSERT INTO events (title, slug, theme, description, venue, start_date, start_time, end_date, end_time, image, status)
                 VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, ?)"
            );
            $statement->bind_param(
                'sssssssssss',
                $values['title'],
                $values['slug'],
                $values['theme'],
                $values['description'],
                $values['venue'],
                $values['start_date'],
                $values['start_time'],
                $values['end_date'],
                $values['end_time'],
                $values['image'],
                $values['status']
            );
            $statement->execute();
            $event_id = (int) db_last_id($conn);
            $statement->close();

            log_action($conn, 'event_created', 'The event "' . $values['title'] . '"');
            flash('success', 'The event "' . $values['title'] . '" has been added.');
        } else {
            $statement = $conn->prepare(
                "UPDATE events
                 SET title = ?, slug = ?, theme = ?, description = ?, venue = ?, start_date = ?,
                     start_time = NULLIF(?, ''), end_date = NULLIF(?, ''), end_time = NULLIF(?, ''),
                     image = ?, status = ?
                 WHERE id = ?"
            );
            $statement->bind_param(
                'sssssssssssi',
                $values['title'],
                $values['slug'],
                $values['theme'],
                $values['description'],
                $values['venue'],
                $values['start_date'],
                $values['start_time'],
                $values['end_date'],
                $values['end_time'],
                $values['image'],
                $values['status'],
                $event_id
            );
            $statement->execute();
            $statement->close();

            log_action($conn, 'event_updated', 'The event "' . $values['title'] . '"');
            flash('success', 'The event "' . $values['title'] . '" has been saved.');
        }

        redirect(admin_url('events'));
    }
}

$page_title = $is_new ? 'Add event' : 'Edit event';
$page_heading = $is_new ? 'Add an event' : 'Edit event';
$page_actions = '<a class="btn btn-outline-secondary" href="' . admin_url('events') . '">'
    . '<i class="fa-solid fa-list me-1" aria-hidden="true"></i>Back to the list</a>';

require_once __DIR__ . '/../../app/includes/admin/header.php';
?>

    <div class="row g-4">
        <div class="col-lg-8">

            <?php admin_errors($errors); ?>

            <form method="post" enctype="multipart/form-data" novalidate>
                <?php echo csrf_field(); ?>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <?php
                        /* The title box is the source of the address preview, so
                           main.js can show the address while it is being typed. */
                        admin_text_field('title', 'Title', $values['title'], '', 'A Voice to Generations', 'data-slug-source');
                        admin_text_field('theme', 'Theme or tag', $values['theme'], 'For example: Convention, Retreat, Prayer. Shown as a small badge.', 'Retreat');
                        admin_textarea('description', 'Description', $values['description'],
                            'Leave an empty line between paragraphs. It will be read as a page on the website.', 10);
                        ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 text-uppercase text-body-secondary mb-3">When and where</h2>

                        <div class="row">
                            <div class="col-sm-6">
                                <?php admin_date_field('start_date', 'Start date', $values['start_date']); ?>
                            </div>
                            <div class="col-sm-6">
                                <?php admin_time_field('start_time', 'Start time', $values['start_time'], 'Leave empty for an all-day event.'); ?>
                            </div>
                            <div class="col-sm-6">
                                <?php admin_date_field('end_date', 'End date', $values['end_date'], 'Only for something that runs for more than one day.'); ?>
                            </div>
                            <div class="col-sm-6">
                                <?php admin_time_field('end_time', 'End time', $values['end_time']); ?>
                            </div>
                            <div class="col-12">
                                <?php admin_text_field('venue', 'Venue', $values['venue'], 'Where it happens.', 'Busitema University Main Grounds'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 text-uppercase text-body-secondary mb-3">On the website</h2>
                        <?php admin_slug_field(url('events'), $values['slug']); ?>
                        <?php admin_status_field($values['status']); ?>
                        <?php admin_image_field('image', 'Picture', $values['image']); ?>
                    </div>
                </div>

                <?php admin_form_buttons(admin_url('events'), $is_new ? 'Add event' : 'Save changes'); ?>
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
                               href="<?php echo url('events/' . $event['slug']); ?>">
                                <?php echo url('events/' . $event['slug']); ?>
                            </a>
                        </p>
                        <p class="small text-body-secondary mb-0">
                            Added <?php echo e(ago($event['created'])); ?>.
                        </p>
                    </div>
                </div>

                <div class="card border-danger rounded-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Delete this event</h2>
                        <p class="small text-body-secondary">
                            It comes off the website at once and the picture is deleted. This cannot be undone.
                        </p>
                        <?php echo admin_delete_button('events', $event_id, 'the event "' . $event['title'] . '"'); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">A few tips</h2>
                        <ul class="small text-body-secondary mb-0 ps-3">
                            <li>Save it as a draft first if you want to think it over. Nobody can see a draft.</li>
                            <li>The address is made from the title. You can change it in the box if you like.</li>
                            <li>A picture makes a card twice as easy to recognise on a phone.</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../app/includes/admin/footer.php'; ?>
