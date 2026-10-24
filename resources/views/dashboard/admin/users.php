<?php
/**
 * BUBAPC - Admin: the old Users screen, now part of People
 *
 * The sign-in accounts and the people the church lists used to live in two
 * tables and two screens. They are one table and one screen now, so this file
 * is kept only so that a bookmark, a browser history entry or an old e-mail
 * still lands somewhere useful: it sends the visitor on to the People list.
 */

require_once __DIR__ . '/../../../../app/init.php';
if (currentUser()['role'] ?? '' !== 'admin') {
    flash('error', 'Only website administrators can open that page.');
    redirect(adminUrl('people'));
}
requireStaff();

redirect(adminUrl('people'));
