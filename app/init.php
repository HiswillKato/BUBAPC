<?php
/**
 * BUBAPC - The one file every page includes first
 *
 * It loads the configuration, the database connection, the helper functions
 * and the session. After this single line a page can use $conn, e(), url(),
 * is_logged_in() and every other helper.
 *
 * The loads come first and the two gates come last, and the order matters. The
 * gates below are the only things in the project that catch a failure, and
 * either of them may need e() and url() to write a page: the handler prints an
 * address with url() in it, and the installer path is decided by a constant set
 * before any helper exists. So nothing may run before the helpers are in place.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mail.php';

define('APP_BOOTSTRAPPED', true);

/* =========================================================================
   THE FAILURE HANDLER
   ========================================================================= */

/**
 * What a visitor is shown when something goes wrong that a page did not expect.
 *
 * The alternative is a blank screen with a warning on it for the developer, or
 * a database address and a file path in front of anybody who asks. The handler
 * is set here, once, so that every page is covered without having to remember
 * to call anything: a page only has to return or redirect as normal, and if it
 * never does, this catches it.
 *
 * The detail goes to the error log rather than to the screen. PHP writes the
 * file path, the line and the message there, which is what is needed to find the
 * fault, and it is not reachable from a browser.
 *
 * While SHOW_ERRORS is on - which is the case during development, and the
 * project says to turn it off before going live - the message is shown as well,
 * because a blank page with nothing on it is harder to work with than a page
 * that says what went wrong. The installer is left out of this entirely: it has
 * to keep working when the database does not exist yet, which is exactly the
 * state this handler is about.
 */
set_exception_handler(function ($problem) {
    error_log(sprintf(
        '[bubapc] %s in %s on line %d',
        $problem->getMessage(),
        $problem->getFile(),
        $problem->getLine()
    ));

    if (defined('BUBAPC_INSTALLER')) {
        http_response_code(500);
        exit;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);

    $detail = SHOW_ERRORS
        ? '<pre class="text-start bg-body-tertiary p-3 rounded mt-3" style="white-space:pre-wrap">'
            . e($problem->getMessage() . "\n\n" . $problem->getFile() . ' on line ' . $problem->getLine())
            . '</pre>'
        : '';

    echo '<!DOCTYPE html><html lang="en" data-theme="light"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Something went wrong | BUBAPC</title>'
        . '<link rel="stylesheet" href="' . asset('css/bootstrap.min.css') . '">'
        . '<link rel="stylesheet" href="' . asset('css/all.min.css') . '">'
        . '<link rel="stylesheet" href="' . asset('css/style.css') . '">'
        . '</head><body class="bg-body text-body"><main class="container py-5">'
        . '<div class="row justify-content-center"><div class="col-lg-7">'
        . '<h1 class="h3">Something went wrong</h1>'
        . '<p class="text-body-secondary">This page could not be finished. The problem has'
        . ' been written to the error log. Please try again in a moment, and tell the'
        . ' church if it keeps happening.</p>'
        . $detail
        . '<a class="btn btn-primary mt-2" href="' . url() . '">Back to the home page</a>'
        . '</div></div></main></body></html>';

    exit;
});
