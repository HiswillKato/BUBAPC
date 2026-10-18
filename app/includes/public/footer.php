<?php
$available_social = social_links();
$current_year = date('Y');
?>
    </main>
    <footer class="bg-dark text-white-50 mt-5 pt-5">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:2.5rem;height:2.5rem;background:rgba(255,255,255,0.1)" aria-hidden="true">
                            <svg viewBox="0 0 48 48" width="24" height="24" focusable="false"><path d="M24 8c2.4 3.6.6 6-1.2 7.8-1.8 1.8-3.6 3-3.6 6a4.8 4.8 0 0 0 9.6 0c0-1.8-.6-3-1.2-4.2 3.6 1.8 6 4.8 6 9A9.6 9.6 0 0 1 24 36a9.6 9.6 0 0 1-9.6-9.6c0-6.6 4.8-11.4 9.6-18.4z" fill="currentColor"/></svg>
                        </span>
                        <div>
                            <p class="h5 text-white mb-0"><?php echo e(SITE_NAME); ?></p>
                            <p class="small mb-0"><?php echo e(SITE_FULL_NAME); ?></p>
                        </div>
                    </div>
                    <p class="small mt-3 mb-0"><?php echo e(SITE_TAGLINE); ?></p>
                    <?php if ($available_social): ?>
                        <div class="d-flex gap-2 mt-4">
                            <?php foreach ($available_social as $network => $social): ?>
                                <a class="btn btn-outline-light btn-sm rounded-circle d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;padding:0" href="<?php echo e($social['url']); ?>" target="_blank" rel="noopener" aria-label="BUBAPC on <?php echo e($network); ?>">
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
                    <p class="text-uppercase small fw-bold text-white">Daily Devotional</p>
                    <p class="small mb-3">One short message from God&rsquo;s Word, in your inbox every morning.</p>
                    <form action="<?php echo url('devotional-subscribe'); ?>" method="post" data-devotional-form novalidate>
                        <?php echo csrf_field(); ?>
                        <div class="input-group">
                            <input type="email" name="email" class="form-control" placeholder="Your email address" required maxlength="190" autocomplete="email">
                            <button type="submit" class="btn btn-gold"><span class="devotional-button-text">Subscribe</span></button>
                        </div>
                        <p class="devotional-message small mt-2 mb-0" data-devotional-message role="status" aria-live="polite"></p>
                    </form>
                    <p class="mb-0 mt-3"><a class="link-light fw-semibold" href="<?php echo url('contact'); ?>"><i class="fa-solid fa-envelope-open-text text-warning me-1"></i>Contact Us</a></p>
                </div>
            </div>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-5 py-4 border-top border-secondary">
                <p class="small text-white-50 mb-0">&copy; <?php echo e($current_year); ?> <?php echo e(SITE_FULL_NAME); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
    <button class="btn btn-gold rounded-circle d-inline-flex align-items-center justify-content-center position-fixed bottom-0 end-0 m-3" type="button" id="back-to-top" aria-label="Back to top" hidden>
        <i class="fa-solid fa-arrow-up"></i>
    </button>
    <script src="<?= asset('js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('js/main.js') ?>"></script>
</body>
</html>
