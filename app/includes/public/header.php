<?php
$page_title = $page_title ?? SITE_NAME;
$page_description = $page_description ?? SITE_TAGLINE . ' - ' . SITE_FULL_NAME;
$body_class = $body_class ?? '';
$canonical = $canonical ?? current_page_path();
record_visit($conn, $canonical);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($page_title); ?> | <?php echo e(SITE_NAME); ?></title>
  <meta name="description" content="<?php echo e($page_description); ?>">
  <meta name="theme-color" content="#1b2559">
  <meta property="og:site_name" content="<?php echo e(SITE_NAME); ?>">
  <meta property="og:title" content="<?php echo e($page_title); ?> | <?php echo e(SITE_NAME); ?>">
  <meta property="og:description" content="<?php echo e($page_description); ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?php echo url($canonical); ?>">
  <link rel="canonical" href="<?php echo url($canonical); ?>">
  <link rel="icon" href="<?php echo asset('images/favicon.svg'); ?>" type="image/svg+xml">
  <?php require __DIR__ . '/../partials/theme-script.php'; ?>
  <?php require __DIR__ . '/../partials/css-links.php'; ?>
</head>
<body class="<?php echo e($body_class); ?>">
<a class="visually-hidden-focusable btn btn-primary position-absolute top-0 start-0 m-2 mt-lg-5 z-3" href="#main-content">Skip to the main content</a>
<?php require_once __DIR__ . '/navbar.php'; ?>
<main id="main-content">
