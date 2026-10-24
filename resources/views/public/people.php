<?php
/**
 * BUBAPC - People
 *
 * One page for everybody the church lists. The group in the address decides
 * what is shown:
 *
 *   /people                  the people who hold an office
 *   /people?group=pastors    the pastors
 *   /people?group=alumni     the alumni
 *   /people/sis-grace-auma   one person, with their story
 *
 * The leadership page shows the same people as the leadership group, but it is
 * the page the church links to from the menu. This page is the full list, so a
 * pastor or an alumnus can be found here.
 *
 * The group names all come from people_filters(), which checks the word in the
 * address against the church's own list of groups. A word that is not on the
 * list, and the plain /people address itself, falls back to the office holders
 * rather than an error, so a mistyped or hand-edited address is never a dead
 * end.
 */

require_once __DIR__ . '/../../../app/init.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$group = people_filter_from_url();
$per_page = 12;

$categories = people_categories();

/* ---------------------------------------------------------------------
   1. ONE PERSON WHEN AN ADDRESS WAS GIVEN
   --------------------------------------------------------------------- */
if ($slug !== '') {
    $person = person_by_slug($conn, $slug);

    if (!$person) {
        http_response_code(404);
        $error_code = 404;
        $error_title = 'Person not found';
        $error_message = 'That person is not on our website. They may have been renamed, '
            . 'or they may be listed as hidden for now. Everyone we list is on the people page.';
        require __DIR__ . '/../../../app/includes/error-page.php';
        exit;
    }

    $group_label = $categories[$person['category']] ?? 'Our church';
    $where_they_belong = person_groups($conn, $person);
    $family = $where_they_belong['family'];
    $department = $where_they_belong['department'];
    $others_in_family = people_in_group($conn, 'family', (int) $person['family'], 12, (int) $person['id']);

    $about = $person['bio'] ?: $person['testimony'];

    $page_title = $person['name'];
    $page_description = excerpt(strip_tags(html_entity_decode($about ?: $group_label)), 160);
    $canonical = 'people/' . $person['slug'];

    require_once __DIR__ . '/../../../app/includes/public/header.php';

    /* page_header() escapes whatever it is given, so the subtitle is plain
       text with a real middot in it rather than an &middot; entity, which
       would arrive on the page as "&amp;middot;". */
    $subtitle = $group_label;
    if ($person['position'] !== '') {
        $subtitle .= " \u{00B7} " . $person['position'];
    }

    page_header(
        $person['name'],
        $subtitle,
        [['Home', url('')], ['People', url('people')], [$person['name'], '']]
    );
    ?>

    <section class="py-5">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-8">

                    <?php if (!empty($person['testimony']) && $person['category'] === 'alumnus'): ?>
                        <div class="card border-0 border-start border-4 border-warning bg-warning-subtle rounded-4 mb-4">
                            <div class="card-body">
                                <p class="text-uppercase fw-bold small text-gold-dark mb-2">Testimony</p>
                                <?php echo paragraphs($person['testimony']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($person['bio'])): ?>
                        <div class="fs-5">
                            <?php echo paragraphs($person['bio']); ?>
                        </div>
                    <?php elseif (empty($person['testimony'])): ?>
                        <p class="text-body-secondary">
                            More about <?php echo e($person['name']); ?> will be posted here soon.
                        </p>
                    <?php endif; ?>

                    <?php if ($person['category'] === 'alumnus' && !empty($person['testimony'])
                              && !empty($person['bio'])): ?>
                        <h2 class="h4 mt-5 mb-3">Testimony</h2>
                        <div class="fs-5"><?php echo paragraphs($person['testimony']); ?></div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <?php share_bar($person['name'], url('people/' . $person['slug'])); ?>
                    </div>

                    <a class="btn btn-outline-primary mt-4" href="<?php echo url('people'); ?>">
                        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Everyone who serves
                    </a>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-body p-4 text-center">
                            <?php /* The photograph when there is one, their initials when there is not.
                                   Both are circles of the same size so the page
                                   does not jump about as rows are added. */
                            if (!empty($person['image'])): ?>
                                <img src="<?php echo upload_url($person['image']); ?>"
                                     alt="<?php echo e($person['name']); ?>"
                                     class="rounded-circle mb-3" width="140" height="140"
                                     style="object-fit:cover">
                            <?php else: ?>
                                <span class="brand-mark bg-brand-soft text-brand rounded-circle d-inline-flex align-items-center justify-content-center mb-3 fs-2"
                                      style="width:140px;height:140px" aria-hidden="true">
                                    <?php echo initials($person['name']); ?>
                                </span>
                            <?php endif; ?>

                            <h2 class="h5 mb-1"><?php echo e($person['name']); ?></h2>

                            <?php if ($person['position'] !== ''): ?>
                                <p class="small text-uppercase fw-bold text-gold-dark mb-2">
                                    <?php echo e($person['position']); ?>
                                </p>
                            <?php endif; ?>

                            <span class="badge rounded-pill text-bg-light border"><?php echo e($group_label); ?></span>

                            <dl class="list-unstyled small text-start mt-4 mb-0 d-grid gap-2">
                                <?php if (!empty($person['joined_year'])): ?>
                                    <div class="d-flex justify-content-between gap-3">
                                        <dt class="text-body-secondary fw-normal">Joined</dt>
                                        <dd class="mb-0 fw-semibold"><?php echo (int) $person['joined_year']; ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($person['graduated_year'])): ?>
                                    <div class="d-flex justify-content-between gap-3">
                                        <dt class="text-body-secondary fw-normal">Class of</dt>
                                        <dd class="mb-0 fw-semibold"><?php echo (int) $person['graduated_year']; ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php /* The day and the month only - the year is
                                         on this person's own page and nowhere else. */ ?>
                                <?php if (!empty($person['born'])): ?>
                                    <div class="d-flex justify-content-between gap-3">
                                        <dt class="text-body-secondary fw-normal">Birthday</dt>
                                        <dd class="mb-0 fw-semibold text-end">
                                            <?php echo birthday_label($person['born']); ?>
                                            <?php if (birthday_today($person['born'])): ?>
                                                <span class="badge rounded-pill text-bg-warning ms-1">
                                                    <i class="fa-solid fa-cake-candles me-1" aria-hidden="true"></i>Today!
                                                </span>
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>

                                <?php if ($family): ?>
                                    <div class="d-flex justify-content-between gap-3">
                                        <dt class="text-body-secondary fw-normal">Family</dt>
                                        <dd class="mb-0 fw-semibold">
                                            <a href="<?php echo url('families/' . $family['slug']); ?>">
                                                <?php echo e($family['name']); ?>
                                            </a>
                                        </dd>
                                    </div>
                                <?php endif; ?>

                                <?php if ($department): ?>
                                    <div class="d-flex justify-content-between gap-3">
                                        <dt class="text-body-secondary fw-normal">Department</dt>
                                        <dd class="mb-0 fw-semibold">
                                            <a href="<?php echo url('departments/' . $department['slug']); ?>">
                                                <?php echo e($department['name']); ?>
                                            </a>
                                        </dd>
                                    </div>
                                <?php endif; ?>

                                <?php if ($person['phone'] !== ''): ?>
                                    <div class="d-flex justify-content-between gap-3">
                                        <dt class="text-body-secondary fw-normal">Phone</dt>
                                        <dd class="mb-0 fw-semibold">
                                            <a 
                                               href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', $person['phone'])); ?>">
                                                <?php echo e($person['phone']); ?>
                                            </a>
                                        </dd>
                                    </div>
                                <?php endif; ?>

                                <?php if ($person['email'] !== ''): ?>
                                    <div class="d-flex justify-content-between gap-3">
                                        <dt class="text-body-secondary fw-normal">Email</dt>
                                        <dd class="mb-0 fw-semibold text-break">
                                            <a href="mailto:<?php echo e($person['email']); ?>">
                                                <?php echo e($person['email']); ?>
                                            </a>
                                        </dd>
                                    </div>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>

                    <?php if ($family): ?>
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-body p-4">
                                <p class="text-uppercase fw-bold small text-gold-dark mb-2">The family</p>
                                <p class="small text-body-secondary">
                                    <?php echo e($family['name']); ?> is a group of students who meet
                                    through the week for prayer, for the Word, and to look after one another.
                                </p>
                                <a class="btn btn-primary btn-sm w-100" href="<?php echo url('families/' . $family['slug']); ?>">
                                    About <?php echo e($family['name']); ?>
                                </a>

                                <?php if ($others_in_family): ?>
                                    <hr>

                                    <p class="text-uppercase fw-bold small text-gold-dark mb-2">
                                        Others in this family
                                    </p>
                                    <ul class="list-unstyled d-grid gap-2 mb-0">
                                        <?php foreach ($others_in_family as $other): ?>
                                            <li>
                                                <a class="small"
                                                   href="<?php echo url('people/' . $other['slug']); ?>">
                                                    <i class="fa-solid fa-chevron-right text-gold-dark me-1" aria-hidden="true"></i>
                                                    <?php echo e($other['name']); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php
    require_once __DIR__ . '/../../../app/includes/public/footer.php';
    exit;
}

