<?php

/**
 * BUBAPC - Migrate the database to one-word column names.
 *
 * This keeps every row. It renames columns, adds the three new tables
 * (tokens, logs, visitors) and does not read or write the content of a
 * single existing row, apart from settings, which gains an ordinary
 * `id` key alongside its `name`.
 *
 * Run it once, from the command line:
 *
 *   php database/migrate-one-word-names.php
 *
 * Running it a second time is harmless: every step asks the database
 * whether it still has to be done before doing it.
 *
 * Take a copy first if the data matters. That is one command:
 *
 *   sudo mysqldump -u bubapc_user -p bubapc > backup-before-rename.sql
 *
 * The names it produces, and why, are in the header of
 * database/bubapc.sql. Read that first - it is the reference, and this
 * file is only the way to get the live database to match it.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../app/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

/**
 * Does the live database have this table? The installer may not have
 * been run at all, so nothing here may assume a table is there.
 */
function migrate_has_table($conn, $table)
{
    $safe = $conn->real_escape_string($table);
    $result = $conn->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = '$safe' LIMIT 1"
    );
    return $result && $result->num_rows > 0;
}

/**
 * Does this table have this column? Every step below is guarded by
 * this, which is what makes running the file twice safe.
 */
function migrate_has_column($conn, $table, $column)
{
    $safe_table  = $conn->real_escape_string($table);
    $safe_column = $conn->real_escape_string($column);
    $result = $conn->query(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = '" . DB_NAME . "'
           AND TABLE_NAME = '$safe_table' AND COLUMN_NAME = '$safe_column' LIMIT 1"
    );
    return $result && $result->num_rows > 0;
}

/**
 * Does this table have an index of this name? Needed because MariaDB
 * keeps the OLD index name when a column is renamed, so the check has
 * to be about the index name, not the column it covers.
 */
function migrate_has_index($conn, $table, $index)
{
    $safe_table = $conn->real_escape_string($table);
    $safe_index = $conn->real_escape_string($index);
    $result = $conn->query(
        "SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = '" . DB_NAME . "'
           AND TABLE_NAME = '$safe_table' AND INDEX_NAME = '$safe_index' LIMIT 1"
    );
    return $result && $result->num_rows > 0;
}

/**
 * The definitions of the three new tables, written out rather than
 * read from database/bubapc.sql, so this file works on a database that
 * is already installed and has no reason to read a file that DROPs
 * every table it names.
 */
