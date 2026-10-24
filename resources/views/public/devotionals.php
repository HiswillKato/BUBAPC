<?php
/**
 * BUBAPC - The devotional archive
 *
 * With a slug in the address (/devotionals/a-praise-and-worship-mindset) this
 * shows one message. Without one it shows the whole archive, newest first,
 * with a search box and page numbers.
 *
 * The search word always goes through a prepared statement, never into the
 * SQL by hand.
 */

require_once __DIR__ . '/../../../app/init.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$per_page = 9;

/* ---------------------------------------------------------------------
   1. ONE DEVOTIONAL WHEN A SLUG WAS GIVEN
   --------------------------------------------------------------------- */
if ($slug !== '') {
    $statement = $conn->prepare("SELECT * FROM devotionals WHERE slug = ? AND status = 'published' LIMIT 1");
    $statement->bind_param('s', $slug);
    $statement->execute();
    $result = $statement->get_result();
    $devotional = $result->fetch_assoc();
    $statement->close();

    if (!$devotional) {
        http_response_code(404);
        $error_code = 404;
        $error_title = 'Devotional not found';
        $error_message = 'That devotional is not on our website. It may have been moved, '
            . 'or it may still be a draft. The archive has everything we have published.';
        require __DIR__ . '/../../../app/includes/error-page.php';
        exit;
    }

    $page_title = $devotional['title'];
    $page_description = excerpt(strip_tags(html_entity_decode($devotional['message'])), 160);
    $canonical = 'devotionals/' . $devotional['slug'];

    require_once __DIR__ . '/../../../app/includes/public/header.php';

    page_header(
        $devotional['title'],
        'Devotional for ' . format_date($devotional['devotional_date'], 'l, j F Y'),
        [['Home', url('')], ['Devotional', url('devotionals')], [$devotional['title'], '']]
    );
    ?>

    <section class="py-5">
        <div class="container">
            <?php render_devotional($devotional, url('devotionals/' . $devotional['slug'])); ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-5 pt-4 border-top">
                <a class="btn btn-outline-primary" href="<?php echo url('devotionals'); ?>">
                    <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Devotional archive
                </a>
                <a class="btn btn-primary" href="<?php echo url('devotional'); ?>">
                    <i class="fa-solid fa-book-bible me-1" aria-hidden="true"></i>Today&rsquo;s devotional
                </a>
            </div>
        </div>
    </section>

    <?php
    require_once __DIR__ . '/../../../app/includes/public/footer.php';
    exit;
}

/* ---------------------------------------------------------------------
   2. THE ARCHIVE
   --------------------------------------------------------------------- */
$page_title = 'Devotional archive';
$page_description = 'Every devotional we have published at ' . SITE_NAME . ', newest first.';
$canonical = 'devotionals';

require_once __DIR__ . '/../../../app/includes/public/header.php';

page_header(
    'Devotional archive',
    'Every message we have published, newest first. Search for a title, a scripture '
    . 'or a word you remember reading about.',
    /* This *is* the archive, so the breadcrumb stops at Devotional rather
       than linking a parent that points back here. */
    [['Home', url('')], ['Devotional', '']]
);

$search_term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$like = '%' . $search_term . '%';
$search_clause = '';

$count_sql = "SELECT COUNT(*) FROM devotionals WHERE status = 'published'";
$list_sql = "SELECT * FROM devotionals WHERE status = 'published'
             ORDER BY devotional_date DESC, id DESC LIMIT ? OFFSET ?";

/* The type letters must follow the placeholders in the same order: one 's'
   for every LIKE (the search word is text) and 'ii' for LIMIT and OFFSET. */
$types = 'ii';
$values = [];

if ($search_term !== '') {
    $search_clause = ' AND (title LIKE ? OR message LIKE ? OR scripture_reference LIKE ?)';
    $count_sql .= $search_clause;
    $list_sql .= $search_clause;
    $types = 'ssii';
    $values = [$like, $like, $like];
}

$current_page = page_number();
$pagination = paginate(db_count($conn, $count_sql), $per_page, $current_page);

$offset = $pagination['offset'];
$values[] = $per_page;
$values[] = $offset;

$statement = $conn->prepare($list_sql);
$statement->bind_param($types, ...$values);
$statement->execute();
$result = $statement->get_result();

$devotionals = [];
while ($row = $result->fetch_assoc()) {
    $devotionals[] = $row;
}
$statement->close();
?>

    <section class="py-5">
        <div class="container">

            <form class="row g-2 align-items-center mb-4" method="get" action="<?php echo url('devotionals'); ?>" role="search">
                <div class="col-12 col-md-5">
                    <label class="visually-hidden" for="devotional-search">Search the archive</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                        <input type="search" class="form-control" id="devotional-search" name="q"
                               value="<?php echo e($search_term); ?>" maxlength="100"
                               placeholder="Search the archive">
                    </div>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($search_term !== ''): ?>
                        <a class="btn btn-outline-secondary" href="<?php echo url('devotionals'); ?>">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php render_result_count($pagination, 'devotionals'); ?>

            <?php if (!$devotionals): ?>
                <div class="row justify-content-center mt-4">
                    <div class="col-lg-8">
                        <?php
                        if ($search_term !== '') {
                            empty_state('fa-magnifying-glass', 'Nothing found for "' . $search_term . '"',
                                'Try a shorter word, or a scripture reference such as Psalm 34.');
                        } else {
                            empty_state('fa-book-bible', 'The archive is empty for now',
                                'Every devotional we publish will be kept here, so you can come back to it later.');
                        }
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4 mt-1">
                    <?php foreach ($devotionals as $devotional): ?>
                        <div class="col-md-6 col-lg-4">
                            <article class="card h-100 border-0 shadow-sm rounded-4 text-center">
                                <div class="card-body p-4 d-flex flex-column">
                                    <p class="small text-uppercase fw-bold text-gold-dark mb-2">
                                        <?php echo format_date($devotional['devotional_date'], 'j F Y'); ?>
                                    </p>

                                    <h2 class="h5 mb-2">
                                        <a class="text-decoration-none stretched-link" href="<?php echo url('devotionals/' . $devotional['slug']); ?>">
                                            <?php echo e($devotional['title']); ?>
                                        </a>
                                    </h2>

                                    <?php if (!empty($devotional['scripture_reference'])): ?>
                                        <p class="small text-brand fw-semibold mb-2"><?php echo e($devotional['scripture_reference']); ?></p>
                                    <?php endif; ?>

                                    <p class="text-body-secondary small mb-0"><?php echo e(excerpt($devotional['message'], 150)); ?></p>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php render_pagination($pagination, ['q' => $search_term]); ?>
            <?php endif; ?>
        </div>
    </section>

    </section>

    <section class="py-5 bg-light-subtle">
        <div class="container">
            <div class="text-center">
                <a class="btn btn-outline-primary" href="<?php echo url('devotionals/archive'); ?>">Browse the full archive</a>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../../../app/includes/public/footer.php'; ?>
