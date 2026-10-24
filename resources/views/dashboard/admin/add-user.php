<?php
/**
 * BUBAPC - Admin: the old add/change-account form, now the people form
 *
 * Accounts and directory people are saved by the one form now, so this file
 * only keeps old links working: it hands the id, when there is one, to the
 * people form and sends the browser there.
 */

require_once __DIR__ . '/../../../../app/init.php';
if (currentuser()['role'] ?? '' !== 'admin') {
    flash('error', 'Only website administrators can open that page.');
    redirect(adminurl('people'));
}
requirestaff();

$person_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

redirect(adminurl('people/form.php' . ($person_id > 0 ? '?id=' . $person_id : '')));
