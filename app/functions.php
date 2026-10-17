<?php
/**
 * BUBAPC - Reusable helper functions
 *
 * Every function here is a plain procedural function. There are no classes
 * and no framework. Any page can use them after including this file.
 *
 * The file is divided into numbered sections and is not to be split: a helper
 * goes into the section it belongs to, next to the ones it works with, so that
 * everything about one job can be read in one place. The sections that concern
 * one request come first, then the ones that write text into a page, then the
 * ones about reading and saving content.
 */

/* =========================================================================
   1. REQUEST AND CLIENT ADDRESS
   ========================================================================= */

/**
 * The visitor's IP address, used to count visitors and to slow down repeated
 * wrong passwords.
 *
 * The site may sit behind a proxy or a CDN, in which case REMOTE_ADDR is the
 * proxy's address and every visitor looks like the same computer. The two
 * forwarded headers are only honoured for the first value each carries, and
 * only the CF-Connecting-IP (set by Cloudflare) or the first X-Forwarded-For
 * entry is used; a visitor cannot claim a different address by sending more
 * entries, because only the first one is ever read. On a plain direct
 * connection neither header exists and REMOTE_ADDR is returned as it always
 * was. The list is truncated to fit the ip columns, which are VARCHAR(45).
 *
 * It lives here rather than in auth.php because it is a property of the
 * request, not of an account: the visitor counter needs it just as much as the
 * login throttle does, and a helper that only exists in the file about signing
 * in is a helper the rest of the site has to know about.
 */
function ip()
{
    $sources = [
        $_SERVER['CF-Connecting-IP'] ?? '',
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] : '',
    ];

    foreach ($sources as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            return substr($candidate, 0, 45);
        }
    }

    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

/**
 * The browser string the visitor arrived with, cut to the 255 characters the
 * column holds.
 *
 * It is stored as it arrives rather than tidied up first, because it is a
 * fingerprint: the visitor counter hashes the address together with this string
 * to recognise a returning browser, and folding "Firefox" and "Firefox
 * Developer Edition" down to the same word would count two people as one.
 * A visitor can send anything at all in this header, so it is printed with e()
 * wherever it is shown.
 */
function agent()
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/**
 * A plain name for the browser an agent string came from, for showing on the
 * visitors page: "Chrome", "Firefox", "Safari", "Edge" or "Other".
 *
 * agent() keeps the whole string because the visitor counter needs it to
 * recognise a returning browser, but printed on its own it is a wall of jargon
 * on a screen a church secretary reads. This is only ever used to print, never
 * to match or to store, so the only thing that has to be right is the order of
 * the checks: Edge and Opera both carry "Chrome" in their string, and Chrome
 * carries "Safari", so the narrower names are tested first.
 */
function browser_name($user_agent)
{
    $browsers = [
        'Edg'     => 'Edge',
        'OPR'     => 'Opera',
        'Samsung' => 'Samsung Internet',
        'Firefox' => 'Firefox',
        'Chrome'  => 'Chrome',
        'Safari'  => 'Safari',
        'MSIE'    => 'Internet Explorer',
        'Trident' => 'Internet Explorer',
    ];

    foreach ($browsers as $needle => $name) {
        if (stripos((string) $user_agent, $needle) !== false) {
            return $name;
        }
    }

    return $user_agent === '' ? 'Unknown' : 'Other';
}

/**
 * The address of the page the visitor came from, or an empty string when they
 * typed the address or followed a link in an app that does not say.
 *
 * Cut to the 255 characters the column holds, and left as the raw header on
 * purpose: it is stored to be read as a whole and escaped at the print site,
 * never interpreted as a link.
 */
function referer()
{
    return substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 255);
}

/* =========================================================================
   2. TEXT AND URL HELPERS
   ========================================================================= */

/**
 * Escape a value before printing it inside HTML.
 * This stops a visitor from putting HTML or JavaScript into a page.
 */
function e($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Build a full website address, without escaping it.
 *
 * This is the one function that assembles an address, and everything else
 * goes through it. It is the form for places that are NOT writing HTML: the
 * Location header of a redirect, and the links inside an email. For those, an
 * escaped address is a broken address - "&amp;" in a Location header sends the
 * browser to a page called "amp".
 *
 * A page building an href or a src wants url() instead, which escapes.
 * Example: url_raw('events') gives "https://bubapc.int.yt/events"
 */
function url_raw($path = '')
{
    if (BASE_URL !== '') {
        $base = rtrim(BASE_URL, '/');
    } else {
        // No BASE_URL set, so work out the address from the current request.
        $on_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $on_https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // When BUBAPC_HOSTS is a non-empty list, only a name on it may drive
        // the address. Anything else (a changed /etc/hosts entry, a stray
        // Host header, a bot) falls back to the first allowed name, so no
        // foreign address is ever handed out in links or emails. An empty
        // list trusts the request, which is the local-development behaviour.
        if ($host !== '') {
            $allowed = is_array(BUBAPC_HOSTS) ? BUBAPC_HOSTS : [];
            if ($allowed && !in_array($host, $allowed, true)) {
                $host = $allowed[0];
            }
        }

        $base = $scheme . '://' . $host . rtrim(BASE_PATH, '/');
    }

    $path = ltrim($path, '/');
    if ($path === '') {
        return $base . '/';
    }
    return $base . '/' . $path;
}

/**
 * Build a full website URL, escaped for printing into HTML.
 *
 * Almost every caller is writing an href or a src, so the escaping happens
 * here once rather than as e(url(...)) at several hundred call sites. The few
 * places that are not writing HTML - redirect() and the password-reset email -
 * use url_raw() instead.
 */
function url($path = '')
{
    return url_raw($path);
}

/**
 * Build the URL of a file inside the assets folder.
 * Example: asset('css/style.css')
 */
function asset($path = '')
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Build the URL of an uploaded image.
 * When the database value is empty a soft placeholder is returned, so a page
 * never breaks because of a missing image.
 */
function upload_url($path = '')
{
    if ($path === '' || $path === null) {
        return asset('images/placeholder.svg');
    }
    return url(UPLOAD_FOLDER . '/' . ltrim($path, '/'));
}

/**
 * Send the visitor to another page and stop the script running.
 *
 * $path is normally a path from the root of the site, like 'admin/events'.
 * A full address is also accepted, so a page can write
 * redirect(admin_url('events')) and not have the site name put in twice -
 * but only if it is an address on this same website. Anywhere else is ignored
 * and the visitor is sent to the home page instead, which closes the door on
 * a "sign in and then get sent to another site" trick.
 */
function redirect($path)
{
    /* The URL builders escape what they hand back, because almost all of their
       callers are printing an href. A Location header is not HTML, so anything
       that arrives escaped is decoded before it is sent - and decoding is safe
       here precisely because a real address never carries an entity, so this
       cannot invent characters that were not asked for. Without it, the sixty
       odd redirect(admin_url('events')) calls would put "&amp;" in a header
       the moment a module gained a query string. */
    $path = html_entity_decode((string) $path, ENT_QUOTES, 'UTF-8');

    if (preg_match('#^(https?://)([^/]+)#i', $path, $matches) === 1) {
        $host = strtolower($matches[2]);
        $our_host = strtolower(preg_replace('#:\d+$#', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));

        if ($host === $our_host) {
            header('Location: ' . $path);
            exit;
        }

        $path = '';
    }

    header('Location: ' . url_raw($path));
    exit;
}

/**
 * The path of the page being viewed, with no leading or trailing slash and no
 * query string, for example "events" or "devotionals/a-praise-and-worship-mindset".
 *
 * This is the one place the address bar is taken apart, so a menu item and a
 * heading can never disagree about which page they are on. The folder the
 * project sits in is removed as well, because a menu item asked for "events"
 * should not have to know whether the site is at the root of the domain or in
 * a sub folder.
 *
 * The address itself comes from current_page_path() instead, because a page
 * button has to hand back the exact path the browser asked for - this one
 * tidies the path up for matching, which is not the same job.
 */
function current_path()
{
    $path = trim((string) parse_url(current_page_path(), PHP_URL_PATH), '/');

    $base_path = trim(BASE_PATH, '/');
    if ($base_path !== '' && strpos($path, $base_path) === 0) {
        $path = trim(substr($path, strlen($base_path)), '/');
    }

    return $path;
}

/**
 * The whole address of the page being viewed, query string and all, such as
 * "/dashboard/admin/events?q=praise&page=2". This is what a person should be
 * sent back to after signing in, and what the canonical link should point at,
 * because both of those want the address they actually asked for.
 */
function current_page_path()
{
    return $_SERVER['REQUEST_URI'] ?? '/';
}

/**
 * Turn a title into a URL friendly slug.
 * "A Praise & Worship Mindset" becomes "a-praise-worship-mindset"
 */
function slugify($text)
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s\-_]/', '', $text);
    $text = preg_replace('/[\s\-_]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Make a slug for a record that is not already used in the given table.
 *
 * This is the only way a slug is ever made. There is no box on a form for
 * typing one, because a typed address arrives with capitals and spaces in it
 * and then looks wrong in the address bar. A number is added to the end when
 * another record already has this one.
 *
 * $ignore_id is the record being edited, so a record keeps its own address
 * instead of colliding with itself when its title is corrected.
 */
function slug($conn, $table, $text, $ignore_id = 0)
{
    // Only these tables can be used. This keeps the query safe.
    $allowed_tables = ['events', 'sermons', 'departments', 'families', 'devotionals', 'announcements', 'people'];
    if (!in_array($table, $allowed_tables, true)) {
        return slugify($text);
    }

    $slug = slugify($text);
    if ($slug === '') {
        $slug = 'item';
    }

    $number = 1;
    while (true) {
        $test_slug = ($number > 1) ? $slug . '-' . $number : $slug;

        $sql = "SELECT id FROM `$table` WHERE slug = ? AND id <> ? LIMIT 1";
        $statement = $conn->prepare($sql);
        $statement->bind_param('si', $test_slug, $ignore_id);
        $statement->execute();
        $result = $statement->get_result();
        $exists = $result->fetch_assoc();
        $statement->close();

        if (!$exists) {
            return $test_slug;
        }
        $number++;
    }
}

/**
 * Shorten a long text and add three dots.
 */
function excerpt($text, $length = 160)
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    if (strlen($text) <= $length) {
        return $text;
    }
    $cut = mb_substr($text, 0, $length, 'UTF-8');
    $last_space = mb_strrpos($cut, ' ', 0, 'UTF-8');
    if ($last_space !== false) {
        $cut = mb_substr($cut, 0, $last_space, 'UTF-8');
    }
    return $cut . '...';
}

