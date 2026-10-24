<?php
/**
 * BUBAPC - Admin: the list of people
 *
 * This is the one screen for everybody the church keeps: the pastors, the
 * students, the alumni, and the people who lead a department or a family,
 * because a student is a row here too and is pointed at their family and their
 * department from this form.
 *
 * A row here may also be a sign-in account: the same table holds those who
 * run the website, recognised by the password on the row. The Sign in column
 * says which rows can sign in and with what rights, so the whole church is read
 * from one list instead of two.
 *
 * The list is sorted by group first and then by the order the church has set
 * inside each group, so the pastors come before the students. That is why
 * there is an order box right on the row.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../partials/admin/parts.php';

$active_module = 'people';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifycsrf();

    $action = posttext('action');
    $person_id = (int) ($_POST['id'] ?? 0);
    $person = adminrow($conn, 'people', $person_id);

    if (!$person) {
        flash('error', 'That person is no longer here. They may already have been removed.');
        redirect(adminurl('people'));
    }

    if ($action === 'delete') {
        /* A row that can sign in is also an account, and only an administrator
           may throw an account away. An editor keeps the directory tidy and
           must not be able to delete the people who run the website. */
        if (personcansignin($person) && !isadmin()) {
            flash('error', 'Only an administrator can remove a sign-in account.');
            redirect(adminurl('people'));
        }

        /* Before the row goes, anything that points at it has to be let go,
           or a department would be left naming a leader who is not there.

           The typed name is filled in first, so the department page keeps
           saying who led it even after the person is gone. Without this a
           deleted pastor would silently blank out the "Led by" line.

           Only the leader column is touched. A person's own family and
           department columns point at families.id and departments.id, not at
           people.id, so deleting a person must not clear anybody else's
           membership: family and department ids overlap with person ids as the
           tables grow, and matching one against the other would detach students
           from a family they are still in. */
        foreach (['departments', 'families'] as $table) {
            $copy_name = $conn->prepare(
                "UPDATE `$table` SET typed = ? WHERE leader = ? AND typed = ''"
            );
            $copy_name->bind_param('si', $person['name'], $person_id);
            $copy_name->execute();
            $copy_name->close();

            $unlink = $conn->prepare("UPDATE `$table` SET leader = 0 WHERE leader = ?");
            $unlink->bind_param('i', $person_id);
            $unlink->execute();
            $unlink->close();
        }

        /* The file is taken from the row that was just read out of the database
           and never from the posted form, so a hand-made request cannot make
           this delete some other file on the server. */
        deleteuploadedimage($person['image']);

        $statement = $conn->prepare("DELETE FROM people WHERE id = ?");
        $statement->bind_param('i', $person_id);
        $statement->execute();
        $statement->close();

        logaction($conn, 'person_deleted', $person['name']);
        flash('success', $person['name'] . ' has been removed from the website.');
    } elseif ($action === 'toggle') {
        $new_status = (int) $person['status'] === 1 ? 0 : 1;

        $statement = $conn->prepare("UPDATE people SET status = ? WHERE id = ?");
        $statement->bind_param('ii', $new_status, $person_id);
        $statement->execute();
        $statement->close();

        flash('success', $new_status === 1
            ? $person['name'] . ' is now shown on the website.'
            : $person['name'] . ' is now hidden from the website.');
    } elseif ($action === 'set_order') {
        $new_order = max(0, min(999, (int) ($_POST['display_order'] ?? 0)));

        $statement = $conn->prepare("UPDATE people SET display_order = ? WHERE id = ?");
        $statement->bind_param('ii', $new_order, $person_id);
        $statement->execute();
        $statement->close();

        flash('success', 'The order for ' . $person['name'] . ' has been saved.');
    }

    redirect(adminurl('people'));
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$category_filter = isset($_GET['category']) ? (string) $_GET['category'] : '';

/* An unknown category is treated as no filter rather than as a search term,
   so a stale bookmark shows the whole list instead of an error. */
