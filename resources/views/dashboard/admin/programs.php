<?php
/**
 * BUBAPC - Admin: the weekly program
 *
 * The program is a week, not a list, so this page shows the seven days as
 * cards instead of a table with page numbers. Each day holds however many
 * meetings there are, in the order they happen.
 *
 * There is no search box here on purpose. A week is short, and there is
 * nothing to search in seven boxes a visitor can already read at a glance.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'programs';

/* The seven days, in the order a week runs. The numbers are what the
   day_of_week column stores, so Sunday really is 0. */
$days = [];
for ($day_number = 0; $day_number < 7; $day_number++) {
    $days[$day_number] = dayname($day_number);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifycsrf();

    $action = posttext('action');
    $program_id = (int) ($_POST['id'] ?? 0);
    $program = adminrow($conn, 'programs', $program_id);

    if (!$program) {
        flash('error', 'That meeting is no longer here. It may already have been deleted.');
        redirect(adminurl('programs'));
    }

    if ($action === 'delete') {
        $statement = $conn->prepare("DELETE FROM programs WHERE id = ?");
        $statement->bind_param('i', $program_id);
        $statement->execute();
        $statement->close();

        logaction($conn, 'program_deleted', 'The meeting "' . $program['title'] . '"');
        flash('success', 'The meeting "' . $program['title'] . '" has been removed from the program.');
    } elseif ($action === 'toggle') {
        $new_active = (int) $program['active'] === 1 ? 0 : 1;

        $statement = $conn->prepare("UPDATE programs SET active = ? WHERE id = ?");
        $statement->bind_param('ii', $new_active, $program_id);
        $statement->execute();
        $statement->close();

        flash('success', $new_active === 1
            ? '"' . $program['title'] . '" is back in the weekly program.'
            : '"' . $program['title'] . '" has been taken off the weekly program.');
    }

    redirect(adminurl('programs'));
}

$statement = $conn->prepare("SELECT * FROM programs ORDER BY day_of_week ASC, start_time ASC, sort_order ASC");
$statement->execute();
$result = $statement->get_result();

/* $program_by_day[0][0] is the first meeting on Sunday. */
$program_by_day = [];
$total_programs = 0;
while ($row = $result->fetch_assoc()) {
    $program_by_day[(int) $row['day_of_week']][] = $row;
    $total_programs++;
}
$statement->close();

$page_title = 'Weekly program';
$page_heading = 'Weekly program';
$page_actions = '<a class="btn btn-primary" href="' . adminurl('programs/form.php') . '">'
    . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add a meeting</a>';

require_once __DIR__ . '/../../partials/admin/header.php';
?>

    <p class="text-body-secondary">
        <?php if ($total_programs === 0): ?>
            Nothing is in the weekly program yet, so the program page and the home page are both empty.
        <?php else: ?>
            <?php echo e($total_programs); ?> <?php echo $total_programs === 1 ? 'meeting' : 'meetings'; ?>
            in the week. Meetings are switched off with the eye button rather than deleted, so a term
            break is a click and not a re-typing.
        <?php endif; ?>
    </p>

    <div class="row g-3">
        <?php foreach ($days as $day_number => $day_label): ?>
            <?php $meetings = $program_by_day[$day_number] ?? []; ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4">
                        <h2 class="h6 text-uppercase text-body-secondary mb-3">
                            <?php echo e($day_label); ?>
                            <span class="badge rounded-pill text-bg-secondary ms-1"><?php echo count($meetings); ?></span>
                        </h2>

                        <?php if (!$meetings): ?>
                            <p class="small text-body-secondary mb-2">Nothing on this day.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush mb-0">
                                <?php foreach ($meetings as $meeting): ?>
                                    <li class="list-group-item bg-transparent px-0 d-flex align-items-start gap-2">
                                        <div class="flex-grow-1">
                                            <a class="fw-semibold d-block text-decoration-none"
                                               href="<?php echo adminurl('programs/form.php?id=' . (int) $meeting['id']); ?>">
                                                <?php echo e($meeting['title']); ?>
                                            </a>
                                            <?php $time_lines = programtimes($meeting); ?>
                                            <span class="small text-body-secondary">
                                                <?php if ($time_lines): ?>
                                                    <?php foreach ($time_lines as $time_line): ?>
                                                        <span class="d-block"><?php echo $time_line; ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <?php echo e(trim(formattimerange($meeting['start_time'], $meeting['end_time']))); ?>
                                                <?php endif; ?>
                                                <?php foreach (teamlines($meeting['venue']) as $place): ?>
                                                    <span class="d-block">
                                                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                                        <?php echo e($place); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if ((int) $meeting['active'] === 0): ?>
                                                    <span class="badge rounded-pill text-bg-secondary mt-1">Hidden</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="text-nowrap">
                                            <?php
                                            echo adminactiongroup(
                                                admintogglebutton('programs', $meeting['id'], $meeting['active'], 'Show', 'Hide')
                                                . admindeletebutton('programs', $meeting['id'], 'the meeting "' . $meeting['title'] . '"')
                                            );
                                            ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <a class="btn btn-sm btn-outline-secondary mt-3"
                           href="<?php echo adminurl('programs/form.php?day=' . $day_number); ?>">
                            <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add on <?php echo e($day_label); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php require_once __DIR__ . '/../../partials/admin/footer.php'; ?>
