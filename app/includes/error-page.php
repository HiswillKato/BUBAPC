<?php
/**
 * BUBAPC - The shared "something went wrong" page.
 *
 * A page uses it like this:
 *     http_response_code(404);
 *     $error_code = 404;
 *     $error_title = 'Page not found';
 *     $error_message = '...';
 *     require __DIR__ . '/../app/includes/error-page.php';
 *
 * It draws its own complete HTML page, so it also works when the database is
 * not available. Only plain CSS and the e() helper are needed.
 */

$error_code = $error_code ?? 500;
$error_title = $error_title ?? 'Something went wrong';
$error_message = $error_message ?? '';

$error_icons = [
    403 => 'fa-lock',
    404 => 'fa-compass',
    500 => 'fa-triangle-exclamation',
    503 => 'fa-screwdriver-wrench',
];
$error_icon = $error_icons[$error_code] ?? 'fa-triangle-exclamation';

$error_defaults = [
    403 => 'You do not have permission to open this page. If you think this is wrong, please log in.',
    404 => 'The page you are looking for is not here. It may have been moved, renamed or removed.',
    503 => 'The website is not available at the moment. Please come back shortly.',
];
if ($error_message === '' && isset($error_defaults[$error_code])) {
    $error_message = $error_defaults[$error_code];
}
if ($error_message === '') {
    $error_message = 'An unexpected problem happened. Please try again in a moment.';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($error_title); ?> | BUBAPC</title>
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
                <p class="error-code mb-0"><?php echo e($error_code); ?></p>
                <i class="fa-solid <?php echo e($error_icon); ?> fa-2x text-gold mb-3" aria-hidden="true"></i>
                <h1 class="h3"><?php echo e($error_title); ?></h1>
                <p class="text-body-secondary mb-4"><?php echo e($error_message); ?></p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a class="btn btn-primary" href="<?php echo url(''); ?>">
                        <i class="fa-solid fa-house me-1" aria-hidden="true"></i> Go to the homepage
                    </a>
                    <a class="btn btn-outline-primary" href="<?php echo url('contact'); ?>">Contact us</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
