<?php
/**
 * BUBAPC - One-off migration: put the photograph back on a person's page
 *
 * migrate-events-gallery-images.php took the picture box off six modules and
 * the people table was one of them, on the reasoning that a person is shown by
 * their initials. That was half right: initials are a good fallback, but the
 * church does want a face on a pastor, a student and a graduate.
 *
 * So the image column goes back on people, and nothing else does. Events and
 * the gallery keep theirs from that script, and the other four - devotionals,
 * sermons, announcements and departments - keep not having any. The six-module
 * script is left exactly as it was, because it is the record of what was
 * decided; this one undoes one line of it and says so.
 *
 * THE NINE OLD PICTURES ARE NOT COMING BACK, AND THAT IS DELIBERATE
 *
 * Before the column was dropped, all nine people pointed at files in a folder
 * called "leaders" that has never existed on the server. Every one of those
 * links was a broken image on the live website, which is why they were not
 * worth saving. Restoring the column restores the empty box, not those paths,
 * so every person starts on the initials and gets a face when somebody uploads
 * one. Putting the old filenames back would only put the broken pictures back.
 *
 * HOW TO RUN IT
 *
 *   sudo php database/migrate-people-photographs.php
 *
 * It is safe to run more than once: the step checks whether the column is
 * already there and prints "already done" otherwise. Nothing is deleted, and
 * no row is touched - the column arrives empty, which is the fallback.
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

echo "BUBAPC - photographs on people's pages again\n";
echo str_repeat('-', 60) . "\n";

if (!table_exists($conn, 'people')) {
    fwrite(STDERR, "  FAILED there is no people table.\n");
    exit(1);
}

/* The column goes back in the same place it was, between testimony and
   display_order, so a schema diff of the two versions shows one line moving
   rather than a column appearing somewhere new. */
if (column_exists($conn, 'people', 'image')) {
    echo "\n  ok    a person can already have a photograph\n";
} elseif (run_step(
    $conn,
    "ALTER TABLE `people` ADD COLUMN `image` VARCHAR(255) NOT NULL DEFAULT '' "
        . "COMMENT 'Relative to uploads/. Empty means show their initials' "
        . "AFTER `testimony`",
    'a person can have a photograph again'
)) {
    echo "\n  ok    and everybody is on their initials until a picture is uploaded\n";
} else {
    exit(1);
}

/* The folder the pictures go in. The uploads folder itself is made by hand, not
   here, because this script runs as root from a terminal while the website runs
   as www-data: a folder created by root would be root-owned and uploads would
   fail on the first attempt. This only says whether it is there. */
echo "\nThe folder for the pictures:\n";

if (is_dir(__DIR__ . '/../' . UPLOAD_FOLDER . '/people')) {
    echo "  ok    assets/uploads/people is there\n";
} else {
    echo "  MISSING create it, owned by the web server, before uploading:\n";
    echo "           sudo mkdir -p assets/uploads/people\n";
    echo "           sudo chgrp www-data assets/uploads/people\n";
    echo "           sudo chmod 775 assets/uploads/people\n";
}

echo "\n" . str_repeat('-', 60) . "\n";
echo "Open a person in the admin panel to give them a photograph.\n";
echo "Anyone with no picture is shown by their initials, which is not a\n";
echo "fallback that has to be switched on - it is what the page draws.\n";
echo "\n";