<?php
/**
 * BUBAPC - The top of every public page
 *
 * A page in pages/ uses it like this:
 *     require_once __DIR__ . '/../app/init.php';
 *     $page_title = 'About';
 *     require_once __DIR__ . '/../app/includes/public/header.php';
 *     ... page content ...
 *     require_once __DIR__ . '/../app/includes/public/footer.php';
 *
 * $page_title, $page_description, $body_class and $canonical are optional.
 * The page prints its own <section class="page-header"> title band, so
 * $page_title is used for the browser tab, the search engines and the menu.
 */

$page_title = $page_title ?? SITE_NAME;
$page_description = $page_description ?? SITE_TAGLINE . ' - ' . SITE_FULL_NAME;
$body_class = $body_class ?? '';
$canonical = $canonical ?? current_page_path();

/* Note that a browser reached this page. It is here, at the one place every
   public page starts, rather than in each page, so a new page is counted
   without anybody remembering to count it. The admin panel has its own header
   and is deliberately not counted, because the church is not a visitor to
   itself. Every failure inside is swallowed, so this can never stop a page
   being shown. */
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
    <link rel="stylesheet" href="<?php echo asset('css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/all.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">

    <script>
        /* Set the colour theme before the page is painted, so the website
           never flashes white for someone who chose dark mode. */
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
<body class="<?php echo e($body_class); ?>">

<a class="visually-hidden-focusable btn btn-primary position-absolute top-0 start-0 m-2 mt-lg-5 z-3"
   href="#main-content">Skip to the main content</a>

<?php require_once __DIR__ . '/navbar.php'; ?>

<main id="main-content">