/**
 * Turn plain text into safe HTML paragraphs.
 * Used for the devotional message and other long articles.
 */
function paragraphs($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    return '<p>' . nl2br(e($text), false) . '</p>';
}

/**
 * Turn a list of lines (one item per line) into list items.
 * Used for the devotional "Further Study" which has one reference per line.
 */
function line_list($text, $class = '')
{
    $lines = preg_split('/\r\n|\r|\n/', (string) $text);
    $lines = array_filter(array_map('trim', $lines), 'strlen');

    if (!$lines) {
        return '';
    }

    $html = '<ul class="' . e($class) . '">';
    foreach ($lines as $line) {
        $html .= '<li>' . e($line) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

/**
 * The first letters of a name, used to draw a nice avatar circle.
 * "Samuel Okello" becomes "SO".
 */
function initials($name)
{
    $words = preg_split('/\s+/', trim((string) $name));
    $letters = '';
    foreach ($words as $word) {
        if ($word !== '') {
            $letters .= mb_strtoupper(mb_substr($word, 0, 1));
        }
        if (mb_strlen($letters) === 2) {
            break;
        }
    }
    return e($letters === '' ? 'B' : $letters);
}

/* =========================================================================
   3. SMALL DATABASE HELPERS
   ========================================================================= */
/* These only save typing, so they are only used for fixed SQL that contains
   no user input. Anything that comes from a form, a URL or a login box
   always uses $conn->prepare() with bind_param().

   Only two shapes of reading are allowed here: one row (db_one) and one number
   (db_count). A page that needs to walk many rows writes the loop itself, so
   there is no third helper to remember the difference between. */

/**
 * Run a fixed SELECT query and return the first row as an array.
 * Returns an empty array when there is no row.
 */
function db_one($conn, $sql)
{
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row ? $row : [];
}

/**
 * Count the rows a fixed query returns.
 */
function db_count($conn, $sql)
{
    $result = $conn->query($sql);
    $row = $result->fetch_row();
    return (int) $row[0];
}

/**
 * The id of the record that was just inserted.
 */
function db_last_id($conn)
{
    return $conn->insert_id;
}

/**
 * Count the rows a SELECT returns, when the SQL has ? placeholders in it.
 *
 * db_count() above uses $conn->query(), which is only allowed for fixed SQL
 * because a browser-supplied word would be read as SQL. A counting query that
 * searches for something the visitor typed has placeholders, so it has to be
 * prepared and bound like any other reading of $_GET.
 *
 * Returns 0 rather than throwing if the statement cannot be prepared, because a
 * broken count should not take the whole page down - the list below it is
 * prepared separately and will report the real problem.
 */
function db_count_prepared($conn, $sql, $types, $values)
{
    /* With no search word the SQL has no ? in it, and bind_param() refuses an
       empty type string, so the plain count is both allowed and simpler here. */
    if ($types === '' || $values === []) {
        return db_count($conn, $sql);
    }

    try {
        $statement = $conn->prepare($sql);
        $statement->bind_param($types, ...$values);
        $statement->execute();
        $result = $statement->get_result();
        $row = $result->fetch_row();
        $statement->close();
        return $row ? (int) $row[0] : 0;
    } catch (mysqli_sql_exception $problem) {
        return 0;
    }
}

/* =========================================================================
   4. DATE AND TIME HELPERS
   ========================================================================= */

/**
 * Format a date in a friendly way, for example "27 September 2026".
 */
function format_date($date, $format = 'd F Y')
{
    if (!$date || strpos((string) $date, '0000-00-00') === 0) {
        return '';
    }
    $timestamp = strtotime($date);
    return $timestamp ? e(date($format, $timestamp)) : '';
}

/**
 * Format a time, for example "7:30 AM".
 */
function format_time($time)
{
    if (!$time) {
        return '';
    }
    $timestamp = strtotime($time);
    return $timestamp ? e(date('g:i A', $timestamp)) : '';
}

/**
 * Format a time range, for example "7:30 AM - 10:30 AM".
 */
function format_time_range($start_time, $end_time)
{
    $start = format_time($start_time);
    $end = format_time($end_time);
    if ($start && $end) {
        return $start . ' &ndash; ' . $end;
    }
    return $start . $end;
}

/**
 * A readable version of how long ago something happened, "3 days ago".
 */
function ago($datetime)
{
    $timestamp = strtotime((string) $datetime);
    if (!$timestamp) {
        return '';
    }
    $seconds = time() - $timestamp;

    if ($seconds < 60) {
        return 'just now';
    }
    $minutes = floor($seconds / 60);
    if ($minutes < 60) {
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    }
    $hours = floor($minutes / 60);
    if ($hours < 24) {
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    }
    $days = floor($hours / 24);
    if ($days < 30) {
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    $months = floor($days / 30);
    if ($months < 12) {
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    }
    $years = floor($months / 12);
    return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
}

/**
 * The seven day names, matching the day_of_week numbers in the programs
 * table (0 = Sunday ... 6 = Saturday).
 */
function day_name($day_number)
{
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $day_number = (int) $day_number;
    return isset($days[$day_number]) ? e($days[$day_number]) : '';
}

/**
 * "Good morning", "Good afternoon" or "Good evening" for the hour now.
 *
 * Both dashboards open with it, because the first screen a member or a
 * secretary sees in the day should say hello rather than hand over a page
 * of instructions. It lives here so the two panels cannot drift into
 * different greetings, and it takes no argument on purpose: there is one
 * clock, so asking the caller for a time would only invite two answers.
 */
function greeting()
{
    $hour = (int) date('G');

    if ($hour < 12) {
        return 'Good morning';
    }
    return $hour < 18 ? 'Good afternoon' : 'Good evening';
}

/**
 * A short friendly label for an event date, for example
 * "Sat, 4 Oct 2026 at 6:00 PM".
 */
function event_date_label($event)
{
    $label = format_date($event['start_date'], 'D, j M Y');
    if (!empty($event['start_time'])) {
        $label .= ' at ' . format_time($event['start_time']);
    }
    return $label;
}

/**
 * The date and time of an event as one value JavaScript can count down to.
 * Returns '' when the event has no date.
 */
function event_moment($event)
{
    if (empty($event['start_date'])) {
        return '';
    }
    $time = !empty($event['start_time']) ? $event['start_time'] : '00:00:00';
    return $event['start_date'] . 'T' . $time;
}

/* =========================================================================
   5. FLASH MESSAGES
   ========================================================================= */

/**
 * Store a short message to show on the next page.
 * Types: success, error, info
 */
function flash($type, $message)
{
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

/**
 * Read the stored flash messages, clear them, and print them as Bootstrap
 * alerts. Reading and printing are one job: a page shows its messages exactly
 * once, so there is no point in letting something else read them first and
 * find the page blank.
 */
function show_flashes()
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    foreach ($messages as $message_item) {
        if ($message_item['type'] === 'success') {
            $class = 'alert-success';
        } elseif ($message_item['type'] === 'error') {
            $class = 'alert-danger';
        } else {
            $class = 'alert-info';
        }

        echo '<div class="alert ' . e($class) . ' alert-dismissible fade show" role="alert">';
        echo e($message_item['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

/**
 * Print a list of things that went wrong at the top of a form, on any page.
 * Both the public forms and the admin panel use this, so a message is worded
 * and laid out in one place.
 */
function form_errors($errors, $heading = 'Please fix the following before saving:')
{
    if (!$errors) {
        return;
    }
    ?>
    <div class="alert alert-danger" role="alert">
        <p class="fw-semibold mb-2">
            <i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i>
            <?php echo e($heading); ?>
        </p>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/* =========================================================================
   6. PAGINATION
   ========================================================================= */

/**
 * The page number asked for in the URL, for example ?page=3
 */
function page_number()
{
    return isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
}

/**
 * Work out where we are inside a list of results.
 * Returns an array with everything a list page needs.
 */
function paginate($total_items, $per_page = 12, $current_page = 1)
{
    $total_items = max(0, (int) $total_items);
    $per_page = max(1, (int) $per_page);
    $total_pages = (int) ceil($total_items / $per_page);
    if ($total_pages < 1) {
        $total_pages = 1;
    }

    $current_page = max(1, (int) $current_page);
    if ($current_page > $total_pages) {
        $current_page = $total_pages;
    }

    return [
        'total_items'  => $total_items,
        'per_page'     => $per_page,
        'total_pages'  => $total_pages,
        'current_page' => $current_page,
        'offset'       => ($current_page - 1) * $per_page,
        'from'         => $total_items === 0 ? 0 : (($current_page - 1) * $per_page) + 1,
        'to'           => min($total_items, $current_page * $per_page),
    ];
}

/**
 * Build the link to one page of a list, keeping the current filters.
 * Example: page_link(3, ['q' => 'praise']) gives "/devotionals?page=3&q=praise"
 */
function page_link($page_number_value, $extra_query = [])
{
    $query = $extra_query;
    $query['page'] = $page_number_value;

    /* Whatever query string the visitor already had is thrown away and the new
       one is put on, and the path is used exactly as the browser sent it -
       leading slash, sub folder and trailing slash all left alone. A page
       button should hand back the address they were on rather than a tidyed-up
       version of it: whether /devotionals or /devotionals/ is the right form is
       Apache's business, and it answers that with a redirect either way. */
    $address = strtok(current_page_path(), '?');
    return e($address . '?' . http_build_query($query));
}

/**
 * Print the page number buttons under a list.
 * Call it only when there is more than one page.
 */
function render_pagination($pagination, $extra_query = [])
{
    if ($pagination['total_pages'] < 2) {
        return;
    }

    $current = $pagination['current_page'];
    $total = $pagination['total_pages'];

    // Show at most five page numbers around the current page.
    $start = max(1, $current - 2);
    $end = min($total, $current + 2);

    echo '<nav aria-label="Page navigation" class="mt-4">';
    echo '<ul class="pagination justify-content-center flex-wrap">';

    // Previous button
    if ($current > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . page_link($current - 1, $extra_query) . '">&laquo;</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
    }

    for ($number = $start; $number <= $end; $number++) {
        if ($number === $current) {
            echo '<li class="page-item active" aria-current="page"><span class="page-link">' . $number . '</span></li>';
        } else {
            echo '<li class="page-item"><a class="page-link" href="' . page_link($number, $extra_query) . '">' . $number . '</a></li>';
        }
    }

    // Next button
    if ($current < $total) {
        echo '<li class="page-item"><a class="page-link" href="' . page_link($current + 1, $extra_query) . '">&raquo;</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
    }

    echo '</ul></nav>';
}

/**
 * A small line above a list, for example
 * "Showing 13 - 24 of 86 results".
 *
 * $when_empty is what to say instead when there is nothing at all. A public
 * page says nothing, because it is about to print its own "nothing here yet"
 * box and two messages would be noise. An admin list says "No records found",
 * because the person looking at it needs to know the search worked and the
 * table is genuinely empty rather than broken.
 */
function render_result_count($pagination, $label = 'results', $when_empty = '')
{
    if ($pagination['total_items'] === 0) {
        if ($when_empty !== '') {
            echo '<p class="text-body-secondary small mb-0">' . e($when_empty) . '</p>';
        }
        return;
    }
    echo '<p class="text-body-secondary small mb-0">Showing <strong>' . $pagination['from'] . '</strong> to <strong>'
        . $pagination['to'] . '</strong> of <strong>' . $pagination['total_items'] . '</strong> ' . e($label) . '</p>';
}

/**
 * The heading that introduces a section on a public page.
 *
 * The small line above the title is the "kicker". It is only printed when a
 * page has something to say, and it is always a Bootstrap class, so it turns
 * gold in dark mode by itself with no extra rule here.
 *
 *   section_heading('Upcoming events', 'Mark your calendar',
 *                   'Everything happening at the church in one place.');
 */
function section_heading($title, $kicker = '', $lead = '', $level = 2)
{
    ?>
    <div class="mb-4">
        <?php if ($kicker !== ''): ?>
            <p class="text-uppercase fw-bold small text-gold-dark mb-2"><?php echo e($kicker); ?></p>
        <?php endif; ?>
        <h<?php echo (int) $level; ?> class="mb-0"><?php echo e($title); ?></h<?php echo (int) $level; ?>>
        <?php if ($lead !== ''): ?>
            <p class="text-body-secondary mb-0 mt-2 col-lg-8 px-0"><?php echo e($lead); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * A friendly box for a list that has nothing in it yet.
 *
 * Every module on the website can be empty on the day the church launches, so
 * this is used a lot: it says what is missing and what to do about it rather
 * than showing a blank page.
 *
 *   empty_state('fa-calendar-plus', 'No events yet',
 *               'When an event is planned it will appear here.',
 *               '<a class="btn btn-gold" href="...">Tell us about one</a>');
 *
 * $style is 'public' or 'admin'. The words and the icon class are the same
 * either way, and only the frame around them changes: a raised gold card on a
 * public page, where an empty section should invite a visitor, and a plain
 * outlined box in the admin panel, where a screen full of tables should still
 * look like a screen full of tables. Both live here so there is one answer to
 * "what does an empty box look like" rather than one per kind of page.
 */
function empty_state($icon, $title, $text, $button_html = '', $style = 'public')
{
    if ($style === 'admin') {
        ?>
        <div class="text-center border border-secondary-subtle rounded-4 p-5 bg-body">
            <i class="fa-solid <?php echo e($icon); ?> fs-1 text-body-secondary" aria-hidden="true"></i>
            <h2 class="h5 mt-3"><?php echo e($title); ?></h2>
            <p class="text-body-secondary"><?php echo e($text); ?></p>
            <?php echo $button_html; ?>
        </div>
        <?php
        return;
    }
    ?>
    <div class="card border-0 shadow-sm rounded-4 text-center">
        <div class="card-body p-4 p-lg-5">
            <i class="fa-solid <?php echo e($icon); ?> fa-2x text-gold mb-3" aria-hidden="true"></i>
            <h3 class="h5 mb-2"><?php echo e($title); ?></h3>
            <p class="text-body-secondary mb-0"><?php echo e($text); ?></p>
            <?php if ($button_html !== ''): ?>
                <div class="mt-3"><?php echo $button_html; ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/* =========================================================================
   7. THE TITLE BAND AND BREADCRUMBS
   ========================================================================= */

/**
 * Print the coloured title band at the top of an inner page, with the
 * "Home / Media / Sermon title" trail inside it.
 *
 * Use it instead of writing the same band on every page:
 *   page_header('Sermons', 'Watch and listen to every message.', [['Home', url('')], ['Sermons', url('sermons')]]);
 *
 * The last item in $crumbs is the current page, so it is never a link.
 * Leave $crumbs out when the page has no parent.
 */
function page_header($title, $subtitle = '', $crumbs = [])
{
    ?>
    <section class="bg-brand text-white py-5">
        <div class="container">
            <?php if ($crumbs): ?>
                <nav aria-label="Breadcrumb" class="mb-2">
                    <ol class="breadcrumb small mb-0 brand-band">
                        <?php
                        $last_index = count($crumbs) - 1;
                        foreach ($crumbs as $index => $crumb) {
                            /* A crumb is [label, url]. The ?? on the label is
                               there because the label is the one part a page
                               can get wrong: passing an empty [] for the last
                               crumb used to print "Undefined array key 0" in
                               the middle of the breadcrumb, and nothing about
                               a missing label should ever put a warning on a
                               page. A crumb with no label is simply skipped. */
                            $label = $crumb[0] ?? '';
                            $link = $crumb[1] ?? '';
                            if ($label === '') {
                                continue;
                            }
                            $is_last = ($index === $last_index || $link === '');

                            echo '<li class="breadcrumb-item' . ($is_last ? ' active' : '') . '">';
                            if (!$is_last) {
                                echo '<a href="' . $link . '">' . e($label) . '</a>';
                            } else {
                                echo e($label);
                            }
                            echo '</li>';
                        }
                        ?>
                    </ol>
                </nav>
            <?php endif; ?>

            <h1 class="mb-0"><?php echo e($title); ?></h1>
            <?php if ($subtitle !== ''): ?>
                <p class="lead mb-0 mt-2 col-lg-8"><?php echo e($subtitle); ?></p>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

/* =========================================================================
   8. SHARE BUTTONS
   ========================================================================= */

/**
 * Print the "share this page" row. Copying the link works, and the other
 * buttons open WhatsApp, Facebook or X in a new tab.
 */
function share_bar($title, $link)
{
    ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <span class="small text-uppercase fw-bold text-body-secondary me-1">Share</span>

            <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle"
                    data-copy-link="<?php echo $link; ?>" aria-label="Copy the link to this page">
                <i class="fa-solid fa-link" aria-hidden="true"></i>
            </button>
            <span class="small fw-semibold text-success" data-copy-feedback role="status" aria-live="polite"></span>

            <?php foreach (share_networks($title, $link) as $network => $network_details): ?>
                <a class="btn btn-sm btn-outline-secondary rounded-circle" href="<?php echo e($network_details['url']); ?>"
                   target="_blank" rel="noopener" aria-label="Share on <?php echo e($network); ?>">
                    <i class="<?php echo e($network_details['icon']); ?>" aria-hidden="true"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * Every network the share row offers, as name => array('url' => ..., 'icon' => ...).
 *
 * The address a network is shared to and the icon that stands for it belong in
 * the same entry, because they are the same fact about the same network. Kept
 * as two lists they could quietly disagree - a link added with no icon shows
 * a blank button, and an icon with no link is a button that goes nowhere.
 *
 * Icon classes live here in PHP rather than in the markup on purpose: pages
 * stay icon-agnostic, so changing an icon is a change in this file and not a
 * hunt through every call site.
 */
function share_networks($title, $link)
{
    /* The caller hands over url() output, which is escaped for printing into
       HTML. Percent-encoding an escaped address would bake the escape into the
       link a visitor receives - "&amp;" would go out as "&amp%3B" - so it is
       decoded back to the real address first. A real address carries no
       entities, so this cannot invent any. */
    $encoded_title = rawurlencode($title);
    $encoded_link = rawurlencode(html_entity_decode((string) $link, ENT_QUOTES, 'UTF-8'));

    /* All three are brand icons, so they use the "brands" family rather than
       the normal solid one. */
    return [
        'WhatsApp' => [
            'url'  => 'https://api.whatsapp.com/send?text=' . $encoded_title . '%20' . $encoded_link,
            'icon' => 'fa-brands fa-whatsapp',
        ],
        'Facebook' => [
            'url'  => 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_link,
            'icon' => 'fa-brands fa-facebook-f',
        ],
        'X'        => [
            'url'  => 'https://twitter.com/intent/tweet?text=' . $encoded_title . '&url=' . $encoded_link,
            'icon' => 'fa-brands fa-x-twitter',
        ],
    ];
}

/* =========================================================================
   9. SOCIAL LINKS
   ========================================================================= */

/**
 * All social platforms that have a URL filled in app/config.php.
 * A platform with an empty URL is never shown anywhere on the website.
 */
function social_links()
{
    $links = [
        'Facebook'  => [FACEBOOK_URL, 'fa-brands fa-facebook-f'],
        'YouTube'   => [YOUTUBE_URL, 'fa-brands fa-youtube'],
        'Instagram' => [INSTAGRAM_URL, 'fa-brands fa-instagram'],
        'TikTok'    => [TIKTOK_URL, 'fa-brands fa-tiktok'],
        'WhatsApp'  => [WHATSAPP_URL, 'fa-brands fa-whatsapp'],
    ];

    $available = [];
    foreach ($links as $network => $details) {
        if (trim((string) $details[0]) !== '') {
            $available[$network] = ['url' => $details[0], 'icon' => $details[1]];
        }
    }
    return $available;
}

/* =========================================================================
   10. VERSE OF THE DAY
   ========================================================================= */
/* A small list of verses kept in this file, so the homepage has something
   to show even before any devotional is written. One is picked per day. */

function verse_of_the_day()
{
    $verses = [
        ['Joshua 6:5', 'NKJV', 'And it shall come to pass, when they make a long blast with the ram\u2019s horn, and when you hear the sound of the trumpet, that all the people shall shout with a great shout; then the wall of the city will fall down flat.'],
        ['Psalm 34:1', 'NKJV', 'I will bless the LORD at all times; His praise shall continually be in my mouth.'],
        ['Acts 16:25', 'NKJV', 'And at midnight Paul and Silas were praying and singing hymns of praise to God, and the prisoners were listening to them.'],
        ['Deuteronomy 4:29', 'NKJV', 'You will seek the LORD your God, and you will find Him if you seek Him with all your heart and with all your soul.'],
        ['Psalm 100:4', 'NKJV', 'Enter into His gates with thanksgiving, and into His courts with praise.'],
        ['Isaiah 40:31', 'NKJV', 'But those who wait on the LORD shall renew their strength; they shall mount up with wings like eagles; they shall run and not be weary, they shall walk and not faint.'],
        ['Psalm 46:1', 'NKJV', 'God is our refuge and strength, a very present help in trouble.'],
        ['Philippians 4:6-7', 'NKJV', 'Be anxious for nothing, but in everything by prayer and supplication with thanksgiving let your requests be made known to God.'],
        ['Romans 12:1', 'NKJV', 'Present your bodies a living sacrifice, holy, acceptable to God, which is your spiritual service.'],
        ['Psalm 119:105', 'NKJV', 'Your word is a lamp for my feet and a light for my path.'],
        ['Matthew 6:33', 'NKJV', 'But seek first the kingdom of God and His righteousness, and all these things shall be added to you.'],
        ['Hebrews 12:1', 'NKJV', 'Therefore, since we are receiving a kingdom which cannot be shaken, let us be steadfast, immovable, always abounding in love and good works.'],
        ['Psalm 37:4', 'NKJV', 'Also delight yourself in the LORD, and He shall give you the desires of your heart.'],
        ['1 Corinthians 15:58', 'NKJV', 'Therefore, my beloved brothers, be steadfast, immovable, always abounding in the work of the Lord, knowing that your labor is not in vain in the Lord.'],
        ['Lamentations 3:22-23', 'NKJV', 'It is through the mercies of the LORD that we are not consumed, because His compassions do not run out. They are new every morning; great is Your faithfulness.'],
    ];

    // Pick a verse using the day of the year, so it changes every day.
    $day_of_year = (int) date('z');
    $verse = $verses[$day_of_year % count($verses)];

    return [
        'reference' => $verse[0],
        'version'   => $verse[1],
        'text'      => $verse[2],
    ];
}

/* =========================================================================
   11. IMAGE UPLOADS
   ========================================================================= */

/**
 * The one sentence shown under every picture box on the site.
 *
 * Both sides of the site use it - the dropzone in an admin form and the plain
 * file box on a member's own profile - so the words are written once and the
 * number in them is worked out from MAX_UPLOAD_SIZE. A church secretary has to
 * act on that number, and it must match what upload_image() will actually
 * accept rather than being typed in a second time and left to drift.
 */
function image_hint()
{
    return 'A picture from your phone or camera, up to ' . round(MAX_UPLOAD_SIZE / 1048576) . ' MB.';
}

/**
 * Check that an uploaded file is a real image and store it in uploads.
 *
 * Returns array('path' => 'gallery/abc.jpg', 'error' => '')
 * On failure 'path' is empty and 'error' explains the problem.
 *
 * $file_item is one entry from $_FILES, for example $_FILES['poster'].
 * $folder is the uploads sub folder, for example 'gallery'.
 */
function upload_image($file_item, $folder)
{
    $result = ['path' => '', 'error' => ''];

    // Nothing was uploaded.
    if (!isset($file_item) || $file_item['error'] === UPLOAD_ERR_NO_FILE) {
        return $result;
    }

    if ($file_item['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'The image could not be uploaded. Please try again.';
        return $result;
    }

    if ($file_item['size'] > MAX_UPLOAD_SIZE) {
        $result['error'] = 'That image is too large. The maximum size is ' . round(MAX_UPLOAD_SIZE / 1048576) . ' MB.';
        return $result;
    }

    // Check the real type of the file, not just the name that was sent.
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $file_info = new finfo(FILEINFO_MIME_TYPE);
    $real_mime = $file_info->file($file_item['tmp_name']);

    if (!isset($allowed_types[$real_mime])) {
        $result['error'] = 'Only JPG, PNG and WEBP images are allowed.';
        return $result;
    }

    // Also check the extension, so a double extension like photo.php.jpg fails.
    $original_extension = strtolower(pathinfo($file_item['name'], PATHINFO_EXTENSION));
    if (!in_array($original_extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $result['error'] = 'Only JPG, PNG and WEBP images are allowed.';
        return $result;
    }

    // Build a unique, safe file name. The visitor's file name is never used.
    $new_name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed_types[$real_mime];

    $root_path = dirname(__DIR__) . '/' . UPLOAD_FOLDER . '/' . $folder;
    if (!is_dir($root_path)) {
        mkdir($root_path, 0755, true);
    }

    if (!move_uploaded_file($file_item['tmp_name'], $root_path . '/' . $new_name)) {
        $result['error'] = 'The image could not be saved. Please check the folder permissions.';
        return $result;
    }

    $result['path'] = $folder . '/' . $new_name;
    return $result;
}

/**
 * Delete an uploaded image.
 * The path must come from the database, never straight from a form field.
 */
function delete_uploaded_image($path)
{
    if (!$path) {
        return;
    }

    $uploads_root = realpath(dirname(__DIR__) . '/' . UPLOAD_FOLDER);
    $real_path = realpath(dirname(__DIR__) . '/' . UPLOAD_FOLDER . '/' . ltrim($path, '/'));

    // Only delete files that really sit inside the uploads folder.
    if ($real_path && $uploads_root && strpos($real_path, $uploads_root) === 0 && is_file($real_path)) {
        unlink($real_path);
    }
}

/**
 * Handle the picture on a form: a new file, the "remove" tick, and the old
 * file on disk. The path that goes into the database is the one this returns,
 * so the form field name is never trusted.
 *
 * Returns ['path' => ..., 'error' => ...]. The error is worth showing but does
 * not stop the saving of the words, so a picture problem never loses the rest
 * of the form. Both the admin panel and a person editing their own page use
 * this, so the rule about what a picture may be lives in one place.
 */
function save_image($file_item, $remove_ticked, $folder, $old_path = '')
{
    $result = ['path' => $old_path, 'error' => ''];

    $upload = upload_image($file_item, $folder);
    if ($upload['error'] !== '') {
        $result['error'] = $upload['error'];
        return $result;
    }

    if ($remove_ticked) {
        delete_uploaded_image($old_path);
        $result['path'] = $upload['path'];
        return $result;
    }

    if ($upload['path'] !== '') {
        /* A fresh file replaces the old one, but the old file is only unlinked
           once the new one is safely on disk, so a picture problem never loses
           the one that was already there. */
        if ($old_path !== '' && $old_path !== $upload['path']) {
            delete_uploaded_image($old_path);
        }
        $result['path'] = $upload['path'];
    }

    return $result;
}

/* =========================================================================
   12. YOUTUBE
   ========================================================================= */

/**
 * The embed link for a YouTube video, or '' when the address is not a video.
 *
 * Taking any normal YouTube link and reading the video id out of it is not
 * worth a function of its own: nothing in the project needs the bare id, they
 * all need the address of the frame to put in an <iframe>. So one address that
 * either works or is empty is the only answer there is, and a page asks this
 * twice at most - once to check the editor pasted something real, and once to
 * print the frame.
 *
 * Handles youtu.be links, watch?v= links and /embed/ links.
 */
function youtube_embed_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    $id = '';
    if (strpos($url, 'youtu.be/') !== false) {
        /* The 9, not 8: "youtu.be/" is eight characters but the video id starts
           after the slash as well, and leaving the slash on would make the
           pattern below match nothing at all. */
        $after = substr($url, strpos($url, 'youtu.be/') + 9);
        $id = preg_match('/^([A-Za-z0-9_-]+)/', $after, $matches) ? $matches[1] : '';
    } elseif (strpos($url, '/embed/') !== false) {
        $after = substr($url, strpos($url, '/embed/') + 7);
        $id = preg_match('/^([A-Za-z0-9_-]+)/', $after, $matches) ? $matches[1] : '';
    } elseif (preg_match('/[?&]v=([A-Za-z0-9_-]+)/', $url, $matches)) {
        $id = $matches[1];
    }

    return $id === '' ? '' : 'https://www.youtube.com/embed/' . $id;
}

/* =========================================================================
   13. WEEKLY PROGRAM
   ========================================================================= */

/**
 * Find the next weekly program that has not finished yet.
 * The programs table stores the day number and the times, so the website
 * can work out what happens next on its own.
 */
function next_program($conn)
{
    $current_day = (int) date('w');
    $current_time = date('H:i:s');

    // Look at today first, then the following six days.
    for ($days_ahead = 0; $days_ahead < 7; $days_ahead++) {
        $day_number = ($current_day + $days_ahead) % 7;
        $day = $day_number;

        if ($days_ahead === 0) {
            // Today: only programs that have not started yet.
            $sql = "SELECT * FROM programs
                    WHERE active = 1 AND day_of_week = ? AND start_time >= ?
                    ORDER BY start_time ASC, sort_order ASC
                    LIMIT 1";
            $time = $current_time;
            $statement = $conn->prepare($sql);
            $statement->bind_param('is', $day, $time);
        } else {
            // A later day: the earliest program of that day.
            $sql = "SELECT * FROM programs
                    WHERE active = 1 AND day_of_week = ?
                    ORDER BY start_time ASC, sort_order ASC
                    LIMIT 1";
            $statement = $conn->prepare($sql);
            $statement->bind_param('i', $day);
        }

        $statement->execute();
        $result = $statement->get_result();
        $program = $result->fetch_assoc();
        $statement->close();

        if ($program) {
            if ($days_ahead === 0) {
                $program['day_label'] = 'Today';
            } elseif ($days_ahead === 1) {
                $program['day_label'] = 'Tomorrow';
            } else {
                $program['day_label'] = day_name($day_number);
            }
            return $program;
        }
    }

    return [];
}

/**
 * Read the whole weekly program grouped by day.
 * $grouped['Sunday'] is a list of program rows.
 */
function weekly_program_by_day($conn)
{
    /* The whole program for the week in one read. There is no ? in this
       statement, so a plain query() is allowed here by the same rule as
       db_one() above. */
    $result = $conn->query("SELECT * FROM programs
                            WHERE active = 1
                            ORDER BY day_of_week ASC, start_time ASC, sort_order ASC");

    $grouped = [];
    while ($program = $result->fetch_assoc()) {
        $name = day_name($program['day_of_week']);
        if (!isset($grouped[$name])) {
            $grouped[$name] = [];
        }
        $grouped[$name][] = $program;
    }
    return $grouped;
}

/**
 * The lines of a per-team list, a line each.
 *
 * Two fields on a meeting hold one entry per team and use the same
 * separator, the semicolon: the venue, because the teams of one meeting
 * meet in different places, and the times, because they can meet at
 * different hours too.
 *
 *     Campus Team: The Ginnery; Syaule Team: Online
 *     5:00-5:45 AM (Campus Team); 5:30-6:00 AM (Syaule Team)
 *
 * Splitting them here means a page prints one team's detail per line rather
 * than letting a long sentence run off the side of a narrow card, and there
 * is one rule for the admin to remember when typing either field.
 */
function team_lines($text)
{
    $lines = [];
    foreach (explode(';', (string) $text) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    return $lines;
}

/**
 * The time a meeting prints, as the lines a page shows.
 *
 * Most meetings run from their start to their end, so the one range is the
 * whole answer and this returns nothing: each card shows that range itself, in
 * the pill on the day cards and in the date line on the next-meeting card. A
 * meeting whose two teams meet at different hours returns those
 * hours instead, one line per team, because a single range would then be a
 * sentence that is true of neither team.
 *
 * The lines come back ready to print: the times are text from the database,
 * so they are escaped here, and a page must echo them as they stand rather
 * than escape them a second time.
 */
function program_times($program)
{
    $lines = team_lines($program['times'] ?? '');
    if (!$lines) {
        return [];
    }
    return array_map('e', $lines);
}

/**
 * Every group a person on the website can belong to, in the order the groups
 * are shown.
 *
 * This is the one place the group names live. The category column in the
 * people table, the drop down in the admin form, the filter on the people
 * page and the grouping on the leadership page all read from here, so a new
 * group is added in this function and the column list in database.sql
 * and nowhere else.
 *
 * The first five are the people who hold an office, and they are what the
 * leadership page shows. The last four are the students, the alumni, and the
 * two groups that belong to no directory page at all: 'member' is somebody who
 * registered themselves on register.php, and 'staff' is an account the office
 * made for the website. Those two are kept at the end on purpose, because
 * leader_categories() and the public filters take the first five and so never
 * offer them to a visitor or a leader.
 */
function people_categories()
{
    return [
        'pastor'            => 'Pastor',
        'church_admin'      => 'Church admin',
        'elder'             => 'Elder',
        'department_leader' => 'Department leader',
        'family_leader'     => 'Family leader',
        'student'           => 'Student',
        'alumnus'           => 'Alumnus',
        'member'            => 'Member',
        'staff'             => 'Staff',
    ];
}

/**
 * The groups that appear on the leadership page, in order.
 * It is the first five of people_categories(), kept as its own list so that
 * adding a group like "members" later cannot accidentally make it appear
 * among the pastors.
 */
function leader_categories()
{
    return array_slice(people_categories(), 0, 5, true);
}

/**
 * The groups the people page can be filtered by, in the order the filter
 * boxes are shown: the office holders, the pastors, the alumni.
 *
 * $where_sql is the SQL filter to add, already written out with no placeholders
 * in it, because the group is checked against people_categories() first and so
 * can never be anything a visitor typed.
 */
function people_filters()
{
    $leader_list = "'" . implode("','", array_keys(leader_categories())) . "'";

    return [
        'leadership' => [
            'label'      => 'Leadership',
            'where_sql'  => "category IN ($leader_list)",
        ],
        'pastors' => [
            'label'      => 'Pastors',
            'where_sql'  => "category = 'pastor'",
        ],
        'alumni' => [
            'label'      => 'Alumni',
            'where_sql'  => "category = 'alumnus'",
        ],
    ];
}

/**
 * Read the filter asked for in the address, for example ?group=alumni.
 * Anything that is not one of the real groups - including the plain /people
 * address, which asks for nothing - comes back as 'leadership', so the page
 * always has a group to show and a mistyped or hand-edited address is never a
 * dead end.
 */
function people_filter_from_url()
{
    $asked = isset($_GET['group']) ? trim((string) $_GET['group']) : '';
    return array_key_exists($asked, people_filters()) ? $asked : 'leadership';
}

/**
 * Find one person by their address, for /people/<slug>.
 * Returns an empty array when there is no such person, or when they are hidden.
 */
function person_by_slug($conn, $slug)
{
    /* A 'member' or a 'staff' row is an account, not a page on the website, so
       it is left out even when it happens to be marked as shown. */
    $statement = $conn->prepare(
        "SELECT * FROM people
         WHERE slug = ? AND status = 1 AND category NOT IN ('member', 'staff')
         LIMIT 1"
    );
    $statement->bind_param('s', $slug);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return $row ? $row : [];
}

/**
 * The family and the department a person belongs to, so a page can say where
 * they sit in the church without asking for them.
 *
 * Both are looked up in one go and the family is preferred when they have
 * one, because a student belongs to a family first and a department second.
 * Returns ['family' => row or [], 'department' => row or []].
 */
function person_groups($conn, $person)
{
    $found = ['family' => [], 'department' => []];

    $family_id = (int) ($person['family'] ?? 0);
    if ($family_id > 0) {
        $statement = $conn->prepare("SELECT * FROM families WHERE id = ? AND active = 1 LIMIT 1");
        $statement->bind_param('i', $family_id);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();
        if ($row) {
            $found['family'] = $row;
        }
    }

    $department_id = (int) ($person['department'] ?? 0);
    if ($department_id > 0) {
        $statement = $conn->prepare("SELECT * FROM departments WHERE id = ? AND active = 1 LIMIT 1");
        $statement->bind_param('i', $department_id);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();
        if ($row) {
            $found['department'] = $row;
        }
    }

    return $found;
}

/**
 * Everybody who belongs in one family or one department, hidden people left out
 * so that switching somebody off in the admin panel also quietly removes them
 * from the team list.
 *
 * A family and a department are the same question asked of a different column,
 * so they are one function here rather than two near-identical queries. $group
 * is 'family' or 'department' and is looked up in the list below before the
 * column name is written into the SQL, which is what makes that safe: the name
 * can never be a word a visitor typed.
 *
 * The two callers want different things from it, and the extra arguments are
 * how they say so:
 *
 *   people_in_group($conn, 'family', $id, 12, $person_id)
 *       the rest of one family, to link across from a person's page - capped
 *       at 12, and with the person being shown left out.
 *   people_in_group($conn, 'department', $id)
 *       the whole team of a department, uncapped, because that page is about
 *       the team. A department is listed in the order of the church, office
 *       holders first; a family is listed by name.
 */
function people_in_group($conn, $group, $group_id, $limit = 0, $except_person_id = 0)
{
    $group_columns = ['family' => 'family', 'department' => 'department'];
    if (!isset($group_columns[$group]) || (int) $group_id < 1) {
        return [];
    }

    $group_id = (int) $group_id;
    $except_person_id = (int) $except_person_id;
    $limit = (int) $limit;

    $sql = "SELECT * FROM people
            WHERE `" . $group_columns[$group] . "` = ? AND status = 1";

    $types = 'i';
    $values = [$group_id];

    if ($except_person_id > 0) {
        $sql .= ' AND id <> ?';
        $types .= 'i';
        $values[] = $except_person_id;
    }

    /* The sort is built as one whole clause, because writing the "ORDER BY" in
       only one of the two branches leaves the other branch with a list of sort
       columns and no instruction to sort by them, which MySQL refuses to run.
       On a department page the people are the whole point, and they are read in
       the order of the church: pastors, admins, elders, the two kinds of leader,
       then the students and the alumni. FIELD() spells that list out because
       the category column holds a word, not a number to sort on. A family page
       does not do this, because a family is read by name. */
    if ($group === 'department') {
        $sql .= " ORDER BY FIELD(category, 'pastor','church_admin','elder',
                                      'department_leader','family_leader','student','alumnus'),";
    } else {
        $sql .= ' ORDER BY';
    }
    $sql .= ' display_order ASC, name ASC';

    /* A limit of zero means "the whole list", not "no rows" - MySQL reads
       LIMIT 0 as an instruction to return nothing at all. So the LIMIT is left
       off the statement instead of being sent as a zero. */
    if ($limit > 0) {
        $sql .= ' LIMIT ?';
        $types .= 'i';
        $values[] = $limit;
    }

    $statement = $conn->prepare($sql);
    $statement->bind_param($types, ...$values);
    $statement->execute();
    $result = $statement->get_result();

    $people = [];
    while ($row = $result->fetch_assoc()) {
        $people[] = $row;
    }
    $statement->close();
    return $people;
}

/**
 * The name shown for the person who leads a department or a family.
 *
 * A group may either point at a person in the people table or carry a name
 * typed by hand, for a leader who does not want a page of their own. The
 * person wins when there is one, so the name follows them if it is changed.
 *
 * $group is a departments or families row. Returns
 * array('name' => ..., 'person' => row or [], 'linked' => true/false).
 */
function group_leader($conn, $group)
{
    $person_id = (int) ($group['leader'] ?? 0);

    if ($person_id > 0) {
        $statement = $conn->prepare("SELECT * FROM people WHERE id = ? LIMIT 1");
        $statement->bind_param('i', $person_id);
        $statement->execute();
        $person = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($person) {
            return ['name' => $person['name'], 'person' => $person, 'linked' => true];
        }
    }

    /* Either nothing was chosen, or the person has since been deleted. The
       typed name is still shown so the page never loses the line entirely. */
    return ['name' => (string) ($group['typed'] ?? ''), 'person' => [], 'linked' => false];
}

/**
 * How many students are in a family, for the card on the families list.
 * A prepared statement, because this is asked once per card on the page.
 */
function family_member_count($conn, $family_id)
{
    if ((int) $family_id < 1) {
        return 0;
    }

    $statement = $conn->prepare("SELECT COUNT(*) FROM people WHERE family = ? AND status = 1");
    $family_id = (int) $family_id;
    $statement->bind_param('i', $family_id);
    $statement->execute();
    $row = $statement->get_result()->fetch_row();
    $statement->close();

    return (int) $row[0];
}

/**
 * Every person who could lead a department or a family, as value => label,
 * for the drop down in the admin form. Only people who hold an office or are
 * already a family leader are offered, because those are the people who can
 * actually be asked to lead a group. The first entry is "none", which means
 * "type the name in the box underneath instead".
 */
function leader_choices($conn)
{
    /* Only the five office categories can be offered here. It is not enough to
       rule out students, because an alumnus is a graduate rather than somebody
       holding an office, and offering them here would put an old student in
       charge of a department on the website. */
    $categories = leader_categories();
    $placeholders = implode(', ', array_fill(0, count($categories), '?'));
    $types = str_repeat('s', count($categories));
    $names = array_keys($categories);

    $statement = $conn->prepare("SELECT id, name, category, position FROM people
                                 WHERE status = 1 AND category IN ($placeholders)
                                 ORDER BY display_order ASC, name ASC");
    $statement->bind_param($types, ...$names);
    $statement->execute();
    $result = $statement->get_result();

    $all_categories = people_categories();
    $choices = [];
    while ($row = $result->fetch_assoc()) {
        $title = trim($row['position'] !== '' ? $row['position'] . ' ' . $row['name'] : $row['name']);
        $group = $all_categories[$row['category']] ?? 'Leader';
        $choices[$row['id']] = $group . ' - ' . $title;
    }
    $statement->close();

    return $choices;
}

/* =========================================================================
   14. EVENTS
   ========================================================================= */

/**
 * The nearest events that have not happened yet.
 */
function next_events($conn, $limit = 3)
{
    $sql = "SELECT * FROM events
            WHERE status = 'published' AND start_date >= CURDATE()
            ORDER BY start_date ASC, start_time ASC
            LIMIT ?";

    $statement = $conn->prepare($sql);
    $limit = (int) $limit;
    $statement->bind_param('i', $limit);
    $statement->execute();
    $result = $statement->get_result();

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $statement->close();
    return $events;
}

/* =========================================================================
   13b. THE DAILY DEVOTIONAL AND THE LATEST NOTICES
   ========================================================================= */
/* Three pages need the same two answers - "what should I read today?" and
   "what is the church saying at the moment?" - so the queries live here and
   every page gets exactly the same devotional for the same day. */

/**
 * The devotional a visitor should read now: the most recent published one
 * written for today or for an earlier day. Returns an empty array when
 * nothing has been published yet.
 */
function current_devotional($conn)
{
    $sql = "SELECT * FROM devotionals
            WHERE status = 'published' AND devotional_date <= CURDATE()
            ORDER BY devotional_date DESC
            LIMIT 1";

    $statement = $conn->prepare($sql);
    $statement->execute();
    $result = $statement->get_result();
    $row = $result->fetch_assoc();
    $statement->close();

    return $row ? $row : [];
}

/**
 * The newest published announcements, newest first.
 */
function recent_announcements($conn, $limit = 3)
{
    $sql = "SELECT * FROM announcements
            WHERE status = 'published'
            ORDER BY publish_date DESC, id DESC
            LIMIT ?";

    $statement = $conn->prepare($sql);
    $limit = (int) $limit;
    $statement->bind_param('i', $limit);
    $statement->execute();
    $result = $statement->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $statement->close();
    return $rows;
}

/* =========================================================================
   15. SETTINGS (MAINTENANCE MODE)
   ========================================================================= */
/* Two small key and value rows live in the settings table. They are kept
   in the database because the admin needs to switch them from the browser. */

/**
 * Read one setting. $fallback is returned when the key is not there.
 */
function setting_get($conn, $key, $fallback = '')
{
    $statement = $conn->prepare('SELECT value FROM settings WHERE name = ? LIMIT 1');
    $statement->bind_param('s', $key);
    $statement->execute();
    $result = $statement->get_result();

    if ($result->num_rows === 0) {
        return $fallback;
    }
    $row = $result->fetch_row();
    return $row[0];
}

/**
 * Save one setting, adding it when it is not there yet.
 *
 * Both statements go through prepare() and bind_param() because the key comes
 * straight from a form field on the settings screen, which is exactly the kind
 * of value this project is not allowed to build a query out of.
 */
function setting_set($conn, $key, $value)
{
    $lookup = $conn->prepare('SELECT name FROM settings WHERE name = ? LIMIT 1');
    $lookup->bind_param('s', $key);
    $lookup->execute();
    $result = $lookup->get_result();

    if ($result->num_rows === 0) {
        $insert = $conn->prepare('INSERT INTO settings (name, value) VALUES (?, ?)');
        $insert->bind_param('ss', $key, $value);
        return (bool) $insert->execute();
    }

    $update = $conn->prepare('UPDATE settings SET value = ? WHERE name = ?');
    $update->bind_param('ss', $value, $key);
    return (bool) $update->execute();
}

/**
 * Is the website closed for maintenance?
 */
function is_maintenance_mode($conn)
{
    return setting_get($conn, 'maintenance_mode', '0') === '1';
}

/* =========================================================================
   16. SITE SEARCH
   ========================================================================= */

/**
 * Search the whole website at once.
 * Returns an array of groups, for example:
 *   $results['Devotionals'][] = array('title' => ..., 'url' => ..., 'text' => ...)
 */
function search_site($conn, $search_term, $per_group = 6)
{
    $results = [];
    $term = trim($search_term);
    if (mb_strlen($term) < 2) {
        return $results;
    }

    // The % signs are wildcards for a LIKE search.
    $like = '%' . $term . '%';
    $per_group = (int) $per_group;

    // The type string must match the placeholders exactly and in the same
    // order: one 's' for every LIKE, because the search word is text, and a
    // single 'i' at the end for LIMIT, because the row cap is a number. An
    // 'i' in front of a LIKE would hand MySQL the integer 0, and "LIKE 0"
    // quietly matches nothing at all, so the group looks empty rather than
    // broken.
    $searches = [
        'Devotionals' => [
            "SELECT * FROM devotionals
             WHERE status = 'published'
               AND (title LIKE ? OR message LIKE ? OR scripture_reference LIKE ? OR scripture_text LIKE ?)
             ORDER BY devotional_date DESC LIMIT ?",
            'ssssi',
            [$like, $like, $like, $like, $per_group],
            'devotionals',
        ],
        'Sermons' => [
            "SELECT * FROM sermons
             WHERE status = 'published'
               AND (title LIKE ? OR preacher LIKE ? OR description LIKE ? OR scripture LIKE ?)
             ORDER BY sermon_date DESC LIMIT ?",
            'ssssi',
            [$like, $like, $like, $like, $per_group],
            'sermons',
        ],
        'Events' => [
            "SELECT * FROM events
             WHERE status = 'published'
               AND (title LIKE ? OR theme LIKE ? OR description LIKE ? OR venue LIKE ?)
             ORDER BY start_date DESC LIMIT ?",
            'ssssi',
            [$like, $like, $like, $like, $per_group],
            'events',
        ],
        /* These two are joined to people so that searching for a leader's name
           finds the group they lead, not only a group with that name. The
           LEFT JOIN matters: a group whose leader was never added as a person
           has no matching row, and must still show up in the list. */
        'Departments' => [
            "SELECT departments.*, people.name AS linked_leader
             FROM departments
             LEFT JOIN people ON people.id = departments.leader
             WHERE departments.active = 1
               AND (departments.name LIKE ? OR departments.description LIKE ?
                    OR departments.typed LIKE ? OR people.name LIKE ?)
             ORDER BY departments.name ASC LIMIT ?",
            /* Four words to search, then the one number for the limit. */
            'ssssi',
            [$like, $like, $like, $like, $per_group],
            'departments',
        ],
        'Families' => [
            "SELECT families.*, people.name AS linked_leader
             FROM families
             LEFT JOIN people ON people.id = families.leader
             WHERE families.active = 1
               AND (families.name LIKE ? OR families.description LIKE ?
                    OR families.typed LIKE ? OR people.name LIKE ?)
             ORDER BY families.name ASC LIMIT ?",
            /* Four words to search, then the one number for the limit. */
            'ssssi',
            [$like, $like, $like, $like, $per_group],
            'families',
        ],
        'Announcements' => [
            "SELECT * FROM announcements
             WHERE status = 'published'
               AND (title LIKE ? OR content LIKE ?)
             ORDER BY publish_date DESC LIMIT ?",
            'ssi',
            [$like, $like, $per_group],
            'announcements',
        ],
        'Leadership' => [
            "SELECT * FROM people
             WHERE status = 1
               AND category NOT IN ('member', 'staff')
               AND (name LIKE ? OR position LIKE ? OR bio LIKE ?)
             ORDER BY display_order ASC, name ASC LIMIT ?",
            'sssi',
            [$like, $like, $like, $per_group],
            'people',
        ],
    ];

    foreach ($searches as $group_name => $search) {
        list($sql, $types, $values, $folder) = $search;

        $statement = $conn->prepare($sql);
        $statement->bind_param($types, ...$values);
        $statement->execute();
        $query_result = $statement->get_result();

        while ($row = $query_result->fetch_assoc()) {
            $results[$group_name][] = search_result_row($group_name, $row, $folder);
        }
        $statement->close();
    }

    return $results;
}

/**
 * Turn one database row into a search result that the page can print.
 */
function search_result_row($group_name, $row, $folder)
{
    if ($group_name === 'Devotionals') {
        return [
            'title' => $row['title'],
            'text'  => excerpt($row['message'], 140),
            'meta'  => format_date($row['devotional_date']),
            'url'   => url('devotionals/' . $row['slug']),
        ];
    }

    if ($group_name === 'Sermons') {
        return [
            'title' => $row['title'],
            'text'  => excerpt($row['description'], 140),
            'meta'  => $row['preacher'] . ' - ' . format_date($row['sermon_date']),
            'url'   => url('sermons#sermon-' . $row['id']),
        ];
    }

    if ($group_name === 'Events') {
        return [
            'title' => $row['title'],
            'text'  => excerpt($row['description'], 140),
            'meta'  => event_date_label($row),
            'url'   => url('events/' . $row['slug']),
        ];
    }

    if ($group_name === 'Departments' || $group_name === 'Families') {
        /* The linked person is preferred over the typed name, for the same
           reason the public pages prefer it. */
        $leader = !empty($row['linked_leader']) ? $row['linked_leader'] : $row['leader'];

        return [
            'title' => $row['name'],
            'text'  => excerpt($row['description'], 140),
            'meta'  => $leader !== '' ? 'Led by ' . $leader : '',
            'url'   => url($folder . '/' . $row['slug']),
        ];
    }

    if ($group_name === 'Announcements') {
        return [
            'title' => $row['title'],
            'text'  => excerpt($row['content'], 140),
            'meta'  => format_date($row['publish_date']),
            'url'   => url('announcements#announcement-' . $row['id']),
        ];
    }

    /* The people table covers the pastors, the students and the alumni, so
       the line under a result says which group that person is in rather than
       repeating their job title. */
    $categories = people_categories();
    $group = $categories[$row['category']] ?? '';
    $meta = trim($group . ' ' . (string) ($row['position'] ?? ''));

    return [
        'title' => $row['name'],
        'text'  => excerpt($row['bio'] ?: $row['testimony'], 140),
        'meta'  => $meta,
        'url'   => url('people/' . $row['slug']),
    ];
}

/* =========================================================================
   17. PASSWORD RULES
   ========================================================================= */

/**
 * Explain what is wrong with a password, for showing under a form.
 * Returns '' when the password is fine.
 *
 * This is the only password check in the project. A page that wants to know
 * whether a password is acceptable asks this and tests the answer for '', so
 * there is never a second "is it strong?" test that could disagree with the
 * message the visitor is actually shown.
 */
function password_problem($password)
{
    if (strlen((string) $password) < MIN_PASSWORD_LENGTH) {
        return 'The password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long.';
    }
    if (preg_match('/[A-Za-z]/', (string) $password) !== 1) {
        return 'The password must contain at least one letter.';
    }
    if (preg_match('/[0-9]/', (string) $password) !== 1) {
        return 'The password must contain at least one number.';
    }
    return '';
}

/* =========================================================================
   18. SMALL DISPLAY HELPERS
   ========================================================================= */

/**
 * A person's circle: their photograph, or their initials when there is none.
 *
 * $size is the diameter in pixels and it sets both versions, so a row of
 * people keeps one shape however many of them have a photograph - which is
 * the rule the pages already followed by hand before this helper existed.
 * $extra carries what depends on where the circle sits: 'flex-shrink-0'
 * beside a name, 'mx-auto mb-3 fs-2' in the middle of a card. The same
 * classes go on both versions for the same reason as the size.
 *
 * The image is always decorative (alt="") because the person's name is
 * printed next to it everywhere this is used; an alt text as well would
 * make a screen reader say the name twice. The initials are aria-hidden
 * for the same reason.
 */
function avatar($image, $name, $size = 48, $extra = 'flex-shrink-0')
{
    $size = (int) $size;

    if (!empty($image)) {
        return '<img src="' . upload_url($image) . '" alt="" width="' . $size . '" height="' . $size
            . '" class="rounded-circle object-fit-cover ' . e($extra) . '">';
    }

    /* The letters are a third of the circle, which is 1.5rem on the 72px
       circle this site started with - the size the old inline style said. */
    return '<span class="brand-mark bg-brand-soft text-brand rounded-circle d-inline-flex align-items-center justify-content-center '
        . e($extra) . '" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . round($size / 3) . 'px"'
        . ' aria-hidden="true">' . initials($name) . '</span>';
}

/**
 * Print one devotional as a full reading page.
 *
 * Both /devotional (today's message) and /devotionals/<slug> (one from the
 * archive) show exactly the same thing, so the markup lives here once.
 *
 * $link is the address the share buttons copy. It is left out when the
 * message is being shown inside a list rather than as a page.
 */
function render_devotional($devotional, $link = '')
{
    ?>
    <div class="reading-progress" id="reading-progress" aria-hidden="true"></div>

    <article class="devotional-article" id="devotional-article">
        <p class="devotional-brand mb-2">
            Devotional for <?php echo format_date($devotional['devotional_date'], 'l, j F Y'); ?>
        </p>

        <?php if (!empty($devotional['scripture_text']) || !empty($devotional['scripture_reference'])): ?>
            <div class="card border-0 border-start border-4 border-warning bg-warning-subtle rounded-4 my-4">
                <div class="card-body">
                    <?php if (!empty($devotional['scripture_text'])): ?>
                        <p class="fst-italic mb-2">&ldquo;<?php echo e($devotional['scripture_text']); ?>&rdquo;</p>
                    <?php endif; ?>
                    <?php if (!empty($devotional['scripture_reference'])): ?>
                        <p class="small fw-bold mb-0 text-gold-dark"><?php echo e($devotional['scripture_reference']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php echo paragraphs($devotional['message']); ?>

        <?php if (!empty($devotional['further_study'])): ?>
            <h2 class="h4 mt-5">Further study</h2>
            <?php echo line_list($devotional['further_study'], 'list-unstyled d-grid gap-2 small text-body-secondary'); ?>
        <?php endif; ?>

        <?php if ($link !== ''): ?>
            <div class="mt-5"><?php share_bar($devotional['title'], $link); ?></div>
        <?php endif; ?>
    </article>
    <?php
}

/**
 * A coloured label for a status, used in the admin tables and on cards.
 */
function status_badge($status, $active_flag = null)
{
    if ($active_flag !== null) {
        return (int) $active_flag === 1
            ? '<span class="badge rounded-pill text-bg-success">Active</span>'
            : '<span class="badge rounded-pill text-bg-secondary">Hidden</span>';
    }

    if ($status === 'published') {
        return '<span class="badge rounded-pill text-bg-success">Published</span>';
    }
    if ($status === 'draft') {
        return '<span class="badge rounded-pill text-bg-secondary">Draft</span>';
    }
    return '<span class="badge rounded-pill text-bg-secondary">' . e($status) . '</span>';
}

/**
 * What one website account is called, in words a church member would use.
 *
 * A word here is a website job, not a church office. 'Administrator' is the
 * one full role: a website or a church administrator, both with the same
 * rights, who may open the users and the settings as well as the content.
 * An editor writes the content; a pastor and a church leader are named for
 * the office they hold and, like an editor, may write the content and
 * nothing more. A member is a church member who signed themselves up on
 * register.php.
 *
 * This is the wording for the role column in the users table, and it is not
 * the same word as the group a person belongs to on the people table. That
 * one says where somebody stands in the church and gives them a page of
 * their own; this one says what one sign-in may do here. A person can hold a
 * church office and still be only an editor here, or hold no office and be
 * an administrator.
 *
 * staff_roles() in app/auth.php is the list of the roles that get into the
 * admin panel. This is the list of what to call them, and it carries every
 * value the role column can hold, so a role the panel turns away still has a
 * name on the users screen.
 */
function user_role_label($role)
{
    $labels = [
        'admin'  => 'Administrator',
        'editor' => 'Editor',
        'pastor' => 'Pastor',
        'leader' => 'Church leader',
        'member' => 'Church member',
    ];

    return $labels[$role] ?? 'Unknown';
}

/**
 * The three ways a person can register on the website, in the order the form
 * offers them. A member and an alumnus are an ordinary account that may sign in
 * at once; a leader is put on the same account but waits for an administrator
 * to switch it on. The wording lives here so the register form and the users
 * list cannot drift apart.
 */
function standing_labels()
{
    return [
        'member'  => 'Member',
        'alumnus' => 'Alumni',
        'leader'  => 'Leader',
    ];
}

/**
 * The file that serves the add/change form for an admin module.
 *
 * Every admin screen is one flat file in /dashboard/admin, and a module that has
 * both a list and a form needs two of them. The list keeps the module's own
 * plural name (events.php) and the form is named after the single thing it edits
 * (add-event.php), which is how a reader tells the two apart in the folder.
 *
 * This list is the only place that pairing is written down. admin_url() reads it,
 * so a caller keeps saying admin_url('events/form.php') and never has to know
 * what the file was renamed to.
 *
 * A module with no entry here has no form - leaders, logs, subscribers and
 * visitors are read-only screens - so asking for one is a mistake worth showing
 * rather than hiding.
 */
function admin_form_file($module)
{
    static $forms = [
        'announcements' => 'add-announcement',
        'departments'   => 'add-department',
        'devotionals'   => 'add-devotional',
        'events'        => 'add-event',
        'families'      => 'add-family',
        'gallery'       => 'add-gallery-item',
        'people'        => 'add-person',
        'programs'      => 'add-program',
        'sermons'       => 'add-sermon',
        'users'         => 'add-user',
    ];

    return $forms[$module] ?? '';
}

/**
 * The page link for an admin screen, for example /dashboard/admin/events
 *
 * Callers name a screen the way it has always been named - a module, or a module
 * with the form or view file it wants - and this works out where that screen now
 * lives. Keeping the translation in one place is what let the admin panel move
 * out of /admin and into /dashboard/admin without touching the hundred-odd links
 * that point into it.
 *
 * Every screen gets a short address with no file name; .htaccess rewrites it onto
 * the .php file. A form screen is named after the single thing it edits, so
 * 'events/form.php' becomes /dashboard/admin/add-event.
 */
function admin_url($module = '')
{
    $module = ltrim($module, '/');
    if ($module === '') {
        return url('dashboard/admin');
    }

    /* The query string belongs to the caller, not to the screen name, so it is
       taken off first and put back on the end untouched. */
    $parts = explode('?', $module, 2);
    $screen = $parts[0];
    $query = isset($parts[1]) ? '?' . $parts[1] : '';

    /* 'events/form.php' is the add/change screen for the events list, and
       'messages/view.php' is the one place a row is read on its own. Both are
       separate files, so both are looked up by name. */
    $file = '';
    if (substr($screen, -9) === '/form.php') {
        $file = admin_form_file(substr($screen, 0, -9));
    } elseif (substr($screen, -9) === '/view.php') {
        $file = 'message-view';
    }

    /* A form.php for a module that has no form keeps its literal name. That
       address does not exist, which is the point: the mistake is visible in the
       address bar instead of quietly landing on the wrong screen. */
    if ($file !== '') {
        return url('dashboard/admin/' . $file . $query);
    }

    return url('dashboard/admin/' . $screen . $query);
}

/* =========================================================================
   19. MENU HELPERS
   ========================================================================= */

/**
 * Is this the menu item for the page being viewed?
 * $keys is the first part of the path, for example 'events'.
 */
function nav_active($keys)
{
    $path = current_path();
    $first_part = explode('/', $path)[0];

    if ($keys === 'home') {
        return $path === '';
    }
    return $first_part === $keys;
}

/* =========================================================================
   20. THE ACTIVITY LOG
   ========================================================================= */
/* One row per interesting thing that happened, written by log_action(), so
   the admin can answer "who changed that?" and "did somebody try to sign in
   from a strange address?" without the website having to be watched. */

/**
 * Write one line to the logs table.
 *
 * $action is the short machine name of the event, for example
 * 'event_created', and it is what /dashboard/admin/logs groups by. $details is the
 * finished English sentence shown on that screen, so the caller builds it
 * with sprintf() and the screen never has to look a key up in a table.
 *
 * The person is whoever is signed in, and 0 when nobody is, which is what
 * makes "a visitor did this" honest rather than a guess. The address comes
 * from ip(), which has already worked out the real one.
 *
 * This must never break the page that called it: a full disk should not stop
 * a devotional being saved. So a failure here is ignored on purpose.
 */
function log_action($conn, $action, $details = '')
{
    if (!defined('BUBAPC_LOGS') || !BUBAPC_LOGS) {
        return;
    }

    $user = current_user();
    $actor = (int) ($user['id'] ?? 0);
    $action = substr(trim((string) $action), 0, 60);
    $details = substr((string) $details, 0, 255);
    $ip = ip();

    $statement = $conn->prepare(
        'INSERT INTO logs (actor, action, details, ip) VALUES (?, ?, ?, ?)'
    );
    $statement->bind_param('isss', $actor, $action, $details, $ip);
    $statement->execute();
    $statement->close();
}

/**
 * Turn a machine name like 'event_created' into words, for a heading or a
 * filter button. Returns the name with the underscores turned into spaces
 * when there is no nicer wording, so a new action never shows a blank.
 */
function log_action_label($action)
{
    $nicer = [
        'user_signed_in'    => 'signed in',
        'user_signed_out'   => 'signed out',
        'user_created'      => 'added a user',
        'user_updated'      => 'changed a user',
        'user_switched_off' => 'switched off a user',
        'password_reset'    => 'asked for a password reset',
        'password_changed'  => 'changed a password',
        'login_refused'     => 'failed to sign in',
        'event_created'     => 'added an event',
        'event_updated'     => 'changed an event',
        'event_deleted'     => 'deleted an event',
        'sermon_created'    => 'added a sermon',
        'sermon_updated'    => 'changed a sermon',
        'sermon_deleted'    => 'deleted a sermon',
        'devotional_created' => 'added a devotional',
        'devotional_updated' => 'changed a devotional',
        'devotional_deleted' => 'deleted a devotional',
        'announcement_created' => 'added an announcement',
        'announcement_updated' => 'changed an announcement',
        'announcement_deleted' => 'deleted an announcement',
        'person_created'    => 'added a person',
        'person_updated'    => 'changed a person',
        'person_deleted'    => 'deleted a person',
        'department_created' => 'added a department',
        'department_updated' => 'changed a department',
        'department_deleted' => 'deleted a department',
        'family_created'    => 'added a family',
        'family_updated'    => 'changed a family',
        'family_deleted'    => 'deleted a family',
        'photo_created'     => 'added a photo',
        'photo_updated'     => 'changed a photo',
        'photo_deleted'     => 'deleted a photo',
        'program_created'   => 'added a programme',
        'program_updated'   => 'changed a programme',
        'program_deleted'   => 'deleted a programme',
        'contact_received'  => 'sent a contact message',
        'devotional_added'  => 'joined the devotional list',
        'newsletter_added'  => 'joined the devotional list',
        'settings_changed'  => 'changed the website settings',
        'log_cleared'       => 'cleared the activity log',
    ];

    if (isset($nicer[$action])) {
        return e($nicer[$action]);
    }

    /* The fallback prints whatever was in the log's action column, which for an
       action this list has never heard of is a raw database string - so the
       escaping belongs here, where the label is made, rather than at the print
       site. */
    return e(ucfirst(str_replace('_', ' ', (string) $action)));
}

/**
 * The Bootstrap colour of the small label that goes with an event name.
 *
 * The dashboard and the activity log both show a log line as a coloured pill,
 * and the colour has to mean the same thing in both places, so the choice is
 * made from the words in the event name rather than written at each call site.
 * Anything that is not recognised is grey, which is the honest answer for an
 * event nobody has thought about yet.
 */
function log_action_tone($action)
{
    $action = (string) $action;

    /* The two words that decide almost everything: something was removed, or
       somebody was turned away. Both are the ones worth seeing across a room. */
    if (strpos($action, 'deleted') !== false || strpos($action, 'cleared') !== false
        || strpos($action, 'switched_off') !== false) {
        return 'danger';
    }
    if (strpos($action, 'refused') !== false) {
        return 'warning';
    }

    /* The quiet, ordinary half of the log. */
    if (strpos($action, 'created') !== false || strpos($action, 'added') !== false
        || strpos($action, 'joined') !== false || strpos($action, 'received') !== false) {
        return 'success';
    }
    if (strpos($action, 'signed_in') !== false) {
        return 'info';
    }
    if (strpos($action, 'updated') !== false || strpos($action, 'changed') !== false
        || strpos($action, 'signed_out') !== false) {
        return 'primary';
    }

    return 'secondary';
}

/* =========================================================================
   21. WHO LOOKED AT THE WEBSITE
   ========================================================================= */
/* A rough count of browsers that reached the site, and nothing more. There is
   no cookie and no identifier that could follow anybody anywhere: the address
   is hashed before it is stored, and a browser with cookies blocked counts the
   same as one without, which is the honest answer for a number like this. */

/**
 * The one line that stands for a visitor, hashed.
 *
 * It is the address plus the browser string, which keeps two offices on one
 * cable apart and two phones on one address apart. It is hashed with sha256,
 * so the row cannot be turned back into an address by reading the table, and
 * an empty address still produces a usable key rather than an error.
 */
function visitor_key()
{
    $address = ip();
    $browser = agent();

    if ($address === '') {
        $address = 'unknown';
    }

    return hash('sha256', $address . '|' . $browser);
}

/**
 * Note that this page was looked at, and keep the number of days in which
 * that happened up to date.
 *
 * $path is the address being viewed. One row is written the first time a
 * browser is seen and the row is then touched rather than duplicated, so the
 * table stays small enough to read on a phone. A visitor seen again on the
 * SAME day only moves `seen`; a new day adds one to `visits`.
 *
 * It is safe to call this on every page: it is one indexed update, and every
 * error is swallowed, because nothing a visitor is looking at may depend on
 * whether a counter moved.
 */
function record_visit($conn, $path = '')
{
    if (defined('BUBAPC_INSTALLER') || defined('BUBAPC_SKIP_VISITS')) {
        return;
    }

    $key = visitor_key();
    $browser = agent();
    $referer = referer();
    $path = substr((string) $path, 0, 190);

    try {
        $statement = $conn->prepare('SELECT id, seen, visits FROM visitors WHERE ip = ? LIMIT 1');
        $statement->bind_param('s', $key);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($row === null) {
            $statement = $conn->prepare(
                'INSERT INTO visitors (ip, agent, path, referer, visits, seen)
                 VALUES (?, ?, ?, ?, 1, NOW())'
            );
            $statement->bind_param('ssss', $key, $browser, $path, $referer);
            $statement->execute();
            $statement->close();
            return;
        }

        /* A browser seen again today is not a second visit, so the counter is only
           moved when the day changes. That is why the screen can say "12
           people today" and mean it.

           Which day it is has to be decided by the DATABASE, in the same
           UPDATE that saves the visit. Asking PHP instead would compare
           against a different clock: PHP runs in UTC here while MariaDB answers
           in local time, so between midnight and 3am every visitor would
           count as a new day, twice over. */
        /* The counter is written BEFORE `seen` on purpose. MariaDB carries out
           the assignments left to right and uses the new value of a column it
           has already set, so with seen = NOW() first, DATE(seen) would always
           be today and the counter would never move. */
        $statement = $conn->prepare(
            'UPDATE visitors
             SET visits = visits + IF(DATE(seen) = CURDATE(), 0, 1),
                 seen = NOW(),
                 path = ?,
                 agent = ?
             WHERE id = ?'
        );
        $id = (int) $row['id'];
        $statement->bind_param('ssi', $path, $browser, $id);
        $statement->execute();
        $statement->close();
    } catch (Throwable $problem) {
        /* Throwable, not just a database exception, because the mistake that
           actually happens here is a mistyped bind_param() type string, and
           that raises an ordinary PHP error rather than a database one. The
           page must still be shown either way. */
    }
}

/**
 * Delete visitor rows older than VISITOR_KEEP_DAYS, so the table cannot grow
 * without end. Called by cron-or-by-hand; it deletes nothing the admin is
 * still reading, because anything that old is noise.
 */
function visitor_sweep($conn)
{
    $statement = $conn->prepare(
        'DELETE FROM visitors WHERE seen < DATE_SUB(NOW(), INTERVAL ? DAY)'
    );
    $days = (int) VISITOR_KEEP_DAYS;
    $statement->bind_param('i', $days);
    $statement->execute();
    $removed = $statement->affected_rows;
    $statement->close();

    return (int) $removed;
}

/**
 * The two numbers the dashboard shows: how many browsers were here today,
 * and how many were here in the last seven days. Both are read from the
 * table, so neither can be out of date.
 */
function visitor_counts($conn)
{
    $today = db_one(
        $conn,
        "SELECT COUNT(*) AS total FROM visitors
         WHERE seen >= DATE_SUB(CURDATE(), INTERVAL 0 DAY)
           AND seen <  DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
    );
    $week = db_one(
        $conn,
        "SELECT COUNT(*) AS total FROM visitors
         WHERE seen >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
           AND seen <  DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
    );

    return [
        'today' => (int) ($today['total'] ?? 0),
        'week'  => (int) ($week['total'] ?? 0),
    ];
}

