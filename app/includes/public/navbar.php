<?php
/**
 * BUBAPC - The menu at the top of every public page.
 * app/includes/public/header.php includes it, so a page never includes this file directly.
 *
 * The bar is grouped by what a visitor is trying to find, in the order somebody
 * arriving with no idea what this church is would ask for it:
 *
 *   Home · About      - what this place is
 *   Our Church        - who is in it: leadership, departments, families, people
 *   Devotional        - today's message, given a link of its own because it is
 *                       the one thing here worth a visitor's time
 *   Media             - what it publishes and keeps: archive, gallery, notices
 *   Attend            - coming to a service: the program, events, contact
 *   Login · Register  - a visitor who already has an account signs in with the
 *                       plain Login link; Register is the one thing the bar
 *                       asks a newcomer to DO, so it is a filled button at the
 *                       end rather than another link
 *
 * Three dropdowns rather than four: Program and Contact used to be separate
 * items, which made the bar longer and left Contact reading as a section when
 * it is really the end of a first visit.
 *
 * Sermons is in none of these menus. It stays in the footer, so the page is
 * still reachable without taking a space in the bar away from the devotional.
 *
 * It is written with Bootstrap classes only:
 * navbar, navbar-expand-lg, nav, dropdown, input-group, form-control and btn.
 * The two custom classes it uses are brand-mark and icon-button, which only
 * fix a size Bootstrap has no utility for; every colour comes from a bg-* or
 * text-* class. The one button class is btn-gold, which is this site's accent
 * for the main action on a page.
 *
 * The search box is not shown in the bar itself. The magnifying glass opens
 * the panel underneath with Bootstrap's own collapse, so the bar stays calm at
 * every width and there is nothing extra for the JavaScript to do.
 */

$search_term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$available_social = social_links();

/* The top strip is only for accounts a visitor can follow, so YouTube is kept
   to the footer instead. */
unset($available_social['YouTube']);
?>
<?php if ($available_social): ?>
<div class="topbar bg-brand-deep text-white d-none d-md-block">
    <div class="container d-flex justify-content-end align-items-center gap-3 py-1 small">
        <?php foreach ($available_social as $network => $social): ?>
            <a class="text-reset" href="<?php echo e($social['url']); ?>"
               target="_blank" rel="noopener" aria-label="BUBAPC on <?php echo e($network); ?>">
                <i class="<?php echo e($social['icon']); ?>" aria-hidden="true"></i>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<header class="site-header sticky-top bg-body border-bottom">
    <nav class="navbar navbar-expand-lg py-2">
        <div class="container">

            <a class="navbar-brand d-flex align-items-center gap-2 me-lg-4" href="<?php echo url(''); ?>">
                <span class="brand-mark bg-brand-soft text-brand rounded-3 d-inline-flex align-items-center justify-content-center" aria-hidden="true">
                    <svg viewBox="0 0 48 48" focusable="false">
                        <path d="M24 8c2.4 3.6.6 6-1.2 7.8-1.8 1.8-3.6 3-3.6 6a4.8 4.8 0 0 0 9.6 0c0-1.8-.6-3-1.2-4.2 3.6 1.8 6 4.8 6 9A9.6 9.6 0 0 1 24 36a9.6 9.6 0 0 1-9.6-9.6c0-6.6 4.8-11.4 9.6-18.4z"
                              fill="currentColor"/>
                    </svg>
                </span>
                <span class="d-flex flex-column lh-1">
                    <span class="brand-name"><?php echo e(SITE_NAME); ?></span>
                    <?php /* The tagline is desktop dressing. On a phone the bar
                           already carries the flame mark and the church name,
                           and a third line of small caps pushed the toggles
                           into the next row. d-lg-block matches the navbar's
                           own expand-lg breakpoint, so it reappears the moment
                           the menu stops collapsing. */ ?>
                    <span class="brand-tagline d-none d-lg-block"><?php echo e(SITE_TAGLINE); ?></span>
                </span>
            </a>

            <?php /* This group is the last thing on the right of the bar, so it
                   needs a left margin of its own: the gap-2 below only spaces
                   the buttons against each other, because CSS gap never puts a
                   gap in front of the first child. Without ms-lg-3 the Register
                   button, which is filled, sat flush against the outline search
                   circle with nothing between them. */ ?>
            <div class="d-flex align-items-center gap-2 order-lg-3 ms-auto ms-lg-3">
                <button class="btn btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center"
                        type="button" data-bs-toggle="collapse" data-bs-target="#bubapc-search"
                        aria-controls="bubapc-search" aria-expanded="false" aria-label="Search the website">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                </button>

                <button class="theme-toggle btn btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center"
                        type="button" id="theme-toggle" aria-label="Switch between light and dark mode">
                    <i class="fa-solid fa-moon" aria-hidden="true"></i>
                    <i class="fa-solid fa-sun" aria-hidden="true"></i>
                </button>

                <button class="navbar-toggler border-0 shadow-none" type="button"
                        data-bs-toggle="collapse" data-bs-target="#bubapc-menu"
                        aria-controls="bubapc-menu" aria-expanded="false" aria-label="Open the menu">
                    <i class="fa-solid fa-bars fs-3" aria-hidden="true"></i>
                </button>
            </div>

            <div class="collapse navbar-collapse order-lg-2" id="bubapc-menu">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 py-3 py-lg-0">
                    <li class="nav-item">
                        <a class="nav-link fw-semibold<?php echo nav_active('home') ? ' active' : ''; ?>"
                           href="<?php echo url(''); ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold<?php echo nav_active('about') ? ' active' : ''; ?>"
                           href="<?php echo url('about'); ?>">About</a>
                    </li>

                    <?php /* WHO WE ARE. Everything here answers "who is this
                           church made of", so the people and the places they
                           belong in sit together rather than being spread
                           across the bar. */ ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fw-semibold<?php echo nav_active('leadership') || nav_active('departments') || nav_active('families') || nav_active('people') ? ' active' : ''; ?>"
                           href="<?php echo url('leadership'); ?>" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">Our Church</a>
                        <ul class="dropdown-menu rounded-4 shadow border-0 p-2">
                            <li><a class="dropdown-item rounded-3" href="<?php echo url('people?group=pastors'); ?>">Pastors</a></li>
                            <li><a class="dropdown-item rounded-3" href="<?php echo url('leadership'); ?>">Leadership</a></li>
                            <li><a class="dropdown-item rounded-3" href="<?php echo url('departments'); ?>">Departments</a></li>
                            <li><a class="dropdown-item rounded-3" href="<?php echo url('families'); ?>">Families</a></li>
                            <li><a class="dropdown-item rounded-3" href="<?php echo url('people?group=alumni'); ?>">Alumni</a></li>
                        </ul>
                    </li>

                    <?php /* THE DAILY MESSAGE. This one is not in a dropdown on purpose. The
                           devotional is the thing this church most wants a visitor
                           to read, and burying it one click inside Media hid it
                           behind a section name that does not suggest it. It gets
                           a link of its own in the bar, sitting where a person
                           would look for it, and the archive stays below it in
                           Media with the other things that are written down and
                           kept. */ ?>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold<?php echo nav_active('devotional') ? ' active' : ''; ?>"
                           href="<?php echo url('devotional'); ?>">Devotional</a>
                    </li>