$migrate_new_tables = [
    'tokens' => "CREATE TABLE `tokens` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user` INT UNSIGNED NOT NULL COMMENT 'A row in users',
        `type` ENUM('password_reset') NOT NULL DEFAULT 'password_reset',
        `hash` CHAR(64) NOT NULL COMMENT 'SHA-256 of the token, never the token itself',
        `expires` DATETIME NOT NULL,
        `used` DATETIME DEFAULT NULL,
        `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `hash` (`hash`),
        KEY `user` (`user`),
        KEY `expires` (`expires`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'logs' => "CREATE TABLE `logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `actor` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'A row in users, or 0 for a visitor',
        `action` VARCHAR(60) NOT NULL COMMENT 'The event name, for example event_created',
        `details` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Finished English text, ready to print',
        `ip` VARCHAR(45) NOT NULL DEFAULT '',
        `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `actor` (`actor`),
        KEY `action` (`action`),
        KEY `created` (`created`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'visitors' => "CREATE TABLE `visitors` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `ip` CHAR(64) NOT NULL COMMENT 'The hashed address, never the address itself',
        `agent` VARCHAR(255) NOT NULL DEFAULT '',
        `path` VARCHAR(190) NOT NULL DEFAULT '',
        `referer` VARCHAR(255) NOT NULL DEFAULT '',
        `visits` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        `seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `ip` (`ip`),
        KEY `seen` (`seen`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($migrate_new_tables as $table => $sql) {
    if (migrate_has_table($conn, $table)) {
        continue;
    }
    $conn->query($sql);
    echo "Created the $table table.\n";
}

/**
 * departments and families are the one place the rule bites twice: the
 * old table has BOTH leader_id (a number pointing at people) and leader
 * (a name typed in by hand), and the new table wants the number to be
 * called leader with the typed name called typed.
 *
 * So the typed name has to move out of the way FIRST. Renaming leader_id
 * to leader while a column called leader is still there is a duplicate
 * column name, and MariaDB refuses it. The order below is not a style
 * choice; it is the only order that works.
 */
foreach (['departments', 'families'] as $table) {
    if (!migrate_has_table($conn, $table)) {
        continue;
    }

    if (migrate_has_column($conn, $table, 'leader')
        && !migrate_has_column($conn, $table, 'typed')
        && migrate_has_column($conn, $table, 'leader_id')) {
        $conn->query("ALTER TABLE `$table` CHANGE `leader` `typed` VARCHAR(160) NOT NULL DEFAULT ''");
        echo "$table: the typed leader name is now `typed`.\n";
    }

    if (migrate_has_column($conn, $table, 'leader_id') && !migrate_has_column($conn, $table, 'leader')) {
        $conn->query("ALTER TABLE `$table` CHANGE `leader_id` `leader` INT UNSIGNED NOT NULL DEFAULT 0");
        echo "$table: leader_id is now `leader`.\n";
    }
}

/**
 * settings is the odd one: its key used to BE the name, so the name is
 * now a unique column and there is an ordinary `id` alongside it. The
 * old primary key has to go before another one can be added.
 */
if (migrate_has_table($conn, 'settings')) {
    if (migrate_has_column($conn, 'settings', 'setting_key') && !migrate_has_column($conn, 'settings', 'name')) {
        $conn->query("ALTER TABLE `settings` CHANGE `setting_key` `name` VARCHAR(64) NOT NULL");
        echo "settings: setting_key is now `name`.\n";
    }

    if (migrate_has_column($conn, 'settings', 'setting_value') && !migrate_has_column($conn, 'settings', 'value')) {
        $conn->query("ALTER TABLE `settings` CHANGE `setting_value` `value` TEXT DEFAULT NULL");
        echo "settings: setting_value is now `value`.\n";
    }

    if (migrate_has_column($conn, 'settings', 'name') && !migrate_has_column($conn, 'settings', 'id')) {
        // The name is the primary key at this point, so drop it first.
        $conn->query("ALTER TABLE `settings` DROP PRIMARY KEY");
        $conn->query("ALTER TABLE `settings` ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
        echo "settings: added the ordinary `id` key.\n";
    }

    if (migrate_has_column($conn, 'settings', 'name') && !migrate_has_index($conn, 'settings', 'name')) {
        $conn->query("ALTER TABLE `settings` ADD UNIQUE KEY `name` (`name`)");
        echo "settings: name is unique.\n";
    }
}

/**
 * Everything else is a straight rename, and each one has to carry the
 * column's real definition with it. Renaming a column in MariaDB means
 * stating the whole column again, so a wrong definition here silently
 * changes the type - which is exactly what happened to people.family
 * while this file was being written, so the types are now spelled out
 * per column rather than guessed from a pattern.
 *
 * The key is table => column => [new name, new definition].
 */
$migrate_renames = [
    'users' => [
        'last_login' => ['login', 'DATETIME DEFAULT NULL'],
        'created_at' => ['created', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ],
    'login_attempts' => [
        'ip_address'   => ['ip', "VARCHAR(45) NOT NULL DEFAULT ''"],
        'attempted_at' => ['attempted', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ],
    'events' => [
        'created_at' => ['created', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ],
    'devotionals' => [
        'created_at' => ['created', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ],
    'sermons' => [
        'created_at' => ['created', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ],
    'announcements' => [
        'created_at' => ['created', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ],
    'people' => [
        'family_id'     => ['family', 'INT UNSIGNED NOT NULL DEFAULT 0'],
        'department_id' => ['department', 'INT UNSIGNED NOT NULL DEFAULT 0'],
        'created_at'    => ['created', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ],
    'gallery' => [
        'created_at' => ['created', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ],
    'messages' => [
        'is_read'    => ['seen', 'TINYINT(1) NOT NULL DEFAULT 0'],
        'created_at' => ['created', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ],
    'subscribers' => [
        'created_at' => ['created', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ],
];

foreach ($migrate_renames as $table => $columns) {
    if (!migrate_has_table($conn, $table)) {
        continue;
    }

    foreach ($columns as $from => $change) {
        list($to, $definition) = $change;

        if (!migrate_has_column($conn, $table, $from) || migrate_has_column($conn, $table, $to)) {
            continue;
        }

        $conn->query("ALTER TABLE `$table` CHANGE `$from` `$to` $definition");
        echo "$table: $from is now $to.\n";
    }
}

/**
 * An index keeps the name it had when its column was renamed, so a
 * renamed column usually leaves an index called the old name behind.
 * The names are part of the convention, so old ones are dropped and new
 * ones added. The column is already there; this only fixes the label.
 */
$migrate_indexes = [
    'departments'    => ['add' => ['leader' => 'leader'], 'drop' => ['leader_id']],
    'families'       => ['add' => ['leader' => 'leader'], 'drop' => ['leader_id']],
    'people'         => ['add' => ['family' => 'family', 'department' => 'department'], 'drop' => ['family_id', 'department_id']],
    'messages'       => ['add' => ['seen' => 'seen', 'created' => 'created'], 'drop' => ['is_read', 'created_at']],
    'login_attempts' => ['add' => ['attempted' => 'attempted'], 'drop' => ['attempted_at', 'ip_address']],
];

foreach ($migrate_indexes as $table => $work) {
    if (!migrate_has_table($conn, $table)) {
        continue;
    }

    foreach ($work['add'] as $index => $column) {
        if (migrate_has_index($conn, $table, $index)) {
            continue;
        }

        // The column has to exist under its new name before the index can
        // be built, or MariaDB fails on a column it cannot find.
        if (!migrate_has_column($conn, $table, $column)) {
            continue;
        }

        $conn->query("ALTER TABLE `$table` ADD KEY `$index` (`$column`)");
        echo "$table: added the $index index.\n";
    }

    foreach ($work['drop'] as $index) {
        if (!migrate_has_index($conn, $table, $index)) {
            continue;
        }

        $conn->query("ALTER TABLE `$table` DROP KEY `$index`");
        echo "$table: dropped the $index index.\n";
    }
}

/**
 * Types that are worth checking even when the NAME is already right.
 *
 * An earlier draft of this file spelled one of these types wrongly, so a
 * database that was renamed by that draft has the right column name in the
 * wrong type: messages.seen came out as a DATETIME instead of a 0-or-1
 * number, which meant a new contact message was stored as the date
 * 0000-00-00 and "WHERE seen = 0" never found it again.
 *
 * A rename is skipped when the new name is already there, so the mistake
 * would never have corrected itself. This last pass compares the live type
 * with the one the schema asks for and puts it right, which also makes the
 * script safe to run again at any time.
 */
$migrate_types = [
    'messages.seen'   => 'TINYINT(1) NOT NULL DEFAULT 0',
    'people.family'   => 'INT UNSIGNED NOT NULL DEFAULT 0',
    'people.department' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    'departments.leader' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    'families.leader' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    'login_attempts.ip' => "VARCHAR(45) NOT NULL DEFAULT ''",
];

foreach ($migrate_types as $target => $definition) {
    list($table, $column) = explode('.', $target);

    if (!migrate_has_table($conn, $table) || !migrate_has_column($conn, $table, $column)) {
        continue;
    }

    $statement = $conn->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $schema_name = DB_NAME;
    $statement->bind_param('sss', $schema_name, $table, $column);
    $statement->execute();
    $live_type = $statement->get_result()->fetch_assoc();
    $statement->close();

    /* Only the base type is compared, and the display width MariaDB reports
       ("int(10)") is dropped before the comparison, so a perfectly good
       column is not rewritten on every run. The rest of the string (NOT
       NULL, the default, a comment) is left as it is. */
    if ($live_type) {
        /* Both sides are reduced to their base type: the first word, with any
           width in brackets taken off. "TINYINT(1)" and the "tinyint(1)"
           MariaDB reports are the same type written two ways, and comparing
           them as written would rewrite the column on every single run. */
        $wanted_base = strtolower(strtok(strtok($definition, ' '), '('));
        $actual_base = strtolower(strtok($live_type['COLUMN_TYPE'], '('));

        if ($actual_base === $wanted_base) {
            continue;
        }
    }

    $conn->query("ALTER TABLE `$table` MODIFY `$column` $definition");
    echo "$target: the type is now $definition.\n";
}

echo "\nDone. The database now matches the names in database/bubapc.sql.\n";
$conn->close();
