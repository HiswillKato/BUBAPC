<?php
/**
 * BUBAPC - One-off migration: the official weekly program
 *
 * The church's week has meetings, each on a day, at a time, in a place. The
 * programs table could only say the day and the time, so this script is the
 * one place the rest is written: the venue, and for the two Glory meetings the
 * team that meets where.
 *
 * ONE ROW PER MEETING PER DAY
 *
 * The table stores one day number per row, so a meeting that happens on three
 * days is three rows - Morning Glory really does happen on Monday, Wednesday
 * and Friday. What the table does NOT do is one row per team: the two teams of
 * one meeting share the row, and where each team meets is named in the venue,
 * one place per team, separated by a semicolon:
 *
 *     Campus Team: The Ginnery; Syaule Team: Online
 *
 * Nothing is written twice. Whose place is whose is the only thing the venue
 * has to say; the time each team meets is a separate question, and it goes in
 * its own field, `times`:
 *
 *     5:00-5:45 AM (Campus Team); 5:30-6:00 AM (Syaule Team)
 *
 * That field is empty for every meeting whose teams meet together, which is
 * most of them - the Sunday Service runs from its start to its end and nothing
 * else needs saying. It is filled in only when the two teams of one meeting
 * really do meet at different times, because then a single start and end would
 * be a sentence that is not true of either team. The row's own start and end
 * stay as the window both teams meet in (5:00 to 6:00 AM), so the week still
 * sorts correctly and the home page can still work out what is on next.
 *
 * WHAT IT DOES, IN ORDER
 *
 *   1. Adds the times, venue and description columns if they are missing.
 *   2. Rewrites any venue still saying "Main Hall" to "Agape Palace (Main
 *      Hall)", so every screen uses the church's full name for the place.
 *   3. Folds the old per-team rows together: where one meeting on one day has
 *      more than one row that named a team, the first is kept and the extra
 *      rows are deleted, because the teams now share the row. Only rows that
 *      named a team are folded, so a meeting nobody gave a team to is never
 *      touched here.
 *   4. Drops the team column, which the venue has taken over.
 *   5. Writes the official week - eighteen meetings folded to twelve - matching
 *      each against what is already there before anything is written: a row
 *      that exists keeps its id, a row that is missing is added, and a second
 *      row for the same meeting on the same day is folded into the first.
 *
 * HOW TO RUN IT
 *
 *   sudo php database/migrate-programs-official.php
 *
 * It is safe to run more than once. Every step says whether it changed
 * something or found it already right, and a second run changes nothing.
 *
 * One thing to know before running it on a live site: step 3 and step 5 fold
 * rows of the SAME name on the SAME day into one. That is the point of a
 * weekly program - one meeting, one row - but it does mean a second meeting
 * that somebody deliberately named and dated exactly like an official one
 * would be folded into it. Nothing else is deleted, and no row of another
 * name is touched.
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

echo "BUBAPC - putting the official weekly program on the website\n";
echo str_repeat('-', 60) . "\n";

if (!table_exists($conn, 'programs')) {
    fwrite(STDERR, "  FAILED there is no programs table.\n");
    exit(1);
}

/* ---------------------------------------------------------------------
   1. THE NEW COLUMNS
   times and venue hold a list of one entry per team and are plain text,
   so times is nullable (empty for every meeting whose whole church meets
   together) and venue defaults to an empty word: a meeting whose venue is
   not fixed - Family Meetings are told to families by their leaders -
   says so in its description instead of having a made-up place printed.
   The description is nullable for the same reason.
   --------------------------------------------------------------------- */
echo "\nThe weekly program table:\n";

$columns = [
    'times'       => "ALTER TABLE `programs` ADD COLUMN `times` VARCHAR(190) NULL AFTER `end_time`",
    'venue'       => "ALTER TABLE `programs` ADD COLUMN `venue` VARCHAR(200) NOT NULL DEFAULT '' AFTER `times`",
    'description' => "ALTER TABLE `programs` ADD COLUMN `description` TEXT NULL AFTER `venue`",
];

foreach ($columns as $column => $sql) {
    if (column_exists($conn, 'programs', $column)) {
        echo "  ok    the $column column is already on the table\n";
    } elseif (!run_step($conn, $sql, "the $column column is now on the table")) {
        exit(1);
    }
}

/* ---------------------------------------------------------------------
   2. ONE NAME FOR THE MAIN HALL
   --------------------------------------------------------------------- */
echo "\nThe name of the main hall:\n";

$statement = $conn->prepare(
    "UPDATE programs SET venue = 'Agape Palace (Main Hall)'
     WHERE venue IN ('Main Hall', 'Main hall', 'main hall')"
);
$statement->execute();
$renamed = $statement->affected_rows;
$statement->close();

