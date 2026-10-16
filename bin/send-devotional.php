#!/usr/bin/env php
<?php
/**
 * BUBAPC - Email the day's devotional to the list
 *
 * Run it once a day from cron, a little after the time the devotional should
 * reach people. Every morning at a quarter past six, for example:
 *
 *   15 6 * * *  /usr/bin/php /var/www/bubapc/bin/send-devotional.php >> /var/log/bubapc-devotional.log 2>&1
 *
 * It sends the devotional DATED for that day and nothing else. Writing a
 * devotional for a future day is what schedules it, so several can be written
 * in one sitting and each goes out on its own morning. A day with nothing dated
 * for it sends nothing, which is what stops yesterday's message going out twice.
 *
 * Only addresses with status = 1 in the subscribers table receive it, so an
 * address switched off in the admin panel is skipped without being deleted.
 *
 * Options, for a test or a one-off catch-up:
 *   --date=YYYY-MM-DD   send that day's devotional instead of today's
 *   --dry-run           list who would get it and send nothing
 *   --host=NAME         the address to put in the link when the website has no
 *                       BASE_URL set (the live server should set BASE_URL)
 *
 * It is a command, never a page, so it refuses to run over HTTP.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("send-devotional.php is a command-line tool.\n");
}

require_once __DIR__ . '/../app/init.php';

/* The website's failure handler draws an HTML page, which is no use in a
   terminal or a log file, so it is replaced here with a plain one-liner. */
set_exception_handler(function ($problem) {
    fwrite(STDERR, 'send-devotional: ' . $problem->getMessage() . "\n");
    exit(1);
});

/* =========================================================================
   1. WHAT WAS ASKED FOR
   ========================================================================= */

$dry_run = false;
$day = date('Y-m-d');
$host = '';

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--dry-run') {
        $dry_run = true;
    } elseif (strpos($argument, '--date=') === 0) {
        $day = substr($argument, 7);
    } elseif (strpos($argument, '--host=') === 0) {
        $host = substr($argument, 7);
    } else {
        fwrite(STDERR, "Unknown option: $argument\n");
        exit(2);
    }
}

[$year, $month, $day_of_month] = array_map('intval', array_pad(explode('-', $day), 3, 0));
if (!checkdate($month, $day_of_month, $year)) {
    fwrite(STDERR, "The date has to look like 2026-01-31.\n");
    exit(2);
}

/* url_raw() builds the link in the email. With no BASE_URL set it reads the
   name the browser asked for, and a command has no browser, so --host is the
   way to say it. The live server should set BASE_URL in app/config.php and
   leave this alone. */
if ($host !== '') {
    $_SERVER['HTTP_HOST'] = $host;
}

/* =========================================================================
   2. THE DEVOTIONAL DATED FOR THE DAY
   ========================================================================= */

$statement = $conn->prepare(
    "SELECT * FROM devotionals WHERE status = 'published' AND devotional_date = ? LIMIT 1"
);
$statement->bind_param('s', $day);
$statement->execute();
$devotional = $statement->get_result()->fetch_assoc();
$statement->close();

if (!$devotional) {
    echo 'No published devotional is dated for ' . $day . ". Nothing to send.\n";
    exit(0);
}

/* =========================================================================
   3. EVERY ADDRESS THAT IS SWITCHED ON
   ========================================================================= */

$statement = $conn->prepare("SELECT email FROM subscribers WHERE status = 1 ORDER BY id ASC");
$statement->execute();
$result = $statement->get_result();

$total = 0;
$sent = 0;
$failed = 0;

while ($row = $result->fetch_assoc()) {
    $total++;

    if ($dry_run) {
        echo $row['email'] . "\n";
        continue;
    }

    if (mail_devotional($row['email'], $devotional)) {
        $sent++;
    } else {
        $failed++;
        fwrite(STDERR, 'Could not send to ' . $row['email'] . "\n");
    }
}

$statement->close();

/* =========================================================================
   4. SAY WHAT HAPPENED
   ========================================================================= */

$label = $day . ' "' . $devotional['title'] . '"';

if ($dry_run) {
    printf("[%s] dry run: %d address(es) would get %s\n", date('Y-m-d H:i:s'), $total, $label);
    exit(0);
}

printf("[%s] sent %d of %d address(es): %s\n", date('Y-m-d H:i:s'), $sent, $total, $label);

if ($failed > 0) {
    printf("  %d address(es) could not be sent to. Check the mail server.\n", $failed);
    exit(1);
}

exit(0);
