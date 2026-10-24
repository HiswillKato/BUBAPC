<?php
$page_title = $page_title ?? SITE_NAME;
$page_description = $page_description ?? SITE_TAGLINE . ' - ' . SITE_FULL_NAME;
$body_class = $body_class ?? '';
$canonical = $canonical ?? currentPagePath();
recordVisit($conn, $canonical);
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
  <script>
  (function(){
    try {
      var saved = localStorage.getItem('bubapc-theme');
      var theme = saved === 'light' || saved === 'dark' ? saved : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', theme);
      document.documentElement.setAttribute('data-bs-theme', theme);
    } catch(e){}
  })();
  </script>
  <link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet">
  <link href="<?= asset('css/all.min.css') ?>" rel="stylesheet">
  <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body class="<?php echo e($body_class); ?>">
<a class="visually-hidden-focusable btn btn-primary position-absolute top-0 start-0 m-2 mt-lg-5 z-3" href="#main-content">Skip to the main content</a>
<?php require_once __DIR__ . '/navbar.php'; ?>
<main id="main-content">