echo $renamed > 0
    ? "  ok    $renamed meeting" . ($renamed === 1 ? '' : 's') . " now say Agape Palace (Main Hall)\n"
    : "  ok    no venue is still using the short name\n";

/* ---------------------------------------------------------------------
   3. THE OLD PER-TEAM ROWS, FOLDED TOGETHER
   A row that names a team in the team column is one half of a meeting
   that now shares one row, so the extra halves go. The earliest row of
   the day is the one kept, and which row that is does not matter beyond
   that: step 5 puts the kept row's times and venue right either way.
   --------------------------------------------------------------------- */
echo "\nThe two teams, folded into one row per day:\n";

if (column_exists($conn, 'programs', 'team')) {
    /* The team column is a fixed word in the table itself, and this
       statement reads nothing from a form, so a plain read is safe. The
       ids come back in the order the rows happen, so the first is the
       earliest meeting of that day. */
    $teams = $conn->query(
        "SELECT title, day_of_week, GROUP_CONCAT(id ORDER BY start_time ASC, id ASC) AS ids
         FROM programs
         WHERE team <> ''
         GROUP BY title, day_of_week
         HAVING COUNT(*) > 1"
    );

    $folded = 0;
    $drop = $conn->prepare("DELETE FROM programs WHERE id = ?");

    while ($group = $teams->fetch_assoc()) {
        $ids = explode(',', $group['ids']);
        array_shift($ids);                     // the first row is the one kept

        foreach ($ids as $id) {
            $id = (int) $id;
            $drop->bind_param('i', $id);
            $drop->execute();
            $folded++;
        }
    }

    $drop->close();

    echo $folded > 0
        ? "  ok    $folded team row" . ($folded === 1 ? '' : 's') . " folded into their meeting\n"
        : "  ok    there are no per-team rows to fold\n";

    /* 4. THE TEAM COLUMN GOES
       The venue now says which team meets where (and the times field says
       when), so a column that only ever held the same word would be a
       second place to keep it right. */
    echo "\nThe team column:\n";

    if (run_step(
        $conn,
        "ALTER TABLE `programs` DROP COLUMN `team`",
        'the team column is gone, because the venue now carries the team'
    )) {
        echo "  ok    every meeting kept its rows and its id\n";
    } else {
        exit(1);
    }
} else {
    echo "  ok    there are no per-team rows and no team column (already folded)\n";
}

/* ---------------------------------------------------------------------
   5. THE OFFICIAL WEEK
   One row per meeting per day. day_of_week is 0 for Sunday ... 6 for
   Saturday, exactly as the column stores it. The two Glory meetings name
   their teams in the venue, and the morning one names each team's time in
   `times` as well, because those two teams do not meet at the same hour.
   --------------------------------------------------------------------- */
echo "\nThe official week:\n";

$glory_places = 'Campus Team: The Ginnery; Syaule Team: Online';
$glory_evening = 'Campus Team: The Ginnery; Syaule Team: Trumpet Center Church';
$glory_times = '5:00-5:45 AM (Campus Team); 5:30-6:00 AM (Syaule Team)';

$official = [
    ['Sunday Service',  0, '07:30:00', '10:30:00', '', 'Agape Palace (Main Hall)', 'Weekly Sunday worship service.', 1],
    ['Morning Glory',   1, '05:00:00', '06:00:00', $glory_times, $glory_places, '', 1],
    ['Morning Glory',   3, '05:00:00', '06:00:00', $glory_times, $glory_places, '', 1],
    ['Morning Glory',   5, '05:00:00', '06:00:00', $glory_times, $glory_places, '', 1],
    ['Evening Glory',   1, '19:00:00', '19:45:00', '', $glory_evening, '', 1],
    ['Evening Glory',   2, '19:00:00', '19:45:00', '', $glory_evening, '', 1],
    ['Evening Glory',   4, '19:00:00', '19:45:00', '', $glory_evening, '', 1],
    ['Midweek Service', 3, '18:00:00', '20:00:00', '', 'Agape Palace (Main Hall)', 'Weekly midweek church service.', 1],
    ['Lunch Hour',      4, '13:00:00', '13:45:00', '', 'Agape Palace (Main Hall)', 'Weekly lunch-hour fellowship.', 1],
    ['Family Meetings', 5, '17:30:00', '18:30:00', '', '', 'Weekly family fellowship meetings. The venue is communicated by the different family leaders.', 1],
    ['Choir Practice',  6, '17:00:00', '18:00:00', '', 'Agape Palace (Main Hall)', 'Weekly choir practice and preparation.', 1],
    ['Choir Practice',  0, '17:00:00', '18:00:00', '', 'Agape Palace (Main Hall)', 'Weekly choir practice and preparation.', 1],
];

