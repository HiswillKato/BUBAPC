<?php
/**
 * BUBAPC - Sermons
 *
 * A written summary of each message, with the video when the church has put
 * one on YouTube. There is no separate page for one sermon: every sermon is
 * listed here with an anchor (#sermon-4), which is what the site search links
 * to and what makes a link to a message shareable.
 */

require_once __DIR__ . '/../../../app/init.php';

$page_title = 'Sermons';
$page_description = 'Watch and listen to the messages preached at ' . SITE_NAME . '.';
$canonical = 'sermons';

require_once __DIR__ . '/../partials/public/header.php';

pageheader(
    'Sermons',
    'Every message we have recorded, newest first. Watch the video, read the notes, '
    . 'and take the message with you.',
    [['Home', url('')], ['Media', url('sermons')], ['Sermons', '']]
);

$search_term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$per_page = 8;
$like = '%' . $search_term . '%';
$search_clause = '';

$count_sql = "SELECT COUNT(*) FROM sermons WHERE status = 'published'";
$list_sql = "SELECT * FROM sermons WHERE status = 'published'
             ORDER BY sermon_date DESC, id DESC LIMIT ? OFFSET ?";

$types = 'ii';
$values = [];

if ($search_term !== '') {
    $search_clause = ' AND (title LIKE ? OR preacher LIKE ? OR description LIKE ? OR scripture LIKE ?)';
    $count_sql .= $search_clause;
    $list_sql .= $search_clause;
    $types = 'ssssii';
    $values = [$like, $like, $like, $like];
}

$current_page = pagenumber();
$pagination = paginate(dbcount($conn, $count_sql), $per_page, $current_page);

$offset = $pagination['offset'];
$values[] = $per_page;
$values[] = $offset;

$statement = $conn->prepare($list_sql);
$statement->bind_param($types, ...$values);
$statement->execute();
$result = $statement->get_result();

$sermons = [];
while ($row = $result->fetch_assoc()) {
    $sermons[] = $row;
}
$statement->close();
?>

    <section class="py-5">
        <div class="container">

            <form class="row g-2 align-items-center mb-4" method="get" action="<?php echo url('sermons'); ?>" role="search">
                <div class="col-12 col-md-5">
                    <label class="visually-hidden" for="sermon-search">Search the sermons</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                        <input type="search" class="form-control" id="sermon-search" name="q"
                               value="<?php echo e($search_term); ?>" maxlength="100"
                               placeholder="Search by title, preacher or scripture">
                    </div>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($search_term !== ''): ?>
                        <a class="btn btn-outline-secondary" href="<?php echo url('sermons'); ?>">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php renderresultcount($pagination, 'sermons'); ?>

            <?php if (!$sermons): ?>
                <div class="row justify-content-center mt-4">
                    <div class="col-lg-8">
                        <?php
                        if ($search_term !== '') {
                            emptystate('fa-magnifying-glass', 'No sermon found for "' . $search_term . '"',
                                'Try the preacher&rsquo;s name, part of the title, or a scripture reference.');
                        } else {
                            emptystate('fa-microphone-lines', 'The sermon archive is being filled',
                                'As soon as a message is recorded it will be listed here with the video '
                                . 'and the notes, so you can watch it again at any time.');
                        }
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4 mt-1">
                    <?php foreach ($sermons as $sermon): ?>
                        <?php $embed_url = youtubeembedurl($sermon['video_url']); ?>
                        <div class="col-lg-6" id="sermon-<?php echo (int) $sermon['id']; ?>">
                            <article class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                                <?php if ($embed_url !== ''): ?>
                                    <div class="ratio ratio-16x9">
                                        <iframe src="<?php echo e($embed_url); ?>"
                                                title="<?php echo e($sermon['title']); ?>"
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                allowfullscreen loading="lazy"></iframe>
                                    </div>
                                <?php else: ?>
                                    <div class="ratio ratio-16x9 bg-brand-soft d-flex align-items-center justify-content-center">
                                        <i class="fa-solid fa-microphone-lines fa-3x text-brand" aria-hidden="true"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="card-body p-4">
                                    <p class="small text-uppercase fw-bold text-gold-dark mb-2">
                                        <?php echo formatdate($sermon['sermon_date'], 'j F Y'); ?>
                                        <?php if ($sermon['scripture'] !== ''): ?>
                                            &middot; <?php echo e($sermon['scripture']); ?>
                                        <?php endif; ?>
                                    </p>

                                    <h2 class="h5 mb-1"><?php echo e($sermon['title']); ?></h2>

                                    <?php if ($sermon['preacher'] !== ''): ?>
                                        <p class="small text-body-secondary mb-2">
                                            <i class="fa-solid fa-user-tie me-1" aria-hidden="true"></i>
                                            <?php echo e($sermon['preacher']); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($sermon['description'])): ?>
                                        <p class="text-body-secondary small mb-0"><?php echo e($sermon['description']); ?></p>
                                    <?php endif; ?>

                                    <?php if ($sermon['video_url'] !== '' && $embed_url === ''): ?>
                                        <p class="small mb-0 mt-3">
                                            <a href="<?php echo e($sermon['video_url']); ?>"
                                               target="_blank" rel="noopener">
                                                <i class="fa-solid fa-play me-1" aria-hidden="true"></i>
                                                Watch on YouTube
                                            </a>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php renderpagination($pagination, ['q' => $search_term]); ?>
            <?php endif; ?>
        </div>
    </section>

<?php require_once __DIR__ . '/../partials/public/footer.php'; ?>
