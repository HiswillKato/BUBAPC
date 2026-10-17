<?php
/**
 * BUBAPC - The top of every page inside /dashboard/admin
 *
 * It draws the shell the whole panel lives in: the dark menu on the left and
 * the bar across the top. app/includes/admin/footer.php closes what this opens.
 *
 * The menu is Bootstrap's responsive offcanvas (offcanvas-lg): a panel that
 * slides in over the page on a phone and stands still as a column from 992px
 * up. It is the component for exactly this job, so there is no menu
 * JavaScript of our own and no dimming layer of our own - Bootstrap brings
 * both, and remaking either by hand is how the two get out of step.
 *
 * Who is signed in is written at the bottom of the menu, above Log out,
 * because the first thing a shared office computer needs to answer is whose
 * account is open in front of it.
 */
require_once __DIR__ . '/parts.php';
require_staff();
$page_title = $page_title ?? 'Admin';
$page_heading = $page_heading ?? $page_title;
$page_actions = $page_actions ?? '';
$active_module = $active_module ?? '';

$admin = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($page_title); ?> | <?php echo e(SITE_NAME); ?> Admin</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" href="<?php echo asset('images/favicon.svg'); ?>" type="image/svg+xml">
  <?php require __DIR__ . '/../partials/theme-script.php'; ?>
  <?php require __DIR__ . '/../partials/css-links.php'; ?>