/* Every row already carrying this meeting's name on this day, earliest
   first. One meeting on one day should be one row, so a second row here
   is a team's half of the meeting, or a duplicate, and either way it is
   folded into the row that is kept. */
$find = $conn->prepare(
    "SELECT id, start_time, end_time, times, venue, description, sort_order
     FROM programs
     WHERE title = ? AND day_of_week = ?
     ORDER BY start_time ASC, id ASC"
);

$write = $conn->prepare(
    "UPDATE programs
     SET start_time = ?, end_time = ?, times = NULLIF(?, ''), venue = ?, description = NULLIF(?, ''), sort_order = ?
     WHERE id = ?"
);

$add = $conn->prepare(
    "INSERT INTO programs (title, day_of_week, start_time, end_time, times, venue, description, sort_order, active)
     VALUES (?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, 1)"
);

$drop = $conn->prepare("DELETE FROM programs WHERE id = ?");

$added = 0;
$kept = 0;
$put_right = 0;
$folded = 0;

foreach ($official as $meeting) {
    [$title, $day, $start, $end, $times, $venue, $description, $sort] = $meeting;

    $find->bind_param('si', $title, $day);
    $find->execute();
    $rows = $find->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!$rows) {
        $add->bind_param('sisssssi', $title, $day, $start, $end, $times, $venue, $description, $sort);
        $add->execute();
        $added++;
        continue;
    }

    /* Keep the row that already starts at the official time if there is
       one, because that is most likely the row a visitor has been given
       the address of. Otherwise the earliest row of the day is kept. */
    $keep = $rows[0];
    foreach ($rows as $row) {
        if ($row['start_time'] === $start) {
            $keep = $row;
            break;
        }
    }

    foreach ($rows as $row) {
        if ((int) $row['id'] === (int) $keep['id']) {
            continue;
        }
        $id = (int) $row['id'];
        $drop->bind_param('i', $id);
        $drop->execute();
        $folded++;
    }

    /* Already right? Compare before writing, so a second run says "kept"
       for every row instead of claiming to have changed rows it only
       touched. The venue is the column an admin is most likely to have
       corrected by hand, so it is part of the comparison. */
    $same = $keep['start_time'] === $start
        && $keep['end_time'] === $end
        && (string) $keep['times'] === $times
        && $keep['venue'] === $venue
        && (string) $keep['description'] === $description
        && (int) $keep['sort_order'] === $sort;

    if ($same) {
        $kept++;
        continue;
    }

    $write->bind_param('sssssii', $start, $end, $times, $venue, $description, $sort, $keep['id']);
    $write->execute();
    $put_right++;
}

$find->close();
$write->close();
$add->close();
$drop->close();

echo "  ok    $added new, $put_right put right, $kept already correct";
echo $folded > 0 ? ", $folded duplicate row" . ($folded === 1 ? '' : 's') . " folded in\n" : "\n";

/* ---------------------------------------------------------------------
   6. THE WEEK AS IT NOW STANDS
   Read back and printed, so the result can be checked by eye against the
   church's own notice board instead of being taken on trust.
   --------------------------------------------------------------------- */
$result = $conn->query(
    "SELECT title, day_of_week, start_time, end_time, times, venue, active
     FROM programs
     ORDER BY day_of_week ASC, start_time ASC, sort_order ASC, id ASC"
);

$day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

echo "\n" . str_repeat('-', 60) . "\n";
printf("%-10s %-16s %-46s %s\n", 'Day', 'Meeting', 'Time', 'Venue');
echo str_repeat('-', 130) . "\n";

while ($row = $result->fetch_assoc()) {
    /* What a visitor is shown: the two teams' own times when they differ,
       otherwise the one window the meeting runs in. */
    $time_label = trim((string) $row['times']) !== ''
        ? str_replace('; ', '  /  ', $row['times'])
        : date('g:i A', strtotime($row['start_time'])) . '-' . date('g:i A', strtotime($row['end_time']));

    printf(
        "%-10s %-16s %-46s %s%s\n",
        $day_names[(int) $row['day_of_week']],
        $row['title'],
        $time_label,
        $row['venue'] !== '' ? $row['venue'] : '(told by the family leaders)',
        (int) $row['active'] === 1 ? '' : '  [hidden]'
    );
}

$total = $conn->query("SELECT COUNT(*) AS total FROM programs")->fetch_assoc();

echo str_repeat('-', 130) . "\n";
echo "The week now holds " . (int) $total['total'] . " meetings in total.\n";
echo "Times are shown in 12-hour form here; the database stores the window as TIME.\n";
echo "Running this script again changes nothing.\n";
echo "\n";