if ($category_filter !== '' && !array_key_exists($category_filter, peoplecategories())) {
    $category_filter = '';
}

$where = [];
$bind_types = '';
$bind_values = [];

if ($search !== '') {
    $where[] = '(name LIKE ? OR position LIKE ? OR bio LIKE ? OR testimony LIKE ?)';
    $like = '%' . $search . '%';
    $bind_types .= 'ssss';
    $bind_values[] = $like;
    $bind_values[] = $like;
    $bind_values[] = $like;
    $bind_values[] = $like;
}
if ($category_filter !== '') {
    $where[] = 'category = ?';
    $bind_types .= 's';
    $bind_values[] = $category_filter;
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

if ($bind_values) {
    $statement = $conn->prepare("SELECT COUNT(*) FROM people" . $where_sql);
    $statement->bind_param($bind_types, ...$bind_values);
    $statement->execute();
    $total_people = (int) $statement->get_result()->fetch_assoc()['COUNT(*)'];
    $statement->close();
} else {
    $total_people = dbcount($conn, "SELECT COUNT(*) FROM people" . $where_sql);
}

$per_page = 25;
$current_page = pagenumber();
$pagination = paginate($total_people, $per_page, $current_page);

/* The family and the department names are joined in so the list can say where
   somebody sits without the editor having to open each form. LEFT JOIN, not
   JOIN, because most students have one or neither. */
$statement = $conn->prepare(
    "SELECT people.*, families.name AS family_name, departments.name AS department_name
     FROM people
     LEFT JOIN families ON families.id = people.family
     LEFT JOIN departments ON departments.id = people.department"
    . $where_sql . " ORDER BY category ASC, display_order ASC, name ASC LIMIT ? OFFSET ?"
);
$offset = $pagination['offset'];
$statement->bind_param($bind_types . 'ii', ...array_merge($bind_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$people = [];
while ($row = $result->fetch_assoc()) {
    $people[] = $row;
}
$statement->close();

/* The groups, in the order the church thinks of them, for the filter box. */
$category_options = ['' => 'All groups'];
foreach (peoplecategories() as $key => $label) {
    $category_options[$key] = $label;
}

$page_title = 'People';
$page_heading = 'People';
$page_actions = '<a class="btn btn-primary" href="' . adminurl('people/form.php') . '">'
    . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add a person</a>';

require_once __DIR__ . '/../../partials/admin/header.php';
?>

<?php
/* The filter bar here has a group box instead of a status box, so it is
   written out rather than using adminfilterbar(). */
$filter_query = ['q' => $search];
if ($category_filter !== '') {
    $filter_query['category'] = $category_filter;
}
?>
<form class="row g-2 align-items-center mb-3" method="get" action="<?php echo adminurl('people'); ?>" role="search">
    <div class="col-12 col-md">
        <label class="visually-hidden" for="filter-search">Search</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
            <input type="search" class="form-control" id="filter-search" name="q"
                   value="<?php echo e($search); ?>" placeholder="Search by name or position" maxlength="100">
        </div>
    </div>
    <div class="col-12 col-md-auto">
        <label class="visually-hidden" for="filter-category">Group</label>
        <select class="form-select" id="filter-category" name="category" data-auto-submit>
            <?php foreach ($category_options as $value => $label): ?>
                <option value="<?php echo e($value); ?>" <?php echo $category_filter === $value ? 'selected' : ''; ?>>
                    <?php echo e($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12 col-md-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($search !== '' || $category_filter !== ''): ?>
            <a class="btn btn-outline-secondary" href="<?php echo adminurl('people'); ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php adminresultbar($pagination, $filter_query, 'people'); ?>

<?php if (!$people): ?>
    <?php
    emptystate(
        'fa-users',
        $search !== '' || $category_filter !== '' ? 'Nobody matches that' : 'Nobody added yet',
        $search !== '' || $category_filter !== ''
            ? 'Try a different name, or clear the filter.'
            : 'This is the one screen for everybody the church lists: the pastors, the students, '
                . 'the alumni, and the people who lead a department or a family. Add them here and '
                . 'they appear on the right page straight away.',
        '<a class="btn btn-primary" href="' . adminurl('people/form.php') . '">'
        . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add the first person</a>',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-sm">
                <thead>
                    <tr>
                        <th scope="col" class="ps-3">Name</th>
                        <th scope="col">Group</th>
                        <th scope="col">Sign in</th>
                        <th scope="col">Belongs to</th>
                        <th scope="col" style="width:8rem">Order</th>
                        <th scope="col">Shown</th>
                        <th scope="col" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $categories = peoplecategories();
                    foreach ($people as $person):
                        $belongs_to = array_filter([
                            !empty($person['family_name']) ? $person['family_name'] : '',
                            !empty($person['department_name']) ? $person['department_name'] : '',
                        ]);
                    ?>
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <?php /* The same rule as the public pages: a
                                             picture when there is one, initials
                                             when there is not. The list is
                                             where somebody notices who is
                                             still missing a photograph. */
                                    if (!empty($person['image'])): ?>
                                        <img src="<?php echo uploadurl($person['image']); ?>" alt=""
                                             width="44" height="44" class="rounded-circle object-fit-cover flex-shrink-0">
                                    <?php else: ?>
                                        <span class="brand-mark bg-brand-soft text-brand rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                              style="width:44px;height:44px" aria-hidden="true">
                                            <?php echo initials($person['name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <div>
                                        <a class="fw-semibold d-block text-decoration-none text-truncate"
                                           href="<?php echo adminurl('people/form.php?id=' . (int) $person['id']); ?>"
                                           style="max-width:14rem">
                                            <?php echo e($person['name']); ?>
                                        </a>
                                        <?php if ($person['position'] !== ''): ?>
                                            <span class="small text-body-secondary text-truncate d-block"
                                                  style="max-width:14rem"><?php echo e($person['position']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($person['graduated_year'])): ?>
                                            <span class="small text-body-secondary d-block">Class of <?php echo (int) $person['graduated_year']; ?></span>
                                        <?php endif; ?>
                                        <?php /* Somebody who celebrates this month is
                                                 marked here too, so the office sees it
                                                 while keeping the list tidy. */ ?>
                                        <?php $cake = birthdaybadge($person['born']);
                                        if ($cake !== ''): ?>
                                            <div class="mt-1"><?php echo $cake; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge rounded-pill text-bg-light border">
                                    <?php echo e($categories[$person['category']] ?? $person['category']); ?>
                                </span>
                            </td>
                            <td>
                                <?php /* A password on the row makes it an account;
                                         the rights come from its role. An account
                                         that is switched off says so. */ ?>
                                <?php if (personcansignin($person)): ?>
                                    <span class="badge rounded-pill text-bg-primary">
                                        <?php echo e(userrolelabel($person['role'])); ?>
                                    </span>
                                    <?php if ((int) $person['active'] !== 1): ?>
                                        <span class="badge rounded-pill text-bg-secondary ms-1">Off</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-body-tertiary">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-body-secondary text-truncate" style="max-width:12rem">
                                <?php echo $belongs_to ? e(implode(' &middot; ', $belongs_to)) : '<span class="text-body-tertiary">&mdash;</span>'; ?>
                            </td>
                            <td class="text-nowrap"><?php echo adminorderfield('people', $person['id'], $person['display_order']); ?></td>
                            <td>
                                <span class="badge rounded-pill <?php echo (int) $person['status'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                    <?php echo (int) $person['status'] === 1 ? 'Shown' : 'Hidden'; ?>
                                </span>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <?php
                                echo adminrowactions('people', $person['id'], [
                                    'active' => $person['status'],
                                    'label' => $person['name'],
                                ]);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../partials/admin/footer.php'; ?>