/* ---------------------------------------------------------------------
   2. THE LIST OF PEOPLE
   --------------------------------------------------------------------- */
$filters = people_filters();
$chosen = $filters[$group];
$group_name = $chosen['label'];

$page_titles = [
    'leadership' => 'Everyone who serves',
    'pastors'    => 'Our pastors',
    'alumni'     => 'Our alumni',
];

$page_subtitles = [
    'leadership' => 'Everyone who holds an office and carries a part of the work of the church.',
    'pastors'    => 'The pastors who shepherd the church and lead its work.',
    'alumni'     => 'Those who have been through the students\' work here before, and what it has meant to them since.',
];

/* The tab names are plural because they head a list, but the empty message
   talks about one person at a time, so it needs the single word. These are
   hand written rather than cut off the label, because "Alumni" has no
   singular form to cut down to. */
$group_one = [
    'leadership' => 'person',
    'pastors'    => 'pastor',
    'alumni'     => 'alumnus',
];

$page_title = $page_titles[$group];
$page_description = $page_subtitles[$group];
$canonical = $group === 'leadership' ? 'people' : 'people?group=' . $group;

require_once __DIR__ . '/../../../app/includes/public/header.php';

page_header(
    $page_title,
    $page_subtitles[$group],
    [['Home', url('')], ['People', '']]
);

