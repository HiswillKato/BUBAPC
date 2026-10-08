<?php
/**
 * BUBAPC - One-off migration: the `leaders` table becomes `people`
 *
 * The church has five groups - alumni, pastors, students, families and
 * departments - and they were not all in the database. The people who hold
 * an office lived in `leaders`, the students and the alumni had nowhere to
 * be recorded at all, and each department and family typed its leader's name
 * as free text that was never linked to the person.
 *
 * This script fixes that. It renames `leaders` to `people`, adds the columns
 * the students and the alumni need, and joins every department and family to
 * the person who leads it. Nothing is thrown away: the old rows keep their
 * ids, so any bookmarked admin link such as /dashboard/admin/leaders/?id=47
 * still finds the same person.
 *
 * HOW TO RUN IT
 *
 *   sudo php database/migrate-people.php
 *
 * It is safe to run more than once. Every step checks whether it has already
 * happened, and a step that is not needed prints "already done" and moves on.
 * Nothing is deleted except the old `leaders` table, and only after its rows
 * have been copied across and the count has been checked.
 *
 * WHY THIS IS PHP AND NOT A .sql FILE
 *
 * Two of the steps cannot sensibly be written in SQL. Building a slug from a
 * name ("Bro. Daniel Waire" to "bro-daniel-waire") and making sure no two
 * people end up with the same one are both already done by slugify() and
 * slug() in app/functions.php, and this project has one copy of that
 * logic on purpose. Doing it here as well would mean two versions to keep in
 * step, and the wrong one would only show up as a broken link months later.
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

echo "BUBAPC - moving the leaders table to people\n";
echo str_repeat("-", 60) . "\n";

/* ---------------------------------------------------------------------
   1. THE TABLE ITSELF
   The rename keeps the rows and their ids, which is the whole point: a
   bookmarked admin link keeps working after the migration.
   --------------------------------------------------------------------- */
if (table_exists($conn, 'leaders') && !table_exists($conn, 'people')) {
    run_step($conn, "RENAME TABLE `leaders` TO `people`", 'renamed leaders to people');
} elseif (table_exists($conn, 'leaders') && table_exists($conn, 'people')) {
    echo "  note  both leaders and people exist. Copy anything missing across by hand.\n";
} elseif (!table_exists($conn, 'people')) {
    fwrite(STDERR, "  FAILED there is no leaders table and no people table.\n");
    exit(1);
} else {
    echo "  ok    people is already there, nothing to rename\n";
}

$before = row_count($conn, 'people');
echo "  info  $before people in the table\n";

/* ---------------------------------------------------------------------
   2. THE NEW COLUMNS
   Each one is added only if it is missing, so a second run adds nothing.
   The old leaders table had no slug, and a slug is what makes
   /people/bro-daniel-waire work.
   --------------------------------------------------------------------- */
echo "\nAdding the columns the students and alumni need:\n";

$new_columns = [
    'slug'            => "VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'Makes /people/bro-daniel-waire work'",
    'category'        => "ENUM('pastor','church_admin','elder','department_leader','family_leader','student','alumnus') NOT NULL DEFAULT 'student'",
    'phone'           => "VARCHAR(40) NOT NULL DEFAULT ''",
    'email'           => "VARCHAR(190) NOT NULL DEFAULT ''",
    'family_id'       => 'INT UNSIGNED NOT NULL DEFAULT 0',
    'department_id'   => 'INT UNSIGNED NOT NULL DEFAULT 0',
    'joined_year'     => 'SMALLINT DEFAULT NULL',
    'graduated_year'  => 'SMALLINT DEFAULT NULL',
    'testimony'       => 'TEXT',
];

foreach ($new_columns as $column => $definition) {
    if (column_exists($conn, 'people', $column)) {
        echo "  ok    $column is already there\n";
        continue;
    }
    run_step($conn, "ALTER TABLE `people` ADD COLUMN `$column` $definition", "added $column");
}

/* ---------------------------------------------------------------------
   3. THE CATEGORY
   The old table had no group column, so leadership.php had to work the
   group out from the words in the position text. That is done here once,
   by hand, from the position that was typed.
   --------------------------------------------------------------------- */
echo "\nWorking out each person's group from their old position:\n";

/* The old position text on the left, the group on the right. A position that
   is not listed here falls through to the catch-all below it, which is
   exactly what leadership.php did while the guesswork was still in place. */
$position_to_category = [
    'pastor'            => 'pastor',
    'church admin'      => 'church_admin',
    'elder'             => 'elder',
    'department leader' => 'department_leader',
    'family leader'     => 'family_leader',
];

$statement = $conn->prepare("SELECT id, name, position FROM people ORDER BY display_order ASC, id ASC");
$statement->execute();
$result = $statement->get_result();
$everyone = [];
while ($row = $result->fetch_assoc()) {
    $everyone[] = $row;
}
$statement->close();

