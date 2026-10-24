<?php
/**
 * BUBAPC - Families
 *
 * A family is a group of students who meet together through the week: for
 * prayer, for the Word, and to look after one another. The families table has
 * the same shape as the departments table, so this page is written in the
 * same shape as departments.php.
 *
 * With a slug in the address (/families/the-okafor-family) this shows one
 * family. Without one it shows the whole list.
 */

require_once __DIR__ . '/../../../app/init.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$per_page = 9;

/* ---------------------------------------------------------------------
   1. ONE FAMILY WHEN A SLUG WAS GIVEN
   --------------------------------------------------------------------- */
if ($slug !== '') {
    $statement = $conn->prepare("SELECT * FROM families WHERE slug = ? AND active = 1 LIMIT 1");
    $statement->bind_param('s', $slug);
    $statement->execute();
    $result = $statement->get_result();
    $family = $result->fetch_assoc();
    $statement->close();

    if (!$family) {
        http_response_code(404);
        $error_code = 404;
        $error_title = 'Family not found';
        $error_message = 'That family is not on our website. It may have been renamed, '
            . 'or it may be listed as hidden for now. The full list is on the families page.';
        require __DIR__ . '/../../../app/includes/error-page.php';
        exit;
    }

    $page_title = $family['name'];
    $page_description = excerpt(strip_tags(html_entity_decode($family['description'] ?: $family['name'])), 160);
    $canonical = 'families/' . $family['slug'];

    /* The leader is looked up through group_leader() rather than read straight
       off the row, so a leader who has a page of their own is linked and their
       name follows any change to it. A name typed in by hand still shows for
       the leaders who do not have a page. */
    $leader = group_leader($conn, $family);

    /* The students in this family, so a page that says a family is "a group of
       students" can actually show the group. */
    $members = people_in_group($conn, 'family', (int) $family['id'], 24);

    require_once __DIR__ . '/../../../app/includes/public/header.php';

    page_header(
        $family['name'],
        $leader['name'] !== '' ? 'Led by ' . $leader['name'] : 'A family of students at ' . SITE_NAME . '.',
        [['Home', url('')], ['Our church', url('leadership')], ['Families', url('families')], [$family['name'], '']]
    );

    $statement = $conn->prepare("SELECT name, slug FROM families
                                 WHERE active = 1 AND id <> ?
                                 ORDER BY display_order ASC, name ASC LIMIT 6");
    $other_id = (int) $family['id'];
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
                        <?php echo paragraphs($family['description'] ?: 'More about this family will be posted here soon.'); ?>
                    </div>

                    <?php if ($members): ?>
                        <h2 class="h4 mt-5 mb-3">The students in this family</h2>
                        <div class="row g-3">
                            <?php foreach ($members as $member): ?>
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
                                        <span class="text-body">
                                            <?php echo e($member['name']); ?>
                                            <?php echo birthday_cake($member['born']); ?>
                                        </span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <?php share_bar($family['name'], url('families/' . $family['slug'])); ?>
                    </div>

                    <a class="btn btn-outline-primary mt-4" href="<?php echo url('families'); ?>">
                        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>All families
                    </a>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <p class="text-uppercase fw-bold small text-gold-dark mb-3">Find your family</p>
                            <p class="text-body-secondary small">
                                If you do not know which family to join yet, that is completely
                                fine. Come to any family meeting, or tell us and we will help
                                you find the one that fits you.
                            </p>
                            <a class="btn btn-primary w-100" href="<?php echo url('contact'); ?>">
                                <i class="fa-solid fa-envelope me-1" aria-hidden="true"></i>Ask for help
                            </a>

                            <?php if ($others): ?>
                                <hr>

                                <p class="text-uppercase fw-bold small text-gold-dark mb-2">Other families</p>
                                <ul class="list-unstyled d-grid gap-2 mb-0">
                                    <?php foreach ($others as $other): ?>
                                        <li>
                                            <a class="small"
                                               href="<?php echo url('families/' . $other['slug']); ?>">
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
    require_once __DIR__ . '/../../../app/includes/public/footer.php';
    exit;
}

/* ---------------------------------------------------------------------
   2. THE LIST OF FAMILIES
   --------------------------------------------------------------------- */
$page_title = 'Families';
$page_description = 'The families at ' . SITE_NAME . ' and how to find the one for you.';
$canonical = 'families';

require_once __DIR__ . '/../../../app/includes/public/header.php';

page_header(
    'Families',
    'Our students are gathered into families that meet through the week. '
    . 'In a family you are known, and you are looked after.',
    [['Home', url('')], ['Our church', url('leadership')], ['Families', '']]
);

$search_term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$like = '%' . $search_term . '%';

/* The list is joined to people so a search for a leader's name finds the family
   they lead, and so the card can show the linked name rather than a typed one
   that has drifted out of date. LEFT JOIN, not JOIN: a family whose leader was
   never added as a person has no matching row and must still be listed. */
$where = "WHERE families.active = 1";
$search_types = '';
$search_values = [];

if ($search_term !== '') {
    /* Added to $where rather than to the finished SQL, because it has to sit
       before ORDER BY and LIMIT. */
    $where .= ' AND (families.name LIKE ? OR families.description LIKE ?'
        . ' OR families.typed LIKE ? OR people.name LIKE ?)';
    /* Four words to search. The page numbers are bound separately below. */
    $search_types = 'ssss';
    $search_values = [$like, $like, $like, $like];
}

$count_sql = "SELECT COUNT(*) FROM families
              LEFT JOIN people ON people.id = families.leader
              " . $where;
$list_sql = "SELECT families.*, people.name AS linked_leader
             FROM families
             LEFT JOIN people ON people.id = families.leader
             " . $where
    . " ORDER BY families.display_order ASC, families.name ASC LIMIT ? OFFSET ?";

$current_page = page_number();
$total = db_count_prepared($conn, $count_sql, $search_types, $search_values);
$pagination = paginate($total, $per_page, $current_page);

$offset = $pagination['offset'];

$statement = $conn->prepare($list_sql);
$statement->bind_param($search_types . 'ii', ...array_merge($search_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$families = [];
while ($row = $result->fetch_assoc()) {
    $families[] = $row;
}
$statement->close();
?>

    <section class="py-5">
        <div class="container">

            <form class="row g-2 align-items-center mb-4" method="get" action="<?php echo url('families'); ?>" role="search">
                <div class="col-12 col-md-5">
                    <label class="visually-hidden" for="family-search">Search the families</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                        <input type="search" class="form-control" id="family-search" name="q"
                               value="<?php echo e($search_term); ?>" maxlength="100"
                               placeholder="Search the families">
                    </div>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($search_term !== ''): ?>
                        <a class="btn btn-outline-secondary" href="<?php echo url('families'); ?>">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php render_result_count($pagination, 'families'); ?>

            <?php if (!$families): ?>
                <div class="row justify-content-center mt-4">
                    <div class="col-lg-8">
                        <?php
                        if ($search_term !== '') {
                            empty_state('fa-magnifying-glass', 'No family found for "' . $search_term . '"',
                                'Try another word, or browse the whole list.');
                        } else {
                            empty_state('fa-users', 'The families are being listed',
                                'Every family at ' . SITE_NAME . ' will appear here, with the name of the '
                                . 'person who leads it and when it meets.');
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
                    foreach ($families as $family):
                        $leader = group_leader($conn, $family);
                        $member_count = family_member_count($conn, (int) $family['id']);
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <article class="card h-100 border-0 shadow-sm rounded-4">
                                <div class="card-body p-4 d-flex flex-column">
                                    <h2 class="h5 mb-1">
                                        <a class="text-decoration-none stretched-link" href="<?php echo url('families/' . $family['slug']); ?>">
                                            <?php echo e($family['name']); ?>
                                        </a>
                                    </h2>

                                    <?php if ($leader['name'] !== ''): ?>
                                        <p class="small text-body-secondary mb-2">
                                            <i class="fa-solid fa-user-tie me-1" aria-hidden="true"></i>
                                            <?php echo e($leader['name']); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($member_count > 0): ?>
                                        <p class="small text-body-secondary mb-2">
                                            <i class="fa-solid fa-users me-1" aria-hidden="true"></i>
                                            <?php echo (int) $member_count; ?>
                                            <?php echo $member_count === 1 ? 'student' : 'students'; ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($family['description'])): ?>
                                        <p class="text-body-secondary small mb-0"><?php echo e(excerpt($family['description'], 150)); ?></p>
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

<?php require_once __DIR__ . '/../../../app/includes/public/footer.php'; ?>
