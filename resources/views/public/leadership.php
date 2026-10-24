<?php
/**
 * BUBAPC - Leadership
 *
 * The people who hold an office in the church. Each person is filed by the
 * category they were given in the admin panel, so a pastor appears under
 * Pastors because that is what was chosen, not because their job title happens
 * to contain a certain word. Anyone the church has not filed under one of the
 * five office groups is not on this page - they are on the people page under
 * their own group.
 *
 * Every person here also has a page of their own at /people/<slug>, which is
 * where the share buttons and the site search point.
 */

require_once __DIR__ . '/../../../app/init.php';

$page_title = 'Leadership';
$page_description = 'The pastors, elders and leaders who serve at ' . SITE_NAME . '.';
$canonical = 'leadership';

require_once __DIR__ . '/../../../app/includes/public/header.php';

page_header(
    'Our leadership',
    'These are the people who shepherd the church and carry the different parts of its work. '
    . 'Please feel free to greet them before or after a service.',
    [['Home', url('')], ['Our church', ''], ['Leadership', '']]
);

/* The five office groups only. The list is built in SQL from the same helper
   that fills the drop down in the admin form, so a group can never be added to
   one place and forgotten in the other. */
$leader_list = "'" . implode("','", array_keys(leader_categories())) . "'";

$statement = $conn->prepare("SELECT * FROM people
                             WHERE status = 1 AND category IN ($leader_list)
                             ORDER BY display_order ASC, name ASC");
$statement->execute();
$result = $statement->get_result();

$leaders_by_category = [];
while ($leader = $result->fetch_assoc()) {
    $leaders_by_category[$leader['category']][] = $leader;
}
$statement->close();
?>

    <section class="py-5">
        <div class="container">
            <?php
            $categories = leader_categories();
            $any_leaders = false;

            foreach ($categories as $group => $group_label):
                $people = $leaders_by_category[$group] ?? [];
                if (!$people) {
                    continue;
                }
                $any_leaders = true;
            ?>
                <div class="mb-5">
                    <?php section_heading($group_label, 'Serving the church', '', 2); ?>

                    <div class="row g-4">
                        <?php foreach ($people as $leader): ?>
                            <div class="col-sm-6 col-lg-4">
                                <article class="card h-100 border-0 shadow-sm rounded-4 text-center">
                                    <div class="card-body p-4">
                                        <?php if (!empty($leader['image'])): ?>
                                            <img src="<?php echo upload_url($leader['image']); ?>"
                                                 class="rounded-circle mb-3" width="120" height="120"
                                                 alt="<?php echo e($leader['name']); ?>"
                                                 style="object-fit:cover">
                                        <?php else: ?>
                                            <span class="brand-mark bg-brand-soft text-brand rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3 fs-4"
                                                  aria-hidden="true"><?php echo initials($leader['name']); ?></span>
                                        <?php endif; ?>

                                        <?php /* The whole card is the link, so the
                                                 stretched-link sits on the name and
                                                 the card can still be used as one
                                                 big target on a phone. */ ?>
                                        <h3 class="h5 mb-1">
                                            <a class="text-decoration-none stretched-link" href="<?php echo url('people/' . $leader['slug']); ?>">
                                                <?php echo e($leader['name']); ?>
                                            </a>
                                        </h3>
                                        <?php if ($leader['position'] !== ''): ?>
                                            <p class="small text-uppercase fw-bold text-gold-dark mb-2">
                                                <?php echo e($leader['position']); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php $cake = birthday_badge($leader['born']);
                                        if ($cake !== ''): ?>
                                            <p class="mb-2"><?php echo $cake; ?></p>
                                        <?php endif; ?>

                                        <?php if (!empty($leader['bio'])): ?>
                                            <p class="text-body-secondary small mb-0">
                                                <?php echo e(excerpt($leader['bio'], 180)); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$any_leaders): ?>
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <?php
                        empty_state('fa-user-tie', 'The leadership page is being filled in',
                            'The names and photographs of our pastors, elders and department leaders '
                            . 'will be published here as soon as they are added.',
                            '<a class="btn btn-primary" href="' . url('contact') . '">Ask who to speak to</a>');
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="py-5 bg-brand-soft">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h2 class="mb-3">Serve with us</h2>
                    <p class="text-body-secondary mb-4">
                        There is a place for you here. Our departments and families each have
                        something they are responsible for, and there is always more to do
                        than there are hands to do it with.
                    </p>
                    <div class="d-flex justify-content-center flex-wrap gap-2">
                        <a class="btn btn-gold" href="<?php echo url('departments'); ?>">See the departments</a>
                        <a class="btn btn-outline-primary" href="<?php echo url('families'); ?>">See the families</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../../../app/includes/public/footer.php'; ?>