/* The group filter is written out by people_filters() with nothing from the
   browser in it, because the group is checked against the list first. That is
   why there are no placeholders for it here. */
$search_term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$like = '%' . $search_term . '%';

/* The "Everyone" tab has no group condition of its own, so its where_sql comes
   back empty. Putting it in would leave a "WHERE status = 1 AND" with nothing
   after it, which is a syntax error, so the condition is only added when there
   is one. */
$where = "WHERE status = 1";
if ($chosen['where_sql'] !== '') {
    $where .= ' AND ' . $chosen['where_sql'];
}

$search_types = '';
$search_values = [];

if ($search_term !== '') {
    /* The search is added to $where, not to the finished SQL, because it has
       to sit before ORDER BY and LIMIT. Appending it afterwards would put a
       WHERE in the middle of a paging clause. */
    $where .= ' AND (name LIKE ? OR position LIKE ? OR bio LIKE ? OR testimony LIKE ?)';
    /* Four words to search, so four letters. The page numbers are counted
       separately below. */
    $search_types = 'ssss';
    $search_values = [$like, $like, $like, $like];
}

$count_sql = "SELECT COUNT(*) FROM people " . $where;
$list_sql = "SELECT * FROM people " . $where
    . " ORDER BY category ASC, display_order ASC, name ASC LIMIT ? OFFSET ?";

$current_page = page_number();

/* The count carries the same search words, so it has to be prepared as well. */
$total = db_count_prepared($conn, $count_sql, $search_types, $search_values);
$pagination = paginate($total, $per_page, $current_page);

