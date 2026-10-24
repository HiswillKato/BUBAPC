<?php
/**
 * BUBAPC - Admin: the old leaders list now lives under People
 *
 * The leaders screen became the people screen when the students and the alumni
 * were added, so anyone who has this page bookmarked, or who followed a link
 * from an old email, is sent to the new place rather than shown a 404.
 *
 * This file has no list of its own. It is only a signpost.
 */

require_once __DIR__ . '/../../../../app/init.php';
requireStaff();

redirect(adminUrl('people'));
