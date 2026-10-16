<?php
require_once __DIR__ . '/parts.php';
require_staff();
$page_title = $page_title ?? 'Admin';
$page_heading = $page_heading ?? $page_title;
$page_actions = $page_actions ?? '';
$active_module = $active_module ?? '';
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
    <div class="offcanvas-header border-bottom border-secondary d-lg-none">
      <button class="btn-close btn-close-white" type="button" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
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
        <a class="nav-link text-white-50" href="<?php echo url(''); ?>" target="_blank"><i class="fa-solid fa-globe me-2"></i>View website</a>
      </nav>
      <div class="pt-3">
        <form method="post" action="<?php echo url('logout'); ?>"><?php echo csrf_field(); ?><button type="submit" class="btn btn-outline-light w-100"><i class="fa-solid fa-right-from-bracket me-2"></i>Log out</button></form>
      </div>
    </div>
  </aside>
  <div class="flex-grow-1 d-flex flex-column min-vh-100">
    <header class="navbar navbar-expand-lg bg-body border-bottom sticky-top">
      <div class="container-fluid">
        <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Open menu">
          <i class="fa-solid fa-bars"></i>
        </button>
        <h1 class="h4 mb-0"><?php echo e($page_heading); ?></h1>
        <div class="ms-auto d-flex align-items-center gap-2">
          <button class="theme-toggle btn btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center"
                  type="button" id="theme-toggle" aria-label="Switch between light and dark mode">
            <i class="fa-solid fa-moon" aria-hidden="true"></i>
            <i class="fa-solid fa-sun" aria-hidden="true"></i>
          </button>
          <?php if ($page_actions !== ''): ?><div class="d-none d-sm-flex gap-2"><?php echo $page_actions; ?></div><?php endif; ?>
        </div>
      </div>
    </header>
    <?php if ($page_actions !== ''): ?><div class="d-sm-none p-3 border-bottom"><?php echo $page_actions; ?></div><?php endif; ?>
    <?php if (is_maintenance_mode($conn)): ?><div class="alert alert-warning rounded-0 mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>Maintenance mode is ON.</div><?php endif; ?>
    <div class="flex-grow-1 p-3 p-lg-4">
