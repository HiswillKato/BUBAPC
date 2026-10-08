<?php
/**
 * BUBAPC - Departments
 *
 * With a slug in the address (/departments/youth-ministry) this shows one
 * department. Without one it shows the whole list.
 *
 * families.php is written in exactly the same shape with the word "family"
 * instead of "department", because the two tables are the same and the church
 * thinks of them the same way. If you change one, change the other.
 */

require_once __DIR__ . '/../app/init.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$per_page = 9;

/* ---------------------------------------------------------------------
   1. ONE DEPARTMENT WHEN A SLUG WAS GIVEN
   --------------------------------------------------------------------- */
if ($slug !== '') {
    $statement = $conn->prepare("SELECT * FROM departments WHERE slug = ? AND active = 1 LIMIT 1");
    $statement->bind_param('s', $slug);
    $statement->execute();
    $result = $statement->get_result();
    $department = $result->fetch_assoc();
    $statement->close();

    if (!$department) {
        http_response_code(404);
        $error_code = 404;
        $error_title = 'Department not found';
        $error_message = 'That department is not on our website. It may have been renamed, '
            . 'or it may be listed as hidden for now. The full list is on the departments page.';
        require __DIR__ . '/../app/includes/error-page.php';
        exit;
    }

    $page_title = $department['name'];
    $page_description = excerpt(strip_tags(html_entity_decode($department['description'] ?: $department['name'])), 160);
    $canonical = 'departments/' . $department['slug'];

    /* The leader is looked up through group_leader() rather than read straight
       off the row, so a leader who has a page of their own is linked and their
       name follows any change to it. A name typed in by hand still shows for
       the leaders who do not have a page. */
    $leader = group_leader($conn, $department);

    require_once __DIR__ . '/../app/includes/public/header.php';

    page_header(
        $department['name'],
        $leader['name'] !== '' ? 'Led by ' . $leader['name'] : 'One of the departments of ' . SITE_NAME . '.',
        [['Home', url('')], ['Our church', url('leadership')], ['Departments', url('departments')], [$department['name'], '']]
    );

    /* Whoever serves in this department, so the page can show the team as well
       as the person who leads it. */
    $department_people = people_in_group($conn, 'department', (int) $department['id']);

    /* The other departments, so a visitor who liked one can keep looking. */
    $statement = $conn->prepare("SELECT name, slug FROM departments
                                 WHERE active = 1 AND id <> ?
                                 ORDER BY display_order ASC, name ASC LIMIT 6");
    $other_id = (int) $department['id'];
    $statement->bind_param('i', $other_id);
    $statement->execute();
    $result = $statement->get_result();

    $others = [];
    while ($row = $result->fetch_assoc()) {
        $others[] = $row;
    }
    $statement->close();
    ?>

    <section class="py-5">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-8">
                    <?php if ($leader['name'] !== ''): ?>
                        <p class="d-flex align-items-center gap-2 text-body-secondary">
                            <i class="fa-solid fa-user-tie text-brand" aria-hidden="true"></i>
                            Led by
                            <strong class="text-body">
                                <?php if ($leader['linked']): ?>
                                    <a href="<?php echo url('people/' . $leader['person']['slug']); ?>">
                                        <?php echo e($leader['name']); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo e($leader['name']); ?>
                                <?php endif; ?>
                            </strong>
                        </p>
                    <?php endif; ?>

                    <div class="fs-5">
                        <?php echo paragraphs($department['description'] ?: 'More about this department will be posted here soon.'); ?>
                    </div>

                    <?php if ($department_people): ?>
                        <h2 class="h4 mt-5 mb-3">Who serves here</h2>
                        <div class="row g-3">
                            <?php foreach ($department_people as $member): ?>
                                <div class="col-sm-6">
                                    <a class="d-flex align-items-center gap-2 text-decoration-none p-2 border rounded-3 h-100"
                                       href="<?php echo url('people/' . $member['slug']); ?>">
                                        <?php /* A member of the group follows the
                                                 same rule as their own page: the
                                                 photograph when there is one, their
                                                 initials when there is not. */
                                        if (!empty($member['image'])): ?>
                                            <img src="<?php echo upload_url($member['image']); ?>" alt=""
                                                 width="48" height="48" class="rounded-circle object-fit-cover flex-shrink-0">
                                        <?php else: ?>
                                            <span class="brand-mark bg-brand-soft text-brand rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                                  style="width:48px;height:48px" aria-hidden="true">
                                                <?php echo initials($member['name']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span>
                                            <span class="d-block fw-semibold text-body"><?php echo e($member['name']); ?></span>
                                            <?php if ($member['position'] !== ''): ?>
                                                <span class="small text-body-secondary"><?php echo e($member['position']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <?php share_bar($department['name'], url('departments/' . $department['slug'])); ?>
                    </div>

                    <a class="btn btn-outline-primary mt-4" href="<?php echo url('departments'); ?>">
                        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>All departments
                    </a>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <p class="text-uppercase fw-bold small text-gold-dark mb-3">Join in</p>
                            <p class="text-body-secondary small">
                                There is a place for you in this department. Come and speak to
                                the leader after any service, or send us a message and we will
                                introduce you.
                            </p>
                            <a class="btn btn-primary w-100" href="<?php echo url('contact'); ?>">
                                <i class="fa-solid fa-envelope me-1" aria-hidden="true"></i>Get in touch
                            </a>

                            <?php if ($others): ?>
                                <hr>

                                <p class="text-uppercase fw-bold small text-gold-dark mb-2">Other departments</p>
                                <ul class="list-unstyled d-grid gap-2 mb-0">
                                    <?php foreach ($others as $other): ?>
                                        <li>
                                            <a class="small"
                                               href="<?php echo url('departments/' . $other['slug']); ?>">
                                                <i class="fa-solid fa-chevron-right text-gold-dark me-1" aria-hidden="true"></i>
                                                <?php echo e($other['name']); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    require_once __DIR__ . '/../app/includes/public/footer.php';
    exit;
}

/* ---------------------------------------------------------------------
   2. THE LIST OF DEPARTMENTS
   --------------------------------------------------------------------- */
$page_title = 'Departments';
$page_description = 'The departments of ' . SITE_NAME . ' and how to join each one.';
$canonical = 'departments';

require_once __DIR__ . '/../app/includes/public/header.php';

page_header(
    'Departments',
    'Each department takes care of one part of the church&rsquo;s life, and each one '
    . 'is open to anybody who wants to serve.',
    [['Home', url('')], ['Our church', url('leadership')], ['Departments', '']]
);

$search_term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$like = '%' . $search_term . '%';

/* The list is joined to people so a search for a leader's name finds the
   department they lead, and so the card can show the linked name rather than
   a typed one that has drifted out of date. LEFT JOIN, not JOIN: a department
   whose leader was never added as a person has no matching row and must still
   be listed. */
$where = "WHERE departments.active = 1";
$search_types = '';
$search_values = [];

if ($search_term !== '') {
    /* Added to $where rather than to the finished SQL, because it has to sit
       before ORDER BY and LIMIT. */
    $where .= ' AND (departments.name LIKE ? OR departments.description LIKE ?'
        . ' OR departments.typed LIKE ? OR people.name LIKE ?)';
    /* Four words to search. The page numbers are bound separately below. */
    $search_types = 'ssss';
    $search_values = [$like, $like, $like, $like];
}

$count_sql = "SELECT COUNT(*) FROM departments
              LEFT JOIN people ON people.id = departments.leader
              " . $where;
$list_sql = "SELECT departments.*, people.name AS linked_leader
             FROM departments
             LEFT JOIN people ON people.id = departments.leader
             " . $where
    . " ORDER BY departments.display_order ASC, departments.name ASC LIMIT ? OFFSET ?";

$current_page = page_number();
$total = db_count_prepared($conn, $count_sql, $search_types, $search_values);
$pagination = paginate($total, $per_page, $current_page);

$offset = $pagination['offset'];

$statement = $conn->prepare($list_sql);
$statement->bind_param($search_types . 'ii', ...array_merge($search_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$departments = [];
while ($row = $result->fetch_assoc()) {
    $departments[] = $row;
}
$statement->close();
?>

    <section class="py-5">
        <div class="container">

            <form class="row g-2 align-items-center mb-4" method="get" action="<?php echo url('departments'); ?>" role="search">
                <div class="col-12 col-md-5">
                    <label class="visually-hidden" for="department-search">Search the departments</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                        <input type="search" class="form-control" id="department-search" name="q"
                               value="<?php echo e($search_term); ?>" maxlength="100"
                               placeholder="Search the departments">
                    </div>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($search_term !== ''): ?>
                        <a class="btn btn-outline-secondary" href="<?php echo url('departments'); ?>">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php render_result_count($pagination, 'departments'); ?>

            <?php if (!$departments): ?>
                <div class="row justify-content-center mt-4">
                    <div class="col-lg-8">
                        <?php
                        if ($search_term !== '') {
                            empty_state('fa-magnifying-glass', 'No department found for "' . $search_term . '"',
                                'Try another word, or browse the whole list.');
                        } else {
                            empty_state('fa-sitemap', 'The departments are being listed',
                                'Every department of the church will appear here, with the name of the person '
                                . 'who leads it and how to join.');
                        }
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4 mt-1">
                    <?php
                    /* group_leader() is one query per card on a page of nine,
                       which is nothing at this size, and it means the list and
                       the detail page can never disagree about who leads what. */
                    foreach ($departments as $department):
                        $leader = group_leader($conn, $department);
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <article class="card h-100 border-0 shadow-sm rounded-4">
                                <div class="card-body p-4 d-flex flex-column">
                                    <h2 class="h5 mb-1">
                                        <a class="text-decoration-none stretched-link" href="<?php echo url('departments/' . $department['slug']); ?>">
                                            <?php echo e($department['name']); ?>
                                        </a>
                                    </h2>

                                    <?php if ($leader['name'] !== ''): ?>
                                        <p class="small text-body-secondary mb-2">
                                            <i class="fa-solid fa-user-tie me-1" aria-hidden="true"></i>
                                            <?php echo e($leader['name']); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($department['description'])): ?>
                                        <p class="text-body-secondary small mb-0"><?php echo e(excerpt($department['description'], 150)); ?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php render_pagination($pagination, ['q' => $search_term]); ?>
            <?php endif; ?>
        </div>
    </section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; ?>
