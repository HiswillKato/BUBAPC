<?php
/**
 * BUBAPC - Site search
 *
 * One box that searches every part of the website at once. search_site() in
 * app/functions.php runs the seven searches and hands back the groups, so this
 * page only has to print them.
 */

require_once __DIR__ . '/../../../app/init.php';

$search_term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$results = ($search_term === '') ? [] : search_site($conn, $search_term);
$result_total = 0;
foreach ($results as $group_items) {
    $result_total += count($group_items);
}

$page_title = $search_term === '' ? 'Search' : 'Search results for ' . $search_term;
$page_description = 'Search ' . SITE_NAME . ' for sermons, devotionals, events, people and more.';
$canonical = 'search';

require_once __DIR__ . '/../partials/public/header.php';

page_header(
    $search_term === '' ? 'Search' : 'Search results',
    $search_term === ''
        ? 'Look for anything on this website: a sermon, a devotional, an event, a leader or a department.'
        : 'We found ' . $result_total . ' ' . ($result_total === 1 ? 'result' : 'results')
            . ' for "' . $search_term . '".',
    [['Home', url('')], ['Search', '']]
);
?>

    <section class="py-5">
        <div class="container">

            <form class="row g-2 align-items-center mb-5" method="get" action="<?php echo url('search'); ?>" role="search">
                <div class="col-12 col-md-8">
                    <label class="visually-hidden" for="site-search">Search the website</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                        <input type="search" class="form-control" id="site-search" name="q"
                               value="<?php echo e($search_term); ?>" maxlength="100"
                               placeholder="Sermons, devotionals, events, people&hellip;" autofocus>
                    </div>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary btn-lg w-100">Search</button>
                </div>
            </form>

            <?php if ($search_term === ''): ?>
                <div class="row g-4">
                    <?php
                    /* The shortcuts are the words most people come here for. */
                    $shortcuts = [
                        ['fa-book-bible', 'Devotionals', 'A daily message from the Word of God.', 'devotionals'],
                        ['fa-microphone-lines', 'Sermons', 'Watch and listen to past messages.', 'sermons'],
                        ['fa-calendar-day', 'Events', 'Retreats, conventions and special services.', 'events'],
                        ['fa-sitemap', 'Departments', 'The parts of the church and how to join.', 'departments'],
                        ['fa-users', 'Families', 'The families that meet through the week.', 'families'],
                        ['fa-images', 'Gallery', 'Photographs from the life of the church.', 'gallery'],
                    ];
                    ?>
                    <?php foreach ($shortcuts as $shortcut): ?>
                        <div class="col-sm-6 col-lg-4">
                            <a class="card h-100 border-0 shadow-sm rounded-4 text-decoration-none text-body"
                               href="<?php echo url($shortcut[3]); ?>">
                                <div class="card-body p-4">
                                    <i class="fa-solid <?php echo e($shortcut[0]); ?> fa-2x text-gold mb-3" aria-hidden="true"></i>
                                    <h2 class="h5 mb-1"><?php echo e($shortcut[1]); ?></h2>
                                    <p class="text-body-secondary small mb-0"><?php echo e($shortcut[2]); ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($result_total === 0): ?>
                <div class="row justify-content-center">
                    <div class="col-lg-7">
                        <?php
                        empty_state('fa-magnifying-glass', 'Nothing found for "' . $search_term . '"',
                            'Check the spelling, try one word instead of a phrase, or use the menu to find '
                            . 'your way. The devotional and sermon archives are the best places to look.',
                            '<a class="btn btn-primary" href="' . url('contact') . '">Ask us instead</a>');
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($results as $group_name => $group_items): ?>
                    <div class="mb-5">
                        <?php section_heading($group_name . 's', count($group_items) . ' found', '', 2); ?>

                        <?php if ($group_name === 'Departments' || $group_name === 'Families' || $group_name === 'Leadership'): ?>
                            <div class="row g-3">
                                <?php foreach ($group_items as $item): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <a class="card h-100 border-0 shadow-sm rounded-4 text-decoration-none text-body"
                                           href="<?php echo $item['url']; ?>">
                                            <div class="card-body p-3">
                                                <h3 class="h6 mb-1"><?php echo e($item['title']); ?></h3>
                                                <?php if ($item['meta'] !== ''): ?>
                                                    <p class="small text-gold-dark fw-semibold mb-1"><?php echo e($item['meta']); ?></p>
                                                <?php endif; ?>
                                                <p class="small text-body-secondary mb-0"><?php echo e($item['text']); ?></p>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="list-group rounded-4 shadow-sm">
                                <?php foreach ($group_items as $item): ?>
                                    <a class="list-group-item list-group-item-action p-3"
                                       href="<?php echo $item['url']; ?>">
                                        <h3 class="h6 mb-1"><?php echo e($item['title']); ?></h3>
                                        <?php if ($item['meta'] !== ''): ?>
                                            <p class="small text-body-secondary mb-1"><?php echo e($item['meta']); ?></p>
                                        <?php endif; ?>
                                        <p class="small text-body-secondary mb-0"><?php echo e($item['text']); ?></p>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

<?php require_once __DIR__ . '/../partials/public/footer.php'; ?>
