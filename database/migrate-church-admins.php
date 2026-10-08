<?php
/**
 * BUBAPC - One-off migration: church admins who manage the website
 *
 * The church has its own administrators - a secretary, a treasurer, whoever
 * keeps the records - and until now they had no way into the admin panel. The
 * only way to reach it was to be made a website 'admin' or an 'editor', and an
 * editor is the wrong shape: the church wants its administrators keeping the
 * content up to date, and a website admin is somebody who can also delete
 * accounts and change the settings of the site.
 *
 * So the role column grows a fourth value, 'church_admin'. It opens the admin
 * panel, exactly as 'editor' does, and the only thing it cannot do is reach the
 * users and settings screens. For the one church administrator who is also
 * trusted with that, there is a new column, can_manage_users, which raises that
 * one account to the same reach as a website admin.
 *
 * The three existing roles are written out exactly as they were, so no account
 * changes meaning and nobody is given access they did not have this morning.
 *
 * Note the one thing that is easy to get wrong here. `role = 'church_admin'` in
 * the users table and `category = 'church_admin'` in the people table share a
 * word and are NOT the same thing. The users row says what one sign-in may do on
 * this website; the people row says where one person stands in the church and
 * whether they have a page of their own. This script only touches the users
 * table, and it does not read, write or look at the people table at all.
 *
 * HOW TO RUN IT
 *
 *   sudo php database/migrate-church-admins.php
 *
 * It is safe to run more than once. Every step checks whether it has already
 * happened, and a step that is not needed prints "already done" and moves on.
 * Nothing is deleted, and no account is touched beyond the two column changes.
 *
 * This script is not part of the website. Nothing links to it and no page
 * includes it, so it can be deleted once every live site has been moved.
 */

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/migrate-helpers.php';

if (!isset($conn)) {
    fwrite(STDERR, "The database could not be reached, so nothing was changed.\n");
    exit(1);
}

echo "BUBAPC - letting the church's own administrators into the admin panel\n";
echo str_repeat('-', 60) . "\n";

if (!table_exists($conn, 'users')) {
    fwrite(STDERR, "  FAILED there is no users table.\n");
    exit(1);
}

/* ---------------------------------------------------------------------
   1. THE FOURTH ROLE
   The enum is rewritten in full because MySQL has no way of adding one
   value to an enum in place. 'church_admin' goes in after 'editor' so
   that the users screen, which sorts by role, reads admin, church
   admin, editor, member without any extra sorting of its own.
   --------------------------------------------------------------------- */
echo "\nWebsite accounts:\n";

/* Read the enum that is there now rather than assuming one, so a second run can
   say "already done" instead of rebuilding the same definition. 'role' is a
   fixed word, so there is nothing here for a visitor to have typed. */
$role_column = $conn->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch_assoc();
$role_type = $role_column['Type'] ?? '';

if (strpos($role_type, "'church_admin'") !== false) {
    echo "  ok    an account can already be a church admin\n";
} elseif (run_step(
    $conn,
    "ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','editor','church_admin','member') NOT NULL DEFAULT 'member'",
    'an account can now be a church admin'
)) {
    echo "  ok    every existing account keeps the role it had\n";
} else {
    exit(1);
}

/* ---------------------------------------------------------------------
   2. THE EXTRA TICK
   Zero for every row, so nobody is given the users and the settings by
   this script. It has to be ticked by hand, in the admin panel, on the
   one church administrator the church trusts with it.
   --------------------------------------------------------------------- */
if (!column_exists($conn, 'users', 'can_manage_users')) {
    if (run_step(
        $conn,
        "ALTER TABLE `users` ADD COLUMN `can_manage_users` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`",
        'a church admin can be given the users and the settings'
    )) {
        echo "  ok    and it is switched off for everybody, which is the safe way round\n";
    } else {
        exit(1);
    }
} else {
    echo "  ok    the extra tick is already on the table\n";
}

/* ---------------------------------------------------------------------
   3. SHOWING THE RESULT
   --------------------------------------------------------------------- */
$church_admins = $conn->query("SELECT COUNT(*) AS total FROM `users` WHERE role = 'church_admin'")->fetch_assoc();

echo "\n" . str_repeat('-', 60) . "\n";
echo "Every account can now be an admin, a church admin, an editor or a member.\n";
echo "Church admin accounts so far: " . (int) $church_admins['total'] . ".\n";
echo "To give one the users and the settings, edit the account in the admin panel\n";
echo "and tick the one box beside the role.\n";
echo "\n";
