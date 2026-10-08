<?php
/**
 * BUBAPC - The top of every page inside /dashboard/admin
 *
 * A page uses it like this:
 *     require_once __DIR__ . '/../../app/init.php';
 *     $active_module = 'events';
 *     $page_title    = 'Events';
 *     $page_heading  = 'Events';
 *     $page_actions  = '<a class="btn btn-primary" href="...">Add event</a>';
 *     require_once __DIR__ . '/../../app/includes/admin/header.php';
 *     ... page content ...
 *     require_once __DIR__ . '/../../app/includes/admin/footer.php';
 *
 * $page_actions is any HTML printed at the right of the top bar, so each
 * module can put its own "Add" button there.
 */

require_once __DIR__ . '/parts.php';

/* Every page in the admin panel arrives through this one file, so this single
   line is what keeps a church member out of the whole of /dashboard/admin/. A member is
   a real account that can sign in, so "logged in" is not enough to be here -
   require_staff() is. */
require_staff();

$page_title = $page_title ?? 'Admin';
$page_heading = $page_heading ?? $page_title;
$page_actions = $page_actions ?? '';
$active_module = $active_module ?? '';
$admin_user = current_user();

/**
 * The admin modules, the Font Awesome name of each one, and the menu heading it
 * sits under. The side menu prints this list and nothing else, so a new module
 * only has to be added in this one place.
 *
 * The 'group' wording is the reason the menu reads as a menu rather than as one
 * long list of seventeen links. It is grouped by what somebody is *doing* when
 * they look for the screen, in the order a secretary would go looking:
 *
 *   Writing   - the things that are words, put on the site
 *   What's on - the things with a date, a time or a photograph
 *   The church- who is who, and which family or department they belong to
 *   Contact   - the people writing in, and the people we write to
 *   This website - the website itself, who may sign in, and how it is running
 *
 * An item with no group (the dashboard) prints above the first heading. The two
 * screens at the end look after the website rather than its content, which is
 * why they are not under the first four.
 */