</head>
<body>
<div class="admin-shell d-flex">
  <aside class="offcanvas-lg offcanvas-start text-bg-dark border-0" tabindex="-1" id="adminSidebar" aria-label="Admin menu">
    <div class="offcanvas-header pb-0">
      <button class="btn-close btn-close-white ms-auto" type="button" data-bs-dismiss="offcanvas" aria-label="Close the menu"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">

      <?php /* The name of the website, so the menu says which church this
             panel belongs to. It sits in the body rather than in the
             offcanvas header because Bootstrap hides that header from 992px
             up, where the menu stays on screen. */ ?>
      <a class="d-flex align-items-center gap-2 px-2 pt-2 pb-3 text-decoration-none" href="<?php echo admin_url(); ?>">
        <span class="brand-mark bg-white text-brand rounded-3 d-inline-flex align-items-center justify-content-center" aria-hidden="true">
          <svg viewBox="0 0 48 48" focusable="false">
            <path d="M24 8c2.4 3.6.6 6-1.2 7.8-1.8 1.8-3.6 3-3.6 6a4.8 4.8 0 0 0 9.6 0c0-1.8-.6-3-1.2-4.2 3.6 1.8 6 4.8 6 9A9.6 9.6 0 0 1 24 36a9.6 9.6 0 0 1-9.6-9.6c0-6.6 4.8-11.4 9.6-18.4z"
                  fill="currentColor"/>
          </svg>
        </span>
        <span class="d-flex flex-column lh-1">
          <span class="brand-name"><?php echo e(SITE_NAME); ?></span>
          <span class="brand-tagline">Admin panel</span>
        </span>
      </a>

      <nav class="nav nav-pills flex-column gap-1 mb-auto">
        <?php $admin_group = ''; foreach (admin_menu() as $item): ?>
          <?php if (!empty($item['admin_only']) && !is_admin()) continue; ?>
          <?php $item_group = $item['group'] ?? ''; if ($item_group !== $admin_group): if ($item_group !== ''): ?><p class="text-uppercase small text-white-50 mt-3 mb-1 px-2"><?php echo e($item_group); ?></p><?php endif; $admin_group = $item_group; endif; ?>
          <a class="nav-link <?= $active_module === $item['key'] ? 'active bg-white text-dark' : 'text-white-50' ?>" href="<?php echo $item['url']; ?>">
            <i class="fa-solid <?php echo e($item['icon']); ?> me-2"></i><?php echo e($item['label']); ?>
          </a>
        <?php endforeach; ?>
        <p class="text-uppercase small text-white-50 mt-3 mb-1 px-2">Account</p>
        <a class="nav-link <?= $active_module === 'profile' ? 'active bg-white text-dark' : 'text-white-50' ?>" href="<?php echo admin_url('profile'); ?>"><i class="fa-solid fa-user me-2"></i>My profile</a>
        <a class="nav-link text-white-50" href="<?php echo url(''); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-globe me-2"></i>View website</a>
      </nav>

      <div class="border-top border-secondary mt-4 pt-3 d-flex align-items-center gap-2 px-2">
        <?php echo avatar($admin['image'] ?? '', $admin['full_name'], 36); ?>
        <span class="min-width-0">
          <span class="d-block text-white small fw-semibold text-truncate"><?php echo e($admin['full_name']); ?></span>
          <span class="d-block text-white-50 small text-truncate"><?php echo e(user_role_label($admin['role'] ?? 'member')); ?></span>
        </span>
      </div>

      <div class="pt-2">
        <form method="post" action="<?php echo url('logout'); ?>"><?php echo csrf_field(); ?><button type="submit" class="btn btn-outline-light w-100"><i class="fa-solid fa-right-from-bracket me-2"></i>Log out</button></form>
      </div>
    </div>
  </aside>

  <div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php /* One row at every width, carrying the site and the icons and
           nothing else: the menu button on a phone, the site name, then the
           three buttons pushed to the right. The page heading and its one
           action used to sit here too, which is what folded this bar into two
           rows on a phone with the buttons crowding the heading - both now live
           at the top of the content, where there is a whole row to themselves.
           Nothing here wraps, so the bar is the same height on a phone as on a
           desktop, and the site name is what identifies the page when the menu
           is behind its button. */ ?>
    <header class="sticky-top bg-body border-bottom">
      <div class="container-fluid d-flex align-items-center gap-3 px-3 py-2">
        <button class="btn btn-outline-secondary rounded-circle d-lg-none" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar"
                aria-label="Open the menu">
          <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <a class="h4 mb-0 text-body text-decoration-none text-truncate" href="<?php echo admin_url(); ?>">
          <?php echo e(SITE_NAME); ?>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
          <button class="theme-toggle btn btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center"
                  type="button" id="theme-toggle" aria-label="Switch between light and dark mode">
            <i class="fa-solid fa-moon" aria-hidden="true"></i>
            <i class="fa-solid fa-sun" aria-hidden="true"></i>
          </button>
          <?php /* Settings is the one row of the menu an editor may not open -
                 the screen behind it calls require_admin() - so the icon goes
                 to exactly the people that row goes to, and no further. */ ?>
          <?php if (is_admin()): ?>
            <a class="btn btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center"
               href="<?php echo admin_url('settings'); ?>">
              <i class="fa-solid fa-gears" aria-hidden="true"></i><span class="visually-hidden">Settings</span>
            </a>
          <?php endif; ?>
          <a class="btn btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center"
             href="<?php echo admin_url('profile'); ?>">
            <i class="fa-solid fa-user" aria-hidden="true"></i><span class="visually-hidden">My profile</span>
          </a>
        </div>
      </div>
    </header>
    <?php if (is_maintenance_mode($conn)): ?><div class="alert alert-warning rounded-0 mb-0"><i class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></i>Maintenance mode is ON.</div><?php endif; ?>
    <div class="flex-grow-1 p-3 p-lg-4">
      <?php /* The page's heading and its one action, at the top of the content
             where they have a whole row to themselves at every width - which is
             the whole reason the bar above could become a single line. The
             heading comes first so a screen reads in the order it is worked in:
             which screen this is, what may be done here, then the records.
             Every screen sets a title, so the heading is always drawn; the
             action only appears when the screen has one to offer. */ ?>
      <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <h1 class="h4 mb-0 me-auto"><?php echo e($page_heading); ?></h1>
        <?php if ($page_actions !== ''): ?>
          <div class="d-flex flex-wrap gap-2"><?php echo $page_actions; ?></div>
        <?php endif; ?>
      </div>

