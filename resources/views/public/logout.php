<?php
/**
 * BUBAPC - Signing out
 *
 * A POST only. Signing out is a change, so it is never a plain link that a
 * stray crawler or a prefetch could follow: the button in the admin menu is a
 * form with a CSRF token, and a GET request is simply turned away.
 */

require_once __DIR__ . '/../../../app/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Nothing to sign out of, and nothing was changed.
    redirect('');
}

verifycsrf();

logoutuser();

// A brand new session is started so the "you have signed out" note has
// somewhere to live.
session_start();
flash('success', 'You have been signed out. Thank you.');
redirect('login');
