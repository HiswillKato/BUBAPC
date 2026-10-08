<?php
/**
 * BUBAPC - The bottom of every public page.
 * app/includes/public/footer.php includes it, so a page never includes this file directly.
 *
 * It is written with Bootstrap classes only (row, col, card, list-group, form).
 */

$available_social = social_links();
$current_year = date('Y');
?>
    </main>

    <footer class="bg-brand-deep text-white mt-5 pt-5">
        <div class="container">
            <div class="row g-4 g-lg-5">

                <div class="col-lg-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="brand-mark bg-white text-brand-deep rounded-3 d-inline-flex align-items-center justify-content-center" aria-hidden="true">
                            <svg viewBox="0 0 48 48" focusable="false">
                                <path d="M24 8c2.4 3.6.6 6-1.2 7.8-1.8 1.8-3.6 3-3.6 6a4.8 4.8 0 0 0 9.6 0c0-1.8-.6-3-1.2-4.2 3.6 1.8 6 4.8 6 9A9.6 9.6 0 0 1 24 36a9.6 9.6 0 0 1-9.6-9.6c0-6.6 4.8-11.4 9.6-18.4z"
                                      fill="currentColor"/>
                            </svg>
                        </span>
                        <div>
                            <p class="h5 text-white mb-0"><?php echo e(SITE_NAME); ?></p>
                            <p class="small text-white-50 mb-0"><?php echo e(SITE_FULL_NAME); ?></p>
                        </div>
                    </div>

                    <p class="text-white-50 small mt-3 mb-0">
                        <?php echo e(SITE_TAGLINE); ?> &mdash; raising a generation of students
                        who know God and make Him known on every campus and in every home.
                    </p>

                    <?php if ($available_social): ?>
                        <div class="d-flex gap-2 mt-4">
                            <?php foreach ($available_social as $network => $social): ?>
                                <a class="btn btn-sm btn-outline-light rounded-circle d-inline-flex align-items-center justify-content-center"
                                   style="width:40px;height:40px;padding:0"
                                   href="<?php echo e($social['url']); ?>" target="_blank" rel="noopener"
                                   aria-label="BUBAPC on <?php echo e($network); ?>">
                                    <i class="<?php echo e($social['icon']); ?>" aria-hidden="true"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-6 col-lg-2">
                    <p class="text-uppercase small fw-bold text-white">Explore</p>
                    <ul class="list-unstyled small d-grid gap-2">
                        <li><a class="link-light" href="<?php echo url('about'); ?>">About BUBAPC</a></li>
                        <li><a class="link-light" href="<?php echo url('leadership'); ?>">Leadership</a></li>
                        <li><a class="link-light" href="<?php echo url('programs'); ?>">Weekly Program</a></li>
                        <li><a class="link-light" href="<?php echo url('events'); ?>">Events</a></li>
                        <li><a class="link-light" href="<?php echo url('departments'); ?>">Departments</a></li>
                        <li><a class="link-light" href="<?php echo url('families'); ?>">Families</a></li>
                        <li><a class="link-light" href="<?php echo url('people?group=students'); ?>">Students</a></li>
                        <li><a class="link-light" href="<?php echo url('people?group=alumni'); ?>">Alumni</a></li>
                    </ul>
                </div>

                <div class="col-6 col-lg-2">
                    <p class="text-uppercase small fw-bold text-white">Media</p>
                    <ul class="list-unstyled small d-grid gap-2">
                        <li><a class="link-light" href="<?php echo url('devotional'); ?>">Today&rsquo;s Devotional</a></li>
                        <li><a class="link-light" href="<?php echo url('devotionals'); ?>">Devotional Archive</a></li>
                        <li><a class="link-light" href="<?php echo url('sermons'); ?>">Sermons</a></li>
                        <li><a class="link-light" href="<?php echo url('gallery'); ?>">Gallery</a></li>
                        <li><a class="link-light" href="<?php echo url('announcements'); ?>">Announcements</a></li>
                    </ul>
                </div>

                <div class="col-lg-4">
                    <p class="text-uppercase small fw-bold text-white">Stay Connected</p>
                    <p class="text-white-50 small">
                        Receive the daily devotional and news about our programs and events.
                    </p>

                    <form action="<?php echo url('newsletter-subscribe'); ?>" method="post"
                          data-newsletter-form novalidate>
                        <?php echo csrf_field(); ?>
                        <label class="visually-hidden" for="newsletter-email">Your email address</label>
                        <div class="input-group">
                            <input type="email" id="newsletter-email" name="email" class="form-control"
                                   placeholder="Your email address" required maxlength="190" autocomplete="email">
                            <button type="submit" class="btn btn-gold">
                                <span class="newsletter-button-text">Subscribe</span>
                            </button>
                        </div>
                        <p class="newsletter-message small mt-2 mb-0" data-newsletter-message
                           role="status" aria-live="polite"></p>
                    </form>

                    <?php /* The phone number and address below are only as good as a
                             tap away, but the contact form is a different thing -
                             it is how somebody writes to the church - so it gets a
                             link of its own rather than being left to the column
                             heading. It used to sit in the Media list, where the
                             one thing you cannot do by reading sat among the
                             things you can. */ ?>
                    <p class="mb-2">
                        <a class="link-light fw-semibold" href="<?php echo url('contact'); ?>">
                            <i class="fa-solid fa-envelope-open-text text-warning me-1" aria-hidden="true"></i>Contact Us
                        </a>
                    </p>

                    <ul class="list-unstyled small text-white-50 d-grid gap-2 mt-4">
                        <?php if (SITE_LOCATION !== ''): ?>
                            <li class="d-flex gap-2">
                                <i class="fa-solid fa-location-dot text-warning" aria-hidden="true"></i>
                                <span><?php echo e(SITE_LOCATION); ?></span>
                            </li>
                        <?php endif; ?>
                        <?php if (SITE_PHONE !== ''): ?>
                            <li class="d-flex gap-2">
                                <i class="fa-solid fa-phone text-warning" aria-hidden="true"></i>
                                <a class="link-light" href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', SITE_PHONE)); ?>"><?php echo e(SITE_PHONE); ?></a>
                            </li>
                        <?php endif; ?>
                        <?php if (SITE_EMAIL !== ''): ?>
                            <li class="d-flex gap-2">
                                <i class="fa-solid fa-envelope text-warning" aria-hidden="true"></i>
                                <a class="link-light" href="mailto:<?php echo e(SITE_EMAIL); ?>"><?php echo e(SITE_EMAIL); ?></a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-5 py-4 border-top border-secondary">
                <p class="small text-white-50 mb-0">
                    &copy; <?php echo e($current_year); ?> <?php echo e(SITE_FULL_NAME); ?>. All rights reserved.
                </p>
                <p class="small text-warning fw-bold mb-0">
                    <i class="fa-solid fa-fire" aria-hidden="true"></i> <?php echo e(SITE_TAGLINE); ?>
                </p>
            </div>
        </div>
    </footer>

    <button class="back-to-top btn btn-primary rounded-circle d-inline-flex align-items-center justify-content-center" type="button"
            id="back-to-top" aria-label="Back to top" hidden>
        <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
    </button>

    <script src="<?php echo asset('js/bootstrap.bundle.min.js'); ?>"></script>
    <script src="<?php echo asset('js/main.js'); ?>"></script>
</body>
</html>
