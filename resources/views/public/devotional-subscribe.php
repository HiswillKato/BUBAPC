<?php
/**
 * BUBAPC - Adding somebody to the daily devotional list
 *
 * This is the address the form in the footer posts to, and the same list the
 * daily sender reads (bin/send-devotional.php). Nothing is sent from here: the
 * row is saved and the next morning's devotional goes out to it.
 *
 * The form posts in the background with JavaScript, so it answers in JSON for
 * the footer form and as a normal page (a flash and a redirect) when JavaScript
 * is off. Either way, the same list is written.
 */

require_once __DIR__ . '/../../../app/init.php';

$wants_json = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/**
 * Finish the request, as JSON or as a page.
 */
function devotionalFinish($wants_json, $ok, $message)
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
    devotionalFinish($wants_json, false, 'The devotional form has to be submitted from a page.');
}

verifyCsrf();

$email = postText('email');

if (!isValidEmail($email)) {
    devotionalFinish($wants_json, false, 'Please type a real email address, for example name@example.com.');
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
    devotionalFinish(
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
    devotionalFinish($wants_json, false, 'Something went wrong. Please try again in a moment.');
}

$statement->close();

/* Not the whole address, only enough to recognise it: an activity log that
   printed every subscriber's address would be a second copy of the list in a
   table nobody thinks about as personal. */
logAction($conn, 'devotional_added', 'An address joined the devotional list: '
    . substr($email, 0, 3) . str_repeat('*', max(0, min(6, mb_strlen($email) - 3)))
    . substr($email, -2));

devotionalFinish($wants_json, true, 'Thank you. The daily devotional will find you.');
