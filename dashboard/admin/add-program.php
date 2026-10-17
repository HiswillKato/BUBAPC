<?php
/**
 * BUBAPC - Admin: add or change one meeting in the weekly program
 *
 * The day is a drop down rather than a box, because the database stores the
 * day as a number and 0 means Sunday. Letting somebody type it by hand is how
 * a meeting ends up on the wrong day.
 *
 * The venue is a plain box with one rule: a meeting run in two teams puts
 * each team's place on its own line, separated by a semicolon, because the
 * teams meet in different buildings. The help under it says so, and the
 * public page prints each line separately.
 */

require_once __DIR__ . '/../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../app/includes/admin/parts.php';

$active_module = 'programs';

$program_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$program = $program_id ? admin_row($conn, 'programs', $program_id) : [];

if ($program_id && !$program) {
    flash('error', 'That meeting is no longer here.');
    redirect(admin_url('programs'));
}

$is_new = $program_id === 0;

/* Coming from the "Add on Sunday" button, the day is already chosen. */
$day_from_url = isset($_GET['day']) ? (int) $_GET['day'] : -1;
if ($day_from_url < 0 || $day_from_url > 6) {
    $day_from_url = (int) date('w');
}

$day_options = [];
for ($day_number = 0; $day_number < 7; $day_number++) {
    $day_options[$day_number] = day_name($day_number);
}

$values = array_merge([
    'title' => '',
    'day_of_week' => $day_from_url,
    'start_time' => '09:00:00',
    'end_time' => '',
    'times' => '',
    'venue' => '',
    'description' => '',
    'sort_order' => 0,
    'active' => 1,
], $program);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values['title'] = post_text('title');
    $values['day_of_week'] = (int) ($_POST['day_of_week'] ?? 0);
    $values['venue'] = post_text('venue');
    $values['times'] = post_text('times');
    $values['description'] = post_text('description');
    $values['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
    $values['active'] = post_checkbox('active') ? 1 : 0;

    admin_check_required($values['title'], 'The name of the meeting', $errors);

    if (!isset($day_options[$values['day_of_week']])) {
        $errors[] = 'Please choose a day of the week.';
        $values['day_of_week'] = (int) date('w');
    }

    $values['start_time'] = admin_check_moment($_POST['start_time'] ?? '', 'The start time', $errors, 'time', true);
    $values['end_time'] = admin_check_moment($_POST['end_time'] ?? '', 'The end time', $errors, 'time', false);

    if ($values['start_time'] !== '' && $values['end_time'] !== '' && $values['end_time'] < $values['start_time']) {
        $errors[] = 'The end time is before the start time.';
    }

    if (!$errors) {
        if ($is_new) {
            $statement = $conn->prepare(
                "INSERT INTO programs (title, day_of_week, start_time, end_time, times, venue, description, sort_order, active)
                 VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, NULLIF(?, ''), ?, ?)"
            );
            $statement->bind_param(
                'sisssssii',
                $values['title'],
                $values['day_of_week'],
                $values['start_time'],
                $values['end_time'],
                $values['times'],
                $values['venue'],
                $values['description'],
                $values['sort_order'],
                $values['active']
            );
            $statement->execute();
            $program_id = (int) db_last_id($conn);
            $statement->close();

            log_action($conn, 'program_created', $values['title']);
            flash('success', '"' . $values['title'] . '" has been added to the weekly program.');
        } else {
            $statement = $conn->prepare(
                "UPDATE programs SET title = ?, day_of_week = ?, start_time = ?, end_time = NULLIF(?, ''),
                 times = NULLIF(?, ''), venue = ?, description = NULLIF(?, ''), sort_order = ?, active = ?
                 WHERE id = ?"
            );
            $statement->bind_param(
                'sisssssiii',
                $values['title'],
                $values['day_of_week'],
                $values['start_time'],
                $values['end_time'],
                $values['times'],
                $values['venue'],
                $values['description'],
                $values['sort_order'],
                $values['active'],
                $program_id
            );
            $statement->execute();
            $statement->close();

            log_action($conn, 'program_updated', $values['title']);
            flash('success', '"' . $values['title'] . '" has been saved.');
        }

        redirect(admin_url('programs'));
    }
}

$page_title = $is_new ? 'Add a meeting' : 'Edit a meeting';
$page_heading = $is_new ? 'Add a meeting to the program' : 'Edit a meeting';
$page_actions = '<a class="btn btn-outline-secondary" href="' . admin_url('programs') . '">'
    . '<i class="fa-solid fa-list me-1" aria-hidden="true"></i>Back to the program</a>';

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
                        admin_text_field('title', 'Name of the meeting', $values['title'], '', 'Midweek Prayer');
                        admin_select('day_of_week', 'Day of the week', $day_options, $values['day_of_week']);
                        ?>
                        <div class="row">
                            <div class="col-sm-6">
                                <?php admin_time_field('start_time', 'Start time', $values['start_time']); ?>
                            </div>
                            <div class="col-sm-6">
                                <?php admin_time_field('end_time', 'End time', $values['end_time'],
                                    'Leave empty if it does not end. A meeting whose teams meet at different hours still '
                                    . 'uses this as the window they all fall in.'); ?>
                            </div>
                        </div>
                        <?php
                        admin_textarea('times', 'Times, only if the teams differ', $values['times'],
                            'Leave this empty when the whole meeting runs from the start time to the end time, which '
                            . 'is almost always. When the teams of one meeting meet at different hours, write each '
                            . 'team\'s time after a semicolon, for example '
                            . '5:00-5:45 AM (Campus Team); 5:30-6:00 AM (Syaule Team). This is what a visitor reads '
                            . 'instead of the window above.', 2);
                        admin_textarea('venue', 'Venue', $values['venue'],
                            'Write the main hall in full - Agape Palace (Main Hall). A meeting run in two teams puts '
                            . 'each team and its place on its own line, separated by a semicolon, for example '
                            . 'Campus Team: The Ginnery; Syaule Team: Online. Leave empty when the venue is not '
                            . 'fixed, and say it in the line below instead.', 2);
                        admin_textarea('description', 'What the meeting is for', $values['description'],
                            'One line a visitor can read on the program page. Leave empty if there is nothing '
                            . 'useful to add.', 3);
                        admin_text_field('sort_order', 'Order within the day', (string) $values['sort_order'],
                            'Lower numbers come first. Meetings are shown by start time anyway, so this only '
                            . 'matters when two of them start together.', '0');
                        admin_checkbox('active', 'Show this meeting on the website', (int) $values['active'] === 1,
                            'Switch it off for a term break instead of deleting it.');
                        admin_form_buttons(admin_url('programs'), $is_new ? 'Add to the program' : 'Save changes');
                        ?>
                    </div>
                </div>
            </form>

            <?php if (!$is_new): ?>
                <div class="card border-danger rounded-4 mt-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Remove this meeting</h2>
                        <p class="small text-body-secondary mb-3">
                            It comes off the weekly program at once. If it is only for this term, use the
                            switch on the form instead.
                        </p>
                        <?php echo admin_delete_button('programs', $program_id, 'the meeting "' . $program['title'] . '"'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../app/includes/admin/footer.php'; ?>