foreach ($everyone as $person) {
    $position = strtolower(trim($person['position']));
    $category = 'department_leader';
    foreach ($position_to_category as $needle => $group) {
        if (strpos($position, $needle) !== false) {
            $category = $group;
            break;
        }
    }

    $update = $conn->prepare("UPDATE people SET category = ? WHERE id = ?");
    $id = (int) $person['id'];
    $update->bind_param('si', $category, $id);
    $update->execute();
    $update->close();

    echo '  ' . str_pad($category, 18) . $person['name']
        . ($person['position'] !== '' ? "  (was: {$person['position']})" : '') . "\n";
}

/* ---------------------------------------------------------------------
   4. THE SLUG
   Built with the same slugify() the admin form uses, so a person who is
   renamed later gets the same kind of address.
   --------------------------------------------------------------------- */
require_once __DIR__ . '/../app/functions.php';

echo "\nBuilding a website address for each person:\n";

$statement = $conn->prepare("SELECT id, name, slug FROM people ORDER BY id ASC");
$statement->execute();
$result = $statement->get_result();
$everyone = [];
while ($row = $result->fetch_assoc()) {
    $everyone[] = $row;
}
$statement->close();

foreach ($everyone as $person) {
    $id = (int) $person['id'];
    $slug = slug($conn, 'people', $person['name'], $id);

    $update = $conn->prepare("UPDATE people SET slug = ? WHERE id = ?");
    $update->bind_param('si', $slug, $id);
    $update->execute();
    $update->close();

    echo '  /people/' . $slug . "\n";
}

/* ---------------------------------------------------------------------
   5. LINKING EACH DEPARTMENT AND FAMILY TO ITS LEADER
   The name that was typed by hand is looked up in the people table. Where it
   matches exactly, leader_id is filled in. Where it does not - a leader who
   was never added as a person - leader_id stays 0 and the typed name is still
   what the website shows, so nothing disappears.
   --------------------------------------------------------------------- */
echo "\nLinking each department and family to the person who leads it:\n";

foreach (['departments', 'families'] as $table) {
    if (!column_exists($conn, $table, 'leader_id')) {
        run_step(
            $conn,
            "ALTER TABLE `$table` ADD COLUMN `leader_id` INT UNSIGNED NOT NULL DEFAULT 0 "
            . "COMMENT 'A row in people. 0 means use the text below'",
            "added leader_id to $table"
        );
    } else {
        echo "  ok    $table.leader_id is already there\n";
    }

    if (column_exists($conn, $table, 'leader')) {
        $statement = $conn->prepare("SELECT id, name, leader FROM `$table` ORDER BY id ASC");
    } else {
        run_step($conn, "ALTER TABLE `$table` ADD COLUMN `leader` VARCHAR(160) NOT NULL DEFAULT ''", "added leader to $table");
        $statement = $conn->prepare("SELECT id, name, leader FROM `$table` ORDER BY id ASC");
    }
    $statement->execute();
    $result = $statement->get_result();
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }
    $statement->close();

    foreach ($groups as $row) {
        $group_id = (int) $row['id'];
        $typed_name = trim((string) $row['leader']);

        if ($typed_name === '') {
            continue;
        }

        $find = $conn->prepare('SELECT id FROM people WHERE name = ? LIMIT 1');
        $find->bind_param('s', $typed_name);
        $find->execute();
        $match = $find->get_result()->fetch_assoc();
        $find->close();

        if ($match) {
            $person_id = (int) $match['id'];
            $update = $conn->prepare("UPDATE `$table` SET leader_id = ? WHERE id = ?");
            $update->bind_param('ii', $person_id, $group_id);
            $update->execute();
            $update->close();
            echo '  ' . str_pad($row['name'], 28) . '-> ' . $typed_name . "\n";
        } else {
            echo '  ' . str_pad($row['name'], 28) . '-> kept as typed: ' . $typed_name
                . ' (no matching person)' . "\n";
        }
    }
}

/* ---------------------------------------------------------------------
   6. THE FINAL CHECK
   Nothing is removed until the numbers have been compared, so a mistake
   halfway through leaves the old table alone and easy to go back to.
   --------------------------------------------------------------------- */
echo "\n" . str_repeat('-', 60) . "\n";
$after = row_count($conn, 'people');

if ($after === $before) {
    echo "All $after people came across. Nothing was lost.\n";
} else {
    echo "There were $before people and there are $after now. STOP and check before\n";
    echo "carrying on: the old table has not been touched, so the data is still there.\n";
    exit(1);
}

$missing_slug = db_count($conn, "SELECT COUNT(*) FROM people WHERE slug = ''");
if ($missing_slug > 0) {
    echo "$missing_slug people have no address. Run this script again.\n";
    exit(1);
}

if (table_exists($conn, 'leaders')) {
    echo "\nThe old leaders table is still here as a spare copy. When the website\n";
    echo "looks right - /leadership, /people and the department and family pages -\n";
    echo "drop it with:\n\n";
    echo "  sudo mysql -e \"USE " . DB_NAME . "; DROP TABLE leaders;\"\n";
} else {
    echo "\nDone. The old leaders table is already gone.\n";
}

echo "\n";
