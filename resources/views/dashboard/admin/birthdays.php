<?php
/**
 * BUBAPC - Admin: birthdays by month
 *
 * One month at a time: everybody on the website whose birthday falls in the
 * month picked, earliest day first. This is the screen the office opens at
 * the end of a month to read back who celebrated in it - to mark the cards,
 * to plan the Sunday greeting, or simply to know whom to telephone.
 *
 * Only the day and the month are ever read. The year each person typed on
 * their own page stays on their own page: it is not selected, not shown and
 * not filterable, and that is the arrangement the birthday was added under.
 * Nobody types a birthday here either - it is told by the person it belongs
 * to, on their own church profile or their account page.
 *
 * The month comes from the box on the page and defaults to this one. It is
 * one of the Church group, so everybody with a sign in to the panel reads
 * the same month: a secretary and a website administrator see the same list.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared menu, buttons and checks are needed for the screen around the list. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'birthdays';

/* A hand-edited address with a number outside 1 to 12 falls back to the
   current month rather than showing a wrong or empty list. */
$month = (int) ($_GET['month'] ?? 0);
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}

$month_name = date('F', mktime(0, 0, 0, $month, 1));
$birthdays = birthdays_in_month($conn, $month);

$page_title = 'Birthdays';
$page_heading = 'Birthdays in ' . $month_name;

require_once __DIR__ . '/../../partials/admin/header.php';
?>

<form class="row g-2 align-items-center mb-3" method="get" action="<?php echo admin_url('birthdays'); ?>">
    <div class="col-12 col-md-4 col-lg-3">
        <label class="visually-hidden" for="filter-month">Month</label>
        <select class="form-select" id="filter-month" name="month" data-auto-submit>
            <?php for ($number = 1; $number <= 12; $number++): ?>
                <option value="<?php echo $number; ?>" <?php echo $number === $month ? 'selected' : ''; ?>>
                    <?php echo e(date('F', mktime(0, 0, 0, $number, 1))); ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-12 col-md-auto">
        <button type="submit" class="btn btn-primary">Show the month</button>
    </div>
</form>

<p class="text-body-secondary">
    <?php echo count($birthdays); ?>
    <?php echo count($birthdays) === 1 ? 'person has' : 'people have'; ?>
    a birthday in <?php echo e($month_name); ?>.
    <span class="d-block small mt-1">
        Only the day and the month are counted. The year each person gave stays with them.
    </span>
</p>

<?php if (!$birthdays): ?>
    <?php
    empty_state(
        'fa-cake-candles',
        'No birthdays in ' . $month_name,
        'Nobody has shared a birthday for ' . $month_name . ' yet. People add their own birthday '
        . 'from their church profile or their account page, and it appears here for the month it falls in.',
        '',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-sm">
                <thead>
                    <tr>
                        <th scope="col" class="ps-3" style="width:7rem">Day</th>
                        <th scope="col">Name</th>
                        <th scope="col">Group</th>
                        <th scope="col" class="pe-3">Belongs to</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $categories = people_categories(); ?>
                    <?php foreach ($birthdays as $person): ?>
                        <?php
                        $belongs_to = array_filter([
                            !empty($person['family_name']) ? $person['family_name'] : '',
                            !empty($person['department_name']) ? $person['department_name'] : '',
                        ]);
                        $today = birthday_today($person['born']);
                        ?>
                        <tr<?php echo $today ? ' class="table-warning"' : ''; ?>>
                            <td class="ps-3 text-nowrap fw-semibold">
                                <?php echo format_date($person['born'], 'D j'); ?>
                            </td>
                            <td>
                                <a class="fw-semibold text-decoration-none"
                                   href="<?php echo admin_url('people/form.php?id=' . (int) $person['id']); ?>">
                                    <?php echo e($person['name']); ?>
                                </a>
                                <?php if ($today): ?>
                                    <span class="badge rounded-pill text-bg-warning ms-1">
                                        <i class="fa-solid fa-cake-candles me-1" aria-hidden="true"></i>Today!
                                    </span>
                                <?php endif; ?>
                                <?php if ((int) $person['status'] !== 1): ?>
                                    <span class="badge rounded-pill text-bg-secondary ms-1">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill text-bg-light border">
                                    <?php echo e($categories[$person['category']] ?? $person['category']); ?>
                                </span>
                            </td>
                            <td class="small text-body-secondary text-truncate pe-3" style="max-width:14rem">
                                <?php echo $belongs_to ? e(implode(' &middot; ', $belongs_to)) : '<span class="text-body-tertiary">&mdash;</span>'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../partials/admin/footer.php'; ?>
