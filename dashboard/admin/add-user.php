<?php
/**
 * BUBAPC - Admin: the old add/change-account form, now the people form
 *
 * Accounts and directory people are saved by the one form now, so this file
 * only keeps old links working: it hands the id, when there is one, to the
 * people form and sends the browser there.
 */

require_once __DIR__ . '/../../app/init.php';
require_admin();

$person_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

redirect(admin_url('people/form.php' . ($person_id > 0 ? '?id=' . $person_id : '')));
