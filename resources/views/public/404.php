<?php
/**
 * BUBAPC - the 404 page
 *
 * Apache points ErrorDocument 404 here (see the root .htaccess), so this file
 * runs whenever a visitor asks for an address that does not exist. It only
 * decides the wording and hands over to the shared error card.
 *
 * The status code has to be set as well as the page, otherwise a search engine
 * is told the missing page is fine and keeps it in its index.
 */

require_once __DIR__ . '/../../../app/init.php';

http_response_code(404);

$error_code = 404;
$error_title = 'Page not found';
$error_message = 'We could not find that page. It may have been moved, renamed or removed. '
    . 'Try the menu at the top, or search for what you were looking for.';

require __DIR__ . '/../partials/error-page.php';
