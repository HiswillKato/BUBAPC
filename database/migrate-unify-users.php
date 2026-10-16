<?php
/**
 * BUBAPC - folding the users table into people
 *
 * There used to be two tables about people. `people` held everyone the church
 * lists on the website, and `users` held everyone who could sign in. The same
 * person often had a row in both, joined by users.person_id, and the two had to
 * be kept in step by hand.
 *
 * This script makes `people` the one table. A person is now a single row that
 * says where the church lists them AND what they may do on the website:
 *
 *   - a row with no password is a directory page and nothing else, exactly as
 *     the old people rows were;
 *   - a row with a password is also an account, so the old users columns -
 *     username, password, role, standing, active, login - move onto it.
 *
 * WHY THE `users` COLUMNS ARE COPIED, NOT JOINED
 *
 * A join has to be remembered by every query that reads an account, and one
 * query that forgets it is a bug that only shows up in production. One table
 * cannot be half-joined. The cost is that a directory-only person and an
 * account may share an email address, which is why username - the sign-in
 * name - is NULL when there is no sign-in and unique only among the rows that
 * have one. Two NULLs do not clash in MySQL, so a hundred directory people may
 * share the empty email without a word.
 *
 * HOW TO RUN IT
 *
 *   sudo php database/migrate-unify-users.php
 *
 * It is safe to run more than once. Every step checks whether it has already
 * happened, and a step that is not needed prints "already done" and moves on.
 * The only things ever removed are the old `users` table, once every row in it
 * has a home in `people`, and the one-time `tokens` rows, which the table's own
 * comment already says are safe to empty at any time.
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

echo "BUBAPC - folding the users table into people\n";
echo str_repeat("-", 60) . "\n";

/**
 * Is this value one of the choices in an enum column? SHOW COLUMNS gives the
 * whole definition back, for example "enum('a','b')", so the value is looked
 * for inside it. Used only to decide whether an ALTER is still needed.
 */
function enum_has($conn, $table, $column, $value)
{
    $safe = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$safe'");
    $row = $result->fetch_assoc();
    return $row !== null && strpos($row['Type'], "'$value'") !== false;
}

/**
 * Does this index exist on the table?
 */
function index_exists($conn, $table, $index)
{
    $safe = $conn->real_escape_string($index);
    $result = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$safe'");
    return $result->num_rows > 0;
}

/**
 * A slug for a new person row, made from the name and kept unique against the
 * slugs already in the table. People are listed by /people/<slug>, so two rows
 * may never share one.
 */
function unique_slug($conn, $name)
{
    $base = strtolower(trim($name));
    $base = preg_replace('/[^a-z0-9]+/', '-', $base);
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'person';
    }

    $slug = $base;
    $n = 2;
    $check = $conn->prepare("SELECT 1 FROM `people` WHERE `slug` = ?");
    while (true) {
        $check->bind_param('s', $slug);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            break;
        }
        $slug = $base . '-' . $n;
        $n++;
    }
    return $slug;
}

/**
 * Is there a person row with this id? A user whose person_id points at a row
 * that is no longer there is treated the same as one that never pointed at
 * anything, so its account becomes a row of its own instead of being lost.
 */
function person_exists($conn, $id)
{
    $check = $conn->prepare("SELECT 1 FROM `people` WHERE `id` = ?");
    $check->bind_param('i', $id);
    $check->execute();
    return $check->get_result()->num_rows > 0;
}

/* ---------------------------------------------------------------------
   1. THE SIGN-IN COLUMNS
   Every column the old users table had, added empty. A person who is
   only on the directory page is left with all of them NULL or at their
   default, which is what "no sign-in" means.
   --------------------------------------------------------------------- */
echo "\nThe sign-in columns on people:\n";

$columns = [
    "username" => "ALTER TABLE `people` ADD COLUMN `username` VARCHAR(190) DEFAULT NULL AFTER `email`",
    "password" => "ALTER TABLE `people` ADD COLUMN `password` VARCHAR(255) DEFAULT NULL AFTER `username`",
    "role"     => "ALTER TABLE `people` ADD COLUMN `role` ENUM('admin','editor','pastor','leader','member') DEFAULT NULL AFTER `password`",
    "standing" => "ALTER TABLE `people` ADD COLUMN `standing` ENUM('member','alumnus','leader') NOT NULL DEFAULT 'member' AFTER `role`",
    "active"   => "ALTER TABLE `people` ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `standing`",
    "login"    => "ALTER TABLE `people` ADD COLUMN `login` DATETIME DEFAULT NULL AFTER `active`",
];

foreach ($columns as $name => $sql) {
    if (!column_exists($conn, 'people', $name)) {
        if (!run_step($conn, $sql, "people can now hold `$name`")) {
            exit(1);
        }
    } else {
        echo "  ok    people already has `$name`\n";
    }
}

/* ---------------------------------------------------------------------
   2. THE TWO NEW GROUPS
   'member' is somebody who registered themselves on the website and has
   no directory page; 'staff' is an account the office made for the site.
   They are put at the end of the enum on purpose, so the public filters
   and leader_categories() - which take the first five - never offer
   them, and no existing row changes meaning.
   --------------------------------------------------------------------- */
echo "\nThe groups a person can be listed under:\n";

