<?php
/**
 * BUBAPC - Adding somebody to the newsletter list
 *
 * The form in the footer posts here. assets/js/main.js sends the same form
 * with fetch() and expects a small JSON answer, so the page has two ways to
 * finish: JSON for the quick form, a redirect and a thank-you message for
 * somebody whose browser has no JavaScript.
 *
 * Nothing is emailed from here. The address simply waits in the list for
 * whoever sends the next devotional, and an address that is already on the
 * list is never added twice. When the list is sent out, the one helper to use
 * is mail_newsletter() in app/mail.php, so the wording is the same every time.
 */

require_once __DIR__ . '/../app/init.php';

$wants_json = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/**
 * Finish the request, as JSON or as a page.
 */
function newsletter_finish($wants_json, $ok, $message)
{
    if ($wants_json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message]);
        exit;
    }

    flash($ok ? 'success' : 'error', $message);
    redirect('');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    newsletter_finish($wants_json, false, 'The newsletter form has to be submitted from a page.');
}

verify_csrf();

$email = post_text('email');

if (!is_valid_email($email)) {
    newsletter_finish($wants_json, false, 'Please type a real email address, for example name@example.com.');
}

$email = mb_substr($email, 0, 190);

/* Already there? Say thank you anyway, but do not add a second row. This is
   checked before the insert as well as by the unique key, so two people
   pressing the button at the same moment cannot slip past it. */
$statement = $conn->prepare("SELECT id, status FROM subscribers WHERE email = ? LIMIT 1");
$statement->bind_param('s', $email);
$statement->execute();
$result = $statement->get_result();
$existing = $result->fetch_assoc();
$statement->close();

if ($existing) {
    newsletter_finish(
        $wants_json,
        true,
        (int) $existing['status'] === 1
            ? 'Thank you - that address is already on our list.'
            : 'That address was removed from our list before. Please email us and we will put it back.'
    );
}

$statement = $conn->prepare("INSERT INTO subscribers (email, status) VALUES (?, 1)");
$statement->bind_param('s', $email);

if (!$statement->execute()) {
    $statement->close();
    newsletter_finish($wants_json, false, 'Something went wrong. Please try again in a moment.');
}

$statement->close();

/* Not the whole address, only enough to recognise it: an activity log that
   printed every subscriber's address would be a second copy of the list in a
   table nobody thinks about as personal. */
log_action($conn, 'newsletter_added', 'An address joined the list: '
    . substr($email, 0, 3) . str_repeat('*', max(0, min(6, mb_strlen($email) - 3)))
    . substr($email, -2));

newsletter_finish($wants_json, true, 'Thank you. You are now on the list.');
