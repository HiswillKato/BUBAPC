<?php
/**
 * BUBAPC - The page visitors see when maintenance mode is switched on.
 *
 * It is included by app/auth.php, so e(), url() and asset() are ready.
 * The page is self contained, so it keeps working even if the rest of the
 * website is broken.
 */
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Website under maintenance | <?php echo e(SITE_NAME); ?></title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="<?php echo asset('images/favicon.svg'); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo asset('css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/all.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
</head>
<body>
    <main class="min-vh-100 d-flex align-items-center justify-content-center p-4">
        <div class="card border-0 shadow rounded-4 text-center w-100" style="max-width:560px">
            <div class="card-body p-4 p-lg-5">
                <i class="fa-solid fa-screwdriver-wrench fa-2x text-gold mb-3" aria-hidden="true"></i>
                <h1 class="h3">We will be right back</h1>
                <p class="text-body-secondary mb-0"><?php echo e($maintenance_message); ?></p>
                <p class="text-body-secondary small mb-0">You can still reach us
                    <?php if (SITE_PHONE !== ''): ?>
                        on <a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', SITE_PHONE)); ?>"><?php echo e(SITE_PHONE); ?></a>
                    <?php endif; ?>
                    <?php if (SITE_EMAIL !== ''): ?>
                        or on <a href="mailto:<?php echo e(SITE_EMAIL); ?>"><?php echo e(SITE_EMAIL); ?></a>
                    <?php endif; ?>.
                </p>
            </div>
        </div>
    </main>
</body>
</html>
