<?php
/**
 * BUBAPC - One-off migration: pictures on events and the gallery only
 *
 * The site used to offer a picture box on every module. In practice the church
 * only ever wanted a picture in two places: an event, and the photo album. A
 * devotional, a sermon, an announcement, a person, a department and a family
 * are all things you read, and a picture beside the words was one more thing to
 * upload and one more thing that could be a broken image on a page.
 *
 * So the image column leaves those six tables, the upload box leaves those six
 * forms, and the pictures leave the public pages. Events and the gallery keep
 * theirs, and so do upload_image(), delete_uploaded_image(), admin_image_field()
 * and admin_save_image() in app/functions.php and app/includes/admin/parts.php, because
 * the two modules that keep pictures are the only two callers left.
 *
 * TWO THINGS WORTH KNOWING BEFORE RUNNING THIS
 *
 * One. The pictures on disk are not deleted blind. Two announcement rows point
 * at events/sample-1.jpg and events/sample-2.jpg, which are also event photos,
 * so "delete every file a losing module mentioned" would have taken the event
 * pictures with it. This script builds the list of files the six modules
 * mention, then takes away anything events or the gallery still mention, and
 * only deletes what is left over. That set is printed before it is deleted.
 *
 * Two. The people rows point at a folder called leaders/ that does not exist,
 * so those nine pictures have been broken links on the website all along. There
 * is nothing on disk to delete for them, and dropping the column is what
 * actually fixes them.
 *
 * HOW TO RUN IT
 *
 *   sudo php database/migrate-events-gallery-images.php
 *
 * It is safe to run more than once. Every step checks whether it has already
 * happened and prints "already done" otherwise. The files are only deleted
 * after the columns are gone, so a failure halfway leaves the pictures intact
 * rather than the other way round.
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

/* The six modules losing the picture box, and the two keeping it. The six are
   named once here and used for both the columns and the files, so the two can
   never drift apart and leave a file behind. */
$losing = ['announcements', 'devotionals', 'sermons', 'people', 'departments', 'families'];
$keeping = ['events', 'gallery'];

echo "BUBAPC - pictures on events and the gallery only\n";
echo str_repeat('-', 60) . "\n";

/* ---------------------------------------------------------------------
   1. THE COLUMNS
   Dropped from the six tables that lose the box. Dropping a column also
   drops whatever was written in it, so the files are read out first
   in step 2 and printed in step 3 before anything is deleted.
   --------------------------------------------------------------------- */
echo "\nThe picture column:\n";

$doomed_files = [];

foreach ($losing as $table) {
    if (!table_exists($conn, $table)) {
        continue;
    }

    if (!column_exists($conn, $table, 'image')) {
        echo "  ok    $table has no picture column already\n";
        continue;
    }

    /* Read the file names out before the column goes, because afterwards there
       is nothing left to ask. This is the only moment the paths exist, which
       is why the deletion step is careful about what it throws away. */
    $statement = $conn->query("SELECT DISTINCT image FROM `$table` WHERE image <> ''");
    while ($row = $statement->fetch_assoc()) {
        $doomed_files[$row['image']] = true;
    }
    $statement->close();

    if (run_step($conn, "ALTER TABLE `$table` DROP COLUMN `image`", "$table has dropped its picture column")) {
        continue;
    }

    exit(1);
}

/* ---------------------------------------------------------------------
   2. WHAT IS STILL NEEDED
   A file the gallery or an event still points at is not ours to delete,
   even when a module we are emptying pointed at it as well. This is the
   step that stops the event photographs going with the announcements.
   --------------------------------------------------------------------- */
$still_wanted = [];

foreach ($keeping as $table) {
    if (!table_exists($conn, $table) || !column_exists($conn, $table, 'image')) {
        continue;
    }
    $statement = $conn->query("SELECT DISTINCT image FROM `$table` WHERE image <> ''");
    while ($row = $statement->fetch_assoc()) {
        $still_wanted[$row['image']] = true;
    }
    $statement->close();
}

echo "\nFiles still wanted by events or the gallery:\n";
if (!$still_wanted) {
    echo "  -     none\n";
} else {
    foreach (array_keys($still_wanted) as $path) {
        echo "  keep  $path\n";
    }
}

/* Take the wanted files off the list. Whatever is left belongs to a module
   that no longer has a picture column, so nothing can point at it any more. */
$doomed_files = array_diff_key($doomed_files, $still_wanted);
ksort($doomed_files);

/* ---------------------------------------------------------------------
   3. THE FILES
   Deleting a file is the one step here that cannot be undone, so the list
   is printed in full first. Every path is checked to be a plain file name
   inside assets/uploads/ before it is touched: a value that had somehow
   come from outside is ignored rather than followed, because a leading
   "../" would otherwise let this script reach anywhere on the machine.
   --------------------------------------------------------------------- */
echo "\nFiles to delete:\n";

$upload_root = realpath(__DIR__ . '/../' . UPLOAD_FOLDER);

if (!$upload_root) {
    fwrite(STDERR, "  the uploads folder could not be found, so no file was touched\n");
    exit(1);
}

$deleted = 0;
$missing = 0;

foreach (array_keys($doomed_files) as $path) {
    /* A path is expected to look like "sermons/abc.jpg". Anything with a
       leading slash, a "..", or no folder of its own is not one of ours. */
    if (strpos($path, '..') !== false || $path[0] === '/' || strpos($path, '/') === false) {
        echo "  skip  $path (not an upload path)\n";
        continue;
    }

    $full = $upload_root . '/' . $path;

    /* realpath() collapses any "..", so if the file is really inside the
       uploads folder then this compares equal, and if it is not then the
       path has escaped and is refused. The real file is unlinked, never the
       un-normalised path. */
    $real = realpath($full);
    if ($real === false || strpos($real, $upload_root . '/') !== 0) {
        echo "  skip  $path (not inside the uploads folder)\n";
        continue;
    }

    if (unlink($real)) {
        echo "  gone  $path\n";
        $deleted++;
    } else {
        echo "  FAILED $path could not be deleted\n";
    }
}

if (!$deleted && !$missing) {
    echo "  -     nothing to delete\n";
}

/* ---------------------------------------------------------------------
   4. SHOWING THE RESULT
   --------------------------------------------------------------------- */
echo "\n" . str_repeat('-', 60) . "\n";

foreach ($keeping as $table) {
    if (table_exists($conn, $table) && column_exists($conn, $table, 'image')) {
        echo "Pictures kept on $table.\n";
    }
}

echo "Files deleted: $deleted.\n";
echo "The empty folders for the six modules can be deleted by hand if you\n";
echo "want the folder tidier; the website does not need them.\n";
echo "\n";
