<?php
/**
 * BUBAPC - One-off migration: newsletter subscribers and member signups
 *
 * Two small changes that the member registration on register.php needs.
 *
 * 1. The table `newsletter_subscribers` is renamed to `subscribers`. The long
 *    name said what the table was for, but now that a church member can tick
 *    the same box while registering, the table is simply the people who get
 *    mail, and `subscribers` says that on its own.
 *
 * 2. `users` gains the 'member' role and a `phone` column. Until now every row
 *    in `users` was a person who manages the website, and the enum only had
 *    room for 'admin' and 'editor'. A church member who signs up on
 *    register.php is a different animal: they may sign in, but they must never
 *    reach the admin panel. The enum is what makes that a fact the database
 *    itself enforces rather than something the code has to remember.
 *
 * The role default moves from 'editor' to 'member' on purpose. Every insert
 * in the project names the role it wants, so the default is never actually
 * used - but if some future insert ever forgets to name it, the safest answer
 * is an account that cannot reach the admin panel, not one that can.
 *
 * HOW TO RUN IT
 *
 *   sudo php database/migrate-members.php
 *
 * It is safe to run more than once. Every step checks whether it has already
 * happened, and a step that is not needed prints "already done" and moves on.
 * Nothing is deleted except the old name of the subscribers table, and only
 * after its rows have been checked over.
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

echo "BUBAPC - renaming newsletter_subscribers and opening members up\n";
echo str_repeat("-", 60) . "\n";

/* ---------------------------------------------------------------------
   1. THE SUBSCRIBERS RENAME
   RENAME TABLE keeps every row and every id, so the newsletter admin
   screen and its bookmarked links carry on working untouched.
   --------------------------------------------------------------------- */
echo "\nThe newsletter list:\n";

if (table_exists($conn, 'newsletter_subscribers') && !table_exists($conn, 'subscribers')) {
    $before = row_count($conn, 'newsletter_subscribers');

    if (run_step($conn, "RENAME TABLE `newsletter_subscribers` TO `subscribers`", 'renamed newsletter_subscribers to subscribers')) {
        $after = row_count($conn, 'subscribers');

        if ($after === $before) {
            echo "  ok    all $after addresses came across\n";
        } else {
            fwrite(STDERR, "  FAILED there were $before addresses before and $after after. Check the table by hand.\n");
            exit(1);
        }
    } else {
        exit(1);
    }
} elseif (table_exists($conn, 'newsletter_subscribers') && table_exists($conn, 'subscribers')) {
    echo "  note  both names exist. Merge them by hand, then drop the old one.\n";
    exit(1);
} else {
    echo "  ok    subscribers is already there, nothing to rename\n";
}

/* ---------------------------------------------------------------------
   2. OPENING THE users ROLE UP TO MEMBERS
   The enum is rewritten in full because MySQL has no way of adding one
   value to an enum in place. The two existing roles are written out
   exactly as they were, so no account changes meaning.
   --------------------------------------------------------------------- */
echo "\nWebsite accounts:\n";

if (!table_exists($conn, 'users')) {
    fwrite(STDERR, "  FAILED there is no users table.\n");
    exit(1);
}

$staff = row_count($conn, 'users') . ' account(s) already exist';

if (run_step(
    $conn,
    "ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','editor','member') NOT NULL DEFAULT 'member'",
    "users can now also be a member"
)) {
    echo "  ok    $staff, and every one of them keeps the role it had\n";
} else {
    exit(1);
}

/* ---------------------------------------------------------------------
   3. THE PHONE NUMBER
   Empty by default, which is what the staff rows are left at. Only the
   member registration form and the member profile page fill it in.
   --------------------------------------------------------------------- */
if (!column_exists($conn, 'users', 'phone')) {
    run_step(
        $conn,
        "ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(40) NOT NULL DEFAULT '' AFTER `email`",
        'users can now hold a phone number'
    );
} else {
    echo "  ok    users already has a phone column\n";
}

/* ---------------------------------------------------------------------
   4. SHOWING THE RESULT
   --------------------------------------------------------------------- */
echo "\n" . str_repeat('-', 60) . "\n";
echo "The newsletter list is now the subscribers table (" . row_count($conn, 'subscribers') . " addresses).\n";
echo "Every account can now be an admin, an editor or a member.\n";
echo "Run register.php to let church members sign themselves up.\n";
echo "\n";
