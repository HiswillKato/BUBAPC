<?php
/**
 * BUBAPC - The four little helpers every one-off migration script needs
 *
 * migrate-people.php and migrate-members.php each want to ask the same four
 * questions: is this table here, does it have this column, how many rows are in
 * it, and did that statement work. They used to carry their own copy of the
 * answers, which is four functions to keep in step in two files - and a
 * migration is exactly the sort of script nobody re-reads, so a fix made in one
 * of them would quietly never reach the other.
 *
 * Nothing on the website includes this file. It is only ever loaded by a
 * migration script, and both of those can be deleted once every live site has
 * been moved.
 *
 * WHY THESE USE query() INSTEAD OF prepare()
 *
 * SHOW statements cannot take a bound placeholder at all - there is nowhere to
 * put a ? in "SHOW COLUMNS FROM `x` LIKE ?" that MySQL will fill in - so the one
 * table and column name in each statement has to be escaped and written in
 * rather than bound. That is safe here for the reason the rest of the project
 * relies on: every table and column name these functions are asked about is
 * spelled out in the migration script itself, and nothing here ever reads a
 * word that came from a browser. The one place a name is escaped as well is
 * belt and braces for the day somebody adds a loop over user input.
 */

/**
 * Does this table exist?
 */
function table_exists($conn, $table)
{
    $safe = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '$safe'");
    return $result->num_rows > 0;
}

/**
 * Does this table have a column with this name?
 */
function column_exists($conn, $table, $column)
{
    $safe = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$safe'");
    return $result->num_rows > 0;
}

/**
 * Count the rows in a table.
 */
function row_count($conn, $table)
{
    $result = $conn->query("SELECT COUNT(*) FROM `$table`");
    $row = $result->fetch_row();
    return (int) $row[0];
}

/**
 * Run a statement with nothing in it from the outside world, and say what it
 * did. Every statement a migration runs is written out in full rather than
 * built from a value, so a plain query() is safe. Anything that carries a value
 * goes through prepare() and bind_param(), as the rest of the project does.
 *
 * The caller checks first whether the step is still needed, so running the
 * script twice prints "already done" and changes nothing.
 */
function run_step($conn, $sql, $label)
{
    if ($conn->query($sql) !== false) {
        echo "  ok    $label\n";
        return true;
    }
    echo "  FAILED $label\n         " . $conn->error . "\n";
    return false;
}