if (!enum_has($conn, 'people', 'category', 'member')) {
    run_step(
        $conn,
        "ALTER TABLE `people` MODIFY COLUMN `category` ENUM('pastor','church_admin','elder','department_leader','family_leader','student','alumnus','member','staff') NOT NULL DEFAULT 'student'",
        "people can now also be a member or staff"
    );
} else {
    echo "  ok    people already offers member and staff\n";
}

/* ---------------------------------------------------------------------
   3. THE SIGN-IN NAME IS UNIQUE, BUT ONLY WHEN THERE IS ONE
   Two directory people with an empty email are fine, so the key has to
   allow many NULLs and it does. A sign-in name is what a visitor types
   to get in, so no two accounts may share one.
   --------------------------------------------------------------------- */
echo "\nThe sign-in name:\n";

if (!index_exists($conn, 'people', 'username')) {
    run_step(
        $conn,
        "ALTER TABLE `people` ADD UNIQUE KEY `username` (`username`)",
        "people's sign-in name is now unique when it is given"
    );
} else {
    echo "  ok    the username key is already there\n";
}

/* ---------------------------------------------------------------------
   4. MOVING EVERY ACCOUNT ACROSS
   A user with a person behind them has its account columns copied onto
   that person, so the two become one row. A user with nobody behind
   them becomes a new person row of its own: a 'member' if it signed
   itself up, a 'staff' account otherwise. Either way it is kept off the
   website with status 0, because it belongs to no directory page.

   The old user id and the new person id are remembered as we go, so the
   activity log can be pointed at the same people it was pointed at
   before.
   --------------------------------------------------------------------- */
echo "\nThe accounts:\n";

if (!table_exists($conn, 'users')) {
    echo "  ok    there is no users table, so there is nothing to move\n";
} else {
    $before = row_count($conn, 'users');
    $users = $conn->query("SELECT * FROM `users` ORDER BY `id`");
    $map = [];
    $moved = 0;
    $made = 0;

    while ($user = $users->fetch_assoc()) {
        if ((int) $user['person_id'] > 0 && person_exists($conn, (int) $user['person_id'])) {
            /* The account joins the person that was already there. The email
               and phone are only filled in when the directory row had none,
               because the directory is the newer, hand-kept copy. */
            $id = (int) $user['person_id'];
            $update = "UPDATE `people` SET
                           `username` = ?, `password` = ?, `role` = ?, `standing` = ?,
                           `active` = ?, `login` = ?,
                           `email` = IF(`email` = '', ?, `email`),
                           `phone` = IF(`phone` = '', ?, `phone`)
                       WHERE `id` = ?";
            $stmt = $conn->prepare($update);
            $stmt->bind_param(
                'ssssisssi',
                $user['username'], $user['password'], $user['role'], $user['standing'],
                $user['status'], $user['login'], $user['email'], $user['phone'], $id
            );
            if (!$stmt->execute()) {
                fwrite(STDERR, "  FAILED could not copy the account for " . $user['full_name'] . ": " . $conn->error . "\n");
                exit(1);
            }
            $moved++;
        } else {
            /* Nobody behind the account, so it becomes a person of its own.
               It stays off the website with status 0: it belongs to no
               directory page, and a member or a website admin is not a
               card the church wants on the leadership page. */
            $category = $user['role'] === 'member' ? 'member' : 'staff';
            $slug = unique_slug($conn, $user['full_name']);
            $insert = "INSERT INTO `people`
                           (`name`, `slug`, `category`, `email`, `phone`, `username`, `password`,
                            `role`, `standing`, `active`, `login`, `status`, `created`)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)";
            $stmt = $conn->prepare($insert);
            $active = (int) $user['status'];
            $stmt->bind_param(
                'sssssssssiss',
                $user['full_name'], $slug, $category,
                $user['email'], $user['phone'], $user['username'], $user['password'],
                $user['role'], $user['standing'], $active,
                $user['login'], $user['created']
            );
            if (!$stmt->execute()) {
                fwrite(STDERR, "  FAILED could not move the account for " . $user['full_name'] . ": " . $conn->error . "\n");
                exit(1);
            }
            $id = $conn->insert_id;
            $made++;
        }

        $map[(int) $user['id']] = $id;
    }

    echo "  ok    $before account(s) moved: $moved onto an existing person, $made into a new one\n";

    /* The log points at an account by id. Point every row at the person
       the account became, so the activity screen still knows who did what. */
    if (table_exists($conn, 'logs')) {
        $update = $conn->prepare("UPDATE `logs` SET `actor` = ? WHERE `actor` = ?");
        foreach ($map as $old => $new) {
            if ($old !== $new) {
                $update->bind_param('ii', $new, $old);
                $update->execute();
            }
        }
        echo "  ok    the activity log now points at people\n";
    }

    /* A reset token is a one-time link; the table's own comment says it is
       safe to empty at any time, so it is cheaper and safer to drop the lot
       than to remap rows that should be expiring anyway. */
    if (table_exists($conn, 'tokens')) {
        $conn->query("DELETE FROM `tokens`");
        echo "  ok    one-time links cleared; any reset in flight must be asked for again\n";
    }

    if (run_step($conn, "DROP TABLE `users`", "dropped the old users table")) {
        echo "  ok    all $before account(s) live in people now\n";
    } else {
        echo "  note  people is ready but users could not be dropped. Drop it by hand when you are sure.\n";
    }
}

echo "\n" . str_repeat("-", 60) . "\n";
echo "people is now the one table: directory pages and sign-ins together.\n";