function admin_menu()
{
    return [
        ['key' => '',            'label' => 'Dashboard',   'url' => admin_url(),              'icon' => 'fa-gauge-high'],
        ['key' => 'devotionals', 'label' => 'Devotionals', 'url' => admin_url('devotionals'), 'icon' => 'fa-book-bible',   'group' => 'Writing'],
        ['key' => 'sermons',     'label' => 'Sermons',     'url' => admin_url('sermons'),     'icon' => 'fa-music',        'group' => 'Writing'],
        ['key' => 'announcements', 'label' => 'Announcements', 'url' => admin_url('announcements'), 'icon' => 'fa-bullhorn', 'group' => 'Writing'],
        ['key' => 'events',      'label' => 'Events',      'url' => admin_url('events'),      'icon' => 'fa-calendar-day', 'group' => "What's on"],
        ['key' => 'programs',    'label' => 'Weekly Program', 'url' => admin_url('programs'),  'icon' => 'fa-clock',        'group' => "What's on"],
        ['key' => 'gallery',     'label' => 'Gallery',     'url' => admin_url('gallery'),     'icon' => 'fa-images',       'group' => "What's on"],
        ['key' => 'people',      'label' => 'People',      'url' => admin_url('people'),      'icon' => 'fa-users',        'group' => 'The church'],
        ['key' => 'departments', 'label' => 'Departments', 'url' => admin_url('departments'), 'icon' => 'fa-sitemap',      'group' => 'The church'],
        ['key' => 'families',    'label' => 'Families',    'url' => admin_url('families'),    'icon' => 'fa-house-chimney', 'group' => 'The church'],
        ['key' => 'messages',    'label' => 'Messages',    'url' => admin_url('messages'),    'icon' => 'fa-envelope-open-text', 'group' => 'Contact'],
        ['key' => 'newsletter',  'label' => 'Newsletter',  'url' => admin_url('newsletter'),  'icon' => 'fa-paper-plane',  'group' => 'Contact'],
        ['key' => 'users',       'label' => 'Users',       'url' => admin_url('users'),       'icon' => 'fa-user-group',   'group' => 'This website', 'admin_only' => true],
        ['key' => 'settings',    'label' => 'Settings',    'url' => admin_url('settings'),    'icon' => 'fa-sliders',      'group' => 'This website', 'admin_only' => true],
        ['key' => 'logs',        'label' => 'Activity log', 'url' => admin_url('logs'),       'icon' => 'fa-clipboard-list', 'group' => 'This website'],
        ['key' => 'visitors',    'label' => 'Visitors',    'url' => admin_url('visitors'),    'icon' => 'fa-chart-simple', 'group' => 'This website'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($page_title); ?> | <?php echo e(SITE_NAME); ?> Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?php echo asset('images/favicon.svg'); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo asset('css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/all.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">

    <script>
        /* Set the colour theme before the page is painted. */
        (function () {
            try {
                var saved = localStorage.getItem('bubapc-theme');
                if (!saved || saved === 'auto') {
                    saved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', saved);
            } catch (error) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
</head>
<body>

<div class="admin-shell">

    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="d-flex align-items-center justify-content-between gap-2 p-3 border-bottom border-secondary">
            <a class="d-flex align-items-center gap-2 text-decoration-none text-white" href="<?php echo admin_url(); ?>">
                <span class="brand-mark" style="background:rgba(255,255,255,.12);color:#f5a524" aria-hidden="true">
                    <svg viewBox="0 0 48 48" focusable="false">
                        <path d="M24 8c2.4 3.6.6 6-1.2 7.8-1.8 1.8-3.6 3-3.6 6a4.8 4.8 0 0 0 9.6 0c0-1.8-.6-3-1.2-4.2 3.6 1.8 6 4.8 6 9A9.6 9.6 0 0 1 24 36a9.6 9.6 0 0 1-9.6-9.6c0-6.6 4.8-11.4 9.6-18.4z"
                              fill="currentColor"/>
                    </svg>
                </span>
                <span class="lh-sm">
                    <span class="d-block fw-bold" style="font-family:var(--bubapc-font-heading);font-size:1.1rem"><?php echo e(SITE_NAME); ?></span>
                    <span class="d-block text-warning text-uppercase" style="font-size:.66rem;letter-spacing:.16em">Admin</span>
                </span>
            </a>
            <button class="btn btn-sm btn-outline-light d-lg-none" type="button"
                    data-sidebar-close aria-label="Close the menu">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <!-- flex-nowrap is not decoration. Bootstrap's .nav is flex-wrap: wrap,
             and with flex-column a menu taller than the sidebar wraps into a
             second column instead of scrolling. The nav scrolls, not wraps.

             It is admin-nav and not Bootstrap's .overflow-auto because that one
             shows a scrollbar, and the scrollbar is the thing being hidden here.
             See the .admin-nav rule in assets/css/style.css. -->
        <nav class="nav admin-nav flex-column flex-nowrap gap-1 p-2 flex-grow-1" aria-label="Admin menu">
            <?php
            /* A heading is printed whenever the group changes, so a new module
               only has to say which group it is in. The last one printed is kept
               because without it every item in a group repeats its heading. An
               item with no group - the dashboard - prints above the first one,
               which is why the empty string is the starting value rather than
               the first group name. */
            $admin_group = '';
            ?>
            <?php foreach (admin_menu() as $item): ?>
                <?php if (!empty($item['admin_only']) && !is_admin()) {
                    continue;
                } ?>
                <?php $item_group = $item['group'] ?? ''; ?>
                <?php if ($item_group !== $admin_group): ?>
                    <?php if ($item_group !== ''): ?>
                        <p class="admin-nav-heading"><?php echo e($item_group); ?></p>
                    <?php endif; ?>
                    <?php $admin_group = $item_group; ?>
                <?php endif; ?>
                <a class="admin-nav-link<?php echo $active_module === $item['key'] ? ' active' : ''; ?>"
                   href="<?php echo $item['url']; ?>">
                    <i class="fa-solid <?php echo e($item['icon']); ?>" aria-hidden="true"></i>
                    <?php echo e($item['label']); ?>
                </a>
            <?php endforeach; ?>

            <p class="admin-nav-heading">Account</p>
            <a class="admin-nav-link<?php echo $active_module === 'profile' ? ' active' : ''; ?>"
               href="<?php echo admin_url('profile'); ?>">
                <i class="fa-solid fa-user" aria-hidden="true"></i> My profile
            </a>
            <a class="admin-nav-link" href="<?php echo url(''); ?>" target="_blank" rel="noopener">
                <i class="fa-solid fa-globe" aria-hidden="true"></i> View website
            </a>
        </nav>

        <div class="p-3 border-top border-secondary">
            <p class="text-white small fw-semibold mb-1"><?php echo e($admin_user['full_name']); ?></p>
            <p class="text-uppercase mb-2" style="font-size:.7rem;letter-spacing:.1em;color:#f5a524">
                <?php
                /* The role is read from the account rather than worked out from
                   is_admin(), because there are now three kinds of person who
                   can open this panel and the church's own administrator is
                   not one of the two it used to choose between. user_role_label()
                   is the same wording the users screen uses. */
                echo e(user_role_label($admin_user['role'] ?? 'member'));
                ?>
            </p>
            <form method="post" action="<?php echo url('logout'); ?>">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-sm btn-outline-light w-100">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Log out
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-main d-flex flex-column">

        <header class="admin-topbar sticky-top d-flex align-items-center gap-3 p-3 bg-body border-bottom">
            <button class="admin-menu-button d-lg-none" type="button" data-sidebar-open aria-label="Open the menu">
                <i class="fa-solid fa-bars fs-5" aria-hidden="true"></i>
            </button>

            <h1 class="h4 mb-0 flex-grow-1"><?php echo e($page_heading); ?></h1>

            <div class="d-flex align-items-center gap-2">
                <!-- The same button as the one on the public navbar, class for
                     class. It used to carry only "theme-toggle", so it had no
                     size, no border and no circle around it and read as a
                     loose icon floating in the top bar. Keep the two in step. -->
                <button class="icon-button theme-toggle btn btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center"
                        type="button" id="theme-toggle" aria-label="Switch between light and dark mode">
                    <i class="fa-solid fa-moon" aria-hidden="true"></i>
                    <i class="fa-solid fa-sun" aria-hidden="true"></i>
                </button>
                <?php if ($page_actions !== ''): ?>
                    <div class="d-none d-sm-flex gap-2"><?php echo $page_actions; ?></div>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($page_actions !== ''): ?>
            <div class="d-sm-none p-3 border-bottom"><?php echo $page_actions; ?></div>
        <?php endif; ?>

        <?php if (is_maintenance_mode($conn)): ?>
            <div class="alert alert-warning rounded-0 mb-0" role="alert">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <strong>Maintenance mode is ON.</strong>
                Visitors cannot see the website at the moment.
                <a href="<?php echo admin_url('settings'); ?>" class="alert-link ms-1">Turn it off</a>
            </div>
        <?php endif; ?>

        <div class="flex-grow-1 p-3 p-lg-4">
            <?php show_flashes(); ?>