<?php /* WHAT WE PUBLISH. Everything here is a thing that was
                            written down and kept, so the pictures and notices follow it. */ ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fw-semibold<?php echo nav_active('devotionals') || nav_active('gallery') || nav_active('announcements') ? ' active' : ''; ?>"
                           href="<?php echo url('gallery'); ?>" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">Media</a>
                        <ul class="dropdown-menu rounded-4 shadow border-0 p-2">
                            <li><hr class="dropdown-divider"></li>
                             <li><a class="dropdown-item rounded-3" href="<?php echo url('gallery'); ?>">Gallery</a></li>
                             <li><a class="dropdown-item rounded-3" href="<?php echo url('announcements'); ?>">Announcements</a></li>
                        </ul>
                    </li>

                    <?php /* COMING TO A SERVICE. What is on, and how to reach us,
                           are the same question to somebody who has never been
                           here, so they share one group. Contact used to sit
                           between About and the dropdowns, where it read as an
                           ordinary section rather than the end of a journey. */ ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fw-semibold<?php echo nav_active('programs') || nav_active('events') || nav_active('contact') ? ' active' : ''; ?>"
                           href="<?php echo url('programs'); ?>" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">Programs</a>
                        <ul class="dropdown-menu rounded-4 shadow border-0 p-2">
                            <li><a class="dropdown-item rounded-3" href="<?php echo url('programs'); ?>">Weekly Program</a></li>
                            <li><a class="dropdown-item rounded-3" href="<?php echo url('events'); ?>">Special Events</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item rounded-3" href="<?php echo url('contact'); ?>">Contact Us</a></li>
                        </ul>
                    </li>

                    <?php /* A visitor who has not signed in gets two things here:
                           a plain Login link for somebody who already has an
                           account, and the Register button for somebody the church
                           has never heard from, which is the one way to reach the
                           registration form from any page. Register is a filled
                           button rather than another link because it is the one
                           thing in the bar a visitor is being asked to DO, and
                           putting it last gives the eye an end to stop at. Both
                           disappear once somebody is signed in. The Login link's
                           address, word and icon come from account_link(), so the
                           bar and the account menu cannot drift apart. */ ?>
                    <?php if (!is_logged_in()): ?>
                        <?php $account = account_link(); ?>
                        <li class="nav-item">
                            <a class="nav-link fw-semibold<?php echo nav_active('login') ? ' active' : ''; ?>"
                               href="<?php echo e($account['url']); ?>"
                               <?php echo nav_active('login') ? 'aria-current="page"' : ''; ?>>
                                <i class="fa-solid <?php echo e($account['icon']); ?> me-1" aria-hidden="true"></i><?php echo e($account['label']); ?></a>
                        </li>
                        <li class="nav-item">
                            <?php /* The button carries aria-current rather than an
                                   `active` class: Bootstrap styles .nav-link.active
                                   but has no .btn.active, so the class would say
                                   nothing to a sighted visitor while the attribute
                                   still tells a screen reader this is the page you
                                   are on. */ ?>
                            <a class="btn btn-gold ms-lg-2"
                               href="<?php echo url('register'); ?>"
                               <?php echo nav_active('register') ? 'aria-current="page"' : ''; ?>>Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="collapse border-top" id="bubapc-search">
        <div class="container py-3">
            <form class="d-flex" action="<?php echo url('search'); ?>" method="get" role="search">
                <label class="visually-hidden" for="nav-search">Search the website</label>
                <div class="input-group">
                    <input type="search" id="nav-search" name="q"
                           class="form-control ps-3 bg-body-tertiary"
                           placeholder="Search sermons, devotionals, events&hellip;"
                           value="<?php echo e($search_term); ?>" maxlength="100" autocomplete="off">
                    <button class="btn btn-primary px-3" type="submit" aria-label="Search">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</header>