$offset = $pagination['offset'];

/* Now the list: the search words first, in the order the placeholders appear,
   then the two page numbers. */
$statement = $conn->prepare($list_sql);
$statement->bind_param($search_types . 'ii', ...array_merge($search_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$people = [];
while ($row = $result->fetch_assoc()) {
    $people[] = $row;
}
$statement->close();
?>

    <section class="py-5">
        <div class="container">

            <!-- ============ THE GROUP TABS ============ -->
            <ul class="nav nav-pills mb-4 gap-2 flex-wrap">
                <?php foreach ($filters as $key => $filter): ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $group === $key ? ' active' : ''; ?>"
                           href="<?php echo url('people?group=' . $key); ?>">
                            <?php echo e($filter['label']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form class="row g-2 align-items-center mb-4" method="get"
                  action="<?php echo url('people'); ?>" role="search">
                <input type="hidden" name="group" value="<?php echo e($group); ?>">
                <div class="col-12 col-md-5">
                    <label class="visually-hidden" for="people-search">Search the people</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                        <input type="search" class="form-control" id="people-search" name="q"
                               value="<?php echo e($search_term); ?>" maxlength="100"
                               placeholder="Search by name">
                    </div>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($search_term !== ''): ?>
                        <a class="btn btn-outline-secondary" href="<?php echo url('people?group=' . $group); ?>">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php render_result_count($pagination, strtolower($group_name)); ?>

            <?php if (!$people): ?>
                <div class="row justify-content-center mt-4">
                    <div class="col-lg-8">
                        <?php
                        if ($search_term !== '') {
                            empty_state('fa-magnifying-glass', 'Nobody found for "' . $search_term . '"',
                                'Try another name, or browse the whole list.');
                        } else {
                            empty_state('fa-users', 'This list is being filled in',
                                'Every ' . $group_one[$group] . ' at ' . SITE_NAME . ' will appear here. '
                                . 'Names and photographs are added from the admin panel.');
                        }
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4 mt-1">
                    <?php foreach ($people as $person): ?>
                        <div class="col-sm-6 col-lg-4 col-xl-3">
                            <article class="card h-100 border-0 shadow-sm rounded-4 text-center">
                                <div class="card-body p-4">
                                    <?php if (!empty($person['image'])): ?>
                                        <img src="<?php echo upload_url($person['image']); ?>"
                                             alt="<?php echo e($person['name']); ?>"
                                             class="rounded-circle mb-3" width="120" height="120"
                                             style="object-fit:cover">
                                    <?php else: ?>
                                        <span class="brand-mark bg-brand-soft text-brand rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3 fs-3"
                                              style="width:120px;height:120px" aria-hidden="true">
                                            <?php echo initials($person['name']); ?>
                                        </span>
                                    <?php endif; ?>

                                    <h2 class="h6 mb-1">
                                        <a class="text-decoration-none stretched-link" href="<?php echo url('people/' . $person['slug']); ?>">
                                            <?php echo e($person['name']); ?>
                                        </a>
                                    </h2>

                                    <?php if ($person['position'] !== ''): ?>
                                        <p class="small text-uppercase fw-bold text-gold-dark mb-2">
                                            <?php echo e($person['position']); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="small text-body-secondary mb-2">
                                            <?php echo e($categories[$person['category']] ?? ''); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php /* Somebody who celebrates this month wears the cake on
                                           their card for the whole month, not for one day. */ ?>
                                    <?php $cake = birthday_badge($person['born']);
                                    if ($cake !== ''): ?>
                                        <p class="mb-2"><?php echo $cake; ?></p>
                                    <?php endif; ?>

                                    <?php if (!empty($person['graduated_year'])): ?>
                                        <p class="small text-body-secondary mb-0">Class of <?php echo (int) $person['graduated_year']; ?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php render_pagination($pagination, ['group' => $group, 'q' => $search_term]); ?>
            <?php endif; ?>
        </div>
    </section>

<?php require_once __DIR__ . '/../../../app/includes/public/footer.php'; ?>
