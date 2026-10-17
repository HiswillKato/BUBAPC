<?php
/**
 * BUBAPC - Small admin building blocks
 *
 * The admin modules all look the same: a filter bar, a table of rows with
 * Edit, Toggle and Delete buttons, a pagination bar and an image picker.
 * Instead of copying that markup into every module, the parts live in the
 * functions below and the page just calls them.
 *
 * A page sets these before it includes the shell:
 *   $active_module = 'events';
 *   $page_title    = 'Events';
 *   $page_heading  = 'Events';
 *   $page_actions  = '<a class="btn btn-primary" href="...">Add event</a>';
 */

/* -------------------------------------------------------------------------
   0. THE GATE
   ---------------------------------------------------------------------- */

/* This file is the first thing every page in the admin panel loads, and it is
   loaded *before* the page handles a POST, so this is where the admin panel
   decides who is allowed in. app/includes/admin/header.php asks the same question again
   before it draws anything, because that catches a page that forgot this file.

   Two checks that look like belt and braces, because the one that is easy to
   forget is the one that matters. A church member who signed up on
   register.php is a real account that can sign in, so require_login() is not
   enough of a test for "may manage this website" - the page's saving and
   deleting would run for a member before the shell was ever reached.

   Pages therefore do not call require_login() themselves, and this line is the
   reason they do not need to. If you write a new admin page, load
   admin-parts.php (or admin-header.php) and you are covered.
 */
require_staff();

/* -------------------------------------------------------------------------
   0b. THE MENU IN THE SIDEBAR
   ---------------------------------------------------------------------- */

/**
 * The links in the admin sidebar, in the order they are shown.
 *
 * The header draws a small heading whenever the group changes, so the rows
 * are kept in group order. Anything past the header's own Account row goes
 * here too, because a new screen that is not in this list can only be reached
 * by remembering its address.
 *
 * The key is the module name the page sets as $active_module, so the row for
 * the page you are on is highlighted. admin_only rows are hidden from an
 * editor who is staff but not an administrator.
 */
function admin_menu()
{
    $dashboard = ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'url' => admin_url()];
    $content = [
        ['key' => 'sermons', 'label' => 'Sermons', 'icon' => 'fa-church'],
        ['key' => 'devotionals', 'label' => 'Devotionals', 'icon' => 'fa-scroll'],
        ['key' => 'events', 'label' => 'Events', 'icon' => 'fa-calendar-days'],
        ['key' => 'programs', 'label' => 'Programs', 'icon' => 'fa-list-check'],
        ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'fa-bullhorn'],
        ['key' => 'gallery', 'label' => 'Gallery', 'icon' => 'fa-images'],
    ];
    $church = [
        ['key' => 'people', 'label' => 'People', 'icon' => 'fa-users'],
        ['key' => 'departments', 'label' => 'Departments', 'icon' => 'fa-sitemap'],
        ['key' => 'families', 'label' => 'Families', 'icon' => 'fa-people-roof'],
    ];
    $inbox = [
        ['key' => 'messages', 'label' => 'Messages', 'icon' => 'fa-envelope'],
        ['key' => 'subscribers', 'label' => 'Devotional list', 'icon' => 'fa-paper-plane'],
        ['key' => 'visitors', 'label' => 'Visitors', 'icon' => 'fa-binoculars'],
    ];
    $system = [
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'fa-gears', 'admin_only' => true],
        ['key' => 'logs', 'label' => 'Activity log', 'icon' => 'fa-clock-rotate-left'],
    ];

    $menu = [$dashboard];
    foreach (['Content' => $content, 'Church' => $church, 'Inbox' => $inbox, 'System' => $system] as $group => $rows) {
        foreach ($rows as $row) {
            $row['group'] = $group;
            $row['url'] = admin_url($row['key']);
            $menu[] = $row;
        }
    }

    return $menu;
}

/* -------------------------------------------------------------------------
   1. THE BUTTONS IN A TABLE ROW
   ---------------------------------------------------------------------- */

/**
 * A small Edit button for one row.
 */
function admin_edit_button($module, $id)
{
    return '<a class="btn btn-sm btn-soft" href="' . admin_url($module . '/form.php?id=' . (int) $id) . '">'
        . '<i class="fa-solid fa-pen" aria-hidden="true"></i><span class="visually-hidden">Edit</span></a>';
}

/**
 * A Delete button. It is a POST form with a CSRF token, never a link, and
 * the browser asks the admin to confirm first.
 */
function admin_delete_button($module, $id, $record_label = 'this record')
{
    $message = 'Delete ' . $record_label . '? This cannot be undone.';
    if ($record_label === 'this record') {
        $message = 'Delete this record? This cannot be undone.';
    }

    return '<form method="post" action="' . admin_url($module) . '" class="d-inline">'
        . csrf_field()
        . '<input type="hidden" name="action" value="delete">'
        . '<input type="hidden" name="id" value="' . (int) $id . '">'
        . '<button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="' . e($message) . '">'
        . '<i class="fa-solid fa-trash" aria-hidden="true"></i><span class="visually-hidden">Delete</span></button>'
        . '</form>';
}

/**
 * A button that switches a record on or off without opening the form.
 *
 * $current      the value that is in the database right now. A number for the
 *               is_active columns, or the word 'published' when $kind is
 *               'status'.
 * $kind         'active' posts action=toggle and flips an is_active column.
 *               'status' posts action=toggle_status and swaps published and
 *               draft. Both are the same button over the same form, so they are
 *               the same function; only the words the module's list page is
 *               listening for differ.
 */
function admin_toggle_button($module, $id, $current, $turn_on_label = 'Show', $turn_off_label = 'Hide', $kind = 'active')
{
    $is_status = $kind === 'status';
    $turn_on = $is_status ? $current === 'published' : (int) $current === 1;

    $label = $turn_on ? $turn_off_label : $turn_on_label;
    $icon = $turn_on ? 'fa-eye-slash' : ($is_status ? 'fa-paper-plane' : 'fa-eye');
    $class = $turn_on ? 'btn-outline-secondary' : 'btn-soft';

    return '<form method="post" action="' . admin_url($module) . '" class="d-inline">'
        . csrf_field()
        . '<input type="hidden" name="action" value="' . ($is_status ? 'toggle_status' : 'toggle') . '">'
        . '<input type="hidden" name="id" value="' . (int) $id . '">'
        . '<button type="submit" class="btn btn-sm ' . e($class) . '">'
        . '<i class="fa-solid ' . e($icon) . '" aria-hidden="true"></i> ' . e($label) . '</button>'
        . '</form>';
}

/**
 * Put a row's buttons in a spaced-out line and push them to the right.
 *
 * Each button function returns a bare button, and two bare buttons printed one
 * after another have nothing between them, so they sit flush against each other
 * and read as one lumpy shape. A flex row with a gap is the same answer
 * Bootstrap gives .btn-group. admin_row_actions() calls this for you; a page
 * that builds its own row of buttons - the messages, subscribers and users
 * screens - calls it round its own markup so it cannot forget.
 *
 * $justify is for the few places that are not a right-aligned table cell, such
 * as a button on its own line inside a card. It takes a Bootstrap justify
 * class, so it stays 'justify-content-end' (the table default) everywhere else.
 */
function admin_action_group($buttons, $justify = 'justify-content-end')
{
    return '<div class="d-flex gap-2 ' . e($justify) . '">' . $buttons . '</div>';
}

/**
 * The Edit, Toggle and Delete buttons of one row, in one group.
 * $options lets a module leave out the toggle button when it has no status.
 *
 * The 'status' and 'active' keys are the two different things a module can
 * ask to be flipped, and which one is meant is decided by the page when it
 * builds the table rather than by a function per module.
 */
function admin_row_actions($module, $id, $options = [])
{
    $actions = admin_edit_button($module, $id);

    if (array_key_exists('status', $options)) {
        $actions .= admin_toggle_button($module, $id, $options['status'], 'Publish', 'Unpublish', 'status');
    } elseif (array_key_exists('active', $options)) {
        $actions .= admin_toggle_button($module, $id, $options['active']);
    }

    $actions .= admin_delete_button($module, $id, $options['label'] ?? 'this record');

    return admin_action_group($actions);
}

/* -------------------------------------------------------------------------
   2. THE FILTER BAR ABOVE A TABLE
   ---------------------------------------------------------------------- */

/**
 * Print a search box and a status drop down for an admin list page.
 *
 * $filter_action  where the form goes, for example admin_url('events')
 * $filter_q       the search text that is already in the URL
 * $filter_status  the status that is already in the URL
 * $status_options  array of value => label, for example ['published' => 'Published']
 */
function admin_filter_bar($filter_action, $filter_q = '', $filter_status = '', $status_options = [])
{
    $any_option = '<option value="">All</option>';
    ?>
    <form class="row g-2 align-items-center mb-3" method="get" action="<?php echo e($filter_action); ?>" role="search">
        <div class="col-12 col-md">
            <label class="visually-hidden" for="filter-search">Search</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                <input type="search" class="form-control" id="filter-search" name="q"
                       value="<?php echo e($filter_q); ?>" placeholder="Search" maxlength="100">
            </div>
        </div>

        <?php if ($status_options): ?>
            <div class="col-12 col-md-auto">
                <label class="visually-hidden" for="filter-status">Status</label>
                <select class="form-select" id="filter-status" name="status" data-auto-submit>
                    <?php echo $any_option; ?>
                    <?php foreach ($status_options as $value => $label): ?>
                        <option value="<?php echo e($value); ?>" <?php echo $filter_status === $value ? 'selected' : ''; ?>>
                            <?php echo e($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="col-12 col-md-auto d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($filter_q !== '' || $filter_status !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?php echo e($filter_action); ?>">Clear</a>
            <?php endif; ?>
        </div>
    </form>
    <?php
}

/* -------------------------------------------------------------------------
   3. THE TABLE AND THE PAGINATION BAR
   ---------------------------------------------------------------------- */

/**
 * Print the "Showing 1 to 10 of 40" line and the page buttons.
 *
 * The line of numbers is printed by render_result_count() in app/functions.php,
 * the same one the public lists use - only the words for an empty list differ,
 * so there is one piece of wording to keep right. This wrapper exists because
 * the two always travel together on an admin page, and writing two calls in
 * eleven modules would be a chance to forget the second one.
 */
function admin_result_bar($pagination, $query = [], $label = 'records')
{
    ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <?php render_result_count($pagination, $label, 'No ' . $label . ' found.'); ?>
    </div>
    <?php
    render_pagination($pagination, $query);
}

/* -------------------------------------------------------------------------
   4. FORM PARTS
   ---------------------------------------------------------------------- */

/**
 * Print the Save and Cancel buttons at the bottom of an admin form.
 */
function admin_form_buttons($cancel_url, $save_label = 'Save')
{
    ?>
    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> <?php echo e($save_label); ?>
        </button>
        <a class="btn btn-outline-secondary" href="<?php echo e($cancel_url); ?>">Cancel</a>
    </div>
    <?php
}

/**
 * Print the image upload part of an admin form.
 *
 * $field_name   the name of the file input, for example 'poster'
 * $label        the words shown above the box
 * $current      the image already saved in the database, or ''
 * $hint         small text under the box, or null for image_hint()
 */
function admin_image_field($field_name, $label, $current = '', $hint = null)
{
    $hint = $hint ?? image_hint();
    ?>
    <div class="mb-3">
        <label class="form-label" for="<?php echo e($field_name); ?>"><?php echo e($label); ?></label>

        <?php if ($current): ?>
            <div class="d-flex align-items-center gap-3 p-2 border rounded-3 mb-2 bg-body-tertiary">
                <img src="<?php echo upload_url($current); ?>" alt="" width="84" height="84"
                     class="rounded-3 object-fit-cover">
                <span class="small text-body-secondary">This is the image that is saved now. Choosing a new file replaces it.</span>
            </div>
        <?php endif; ?>

        <div class="dropzone" data-dropzone>
            <input class="visually-hidden" type="file" id="<?php echo e($field_name); ?>"
                   name="<?php echo e($field_name); ?>" accept="image/jpeg,image/png,image/webp">
            <div class="dropzone-picker">
                <i class="fa-solid fa-cloud-arrow-up dropzone-icon" aria-hidden="true"></i>
                <p class="fw-semibold mb-1">Click to choose, or drop an image here</p>
                <p class="small text-body-secondary mb-0"><?php echo e($hint); ?></p>
            </div>
            <div class="dropzone-preview">
                <img src="" alt="Preview of the chosen image">
                <p class="small mt-2 mb-0">
                    <button type="button" class="btn btn-sm btn-outline-danger dropzone-remove">
                        <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> Remove
                    </button>
                </p>
            </div>
        </div>

        <?php if ($current): ?>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="<?php echo e($field_name); ?>-remove">
                <label class="form-check-label small" for="<?php echo e($field_name); ?>-remove">Remove this image when saving</label>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/* -------------------------------------------------------------------------
   5. FORM FIELDS
   ---------------------------------------------------------------------- */

/**
 * Print the list of things that went wrong, at the top of a form.
 */
function admin_errors($errors)
{
    form_errors($errors);
}

/**
 * The frame every labelled form control sits in: the words above it, the
 * control itself, and the small grey line under it.
 *
 * All the admin fields used to write this same twelve lines out for themselves.
 * They still do, but through here, so a change to how a field is spaced or how
 * the help line behaves is one edit rather than six - and, more to the point, so
 * a field cannot end up with a help line that is missing, or attached to the
 * wrong control, without anybody noticing.
 *
 * $control_html is already-built HTML for the control. It is echoed as it
 * stands and never escaped, because every field below builds it from its own
 * name and value through e().
 */
function admin_field($name, $label, $control_html, $help = '', $labelled = true)
{
    ?>
    <div class="mb-3">
        <?php if ($labelled): ?>
            <label class="form-label" for="<?php echo e($name); ?>"><?php echo e($label); ?></label>
        <?php else: ?>
            <span class="form-label d-block"><?php echo e($label); ?></span>
        <?php endif; ?>
        <?php echo $control_html; ?>
        <?php if ($help !== ''): ?>
            <div class="form-text"><?php echo e($help); ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * One labelled text box.
 */
function admin_text_field($name, $label, $value = '', $help = '', $placeholder = '', $extra = '')
{
    $control = '<input type="text" class="form-control" id="' . e($name) . '" name="' . e($name) . '"'
        . ' value="' . e($value) . '"'
        . ($placeholder !== '' ? ' placeholder="' . e($placeholder) . '"' : '')
        . ' ' . $extra . '>';

    admin_field($name, $label, $control, $help);
}

/**
 * A big box for a long piece of writing. Empty lines are kept, because they
 * are what separate the paragraphs of a sermon or a devotional.
 *
 * The box takes the site's own font, not a fixed width one. This used to add
 * font-monospace, which made a devotional look like a page of source code
 * while it was being written, and a person reads their own words for meaning,
 * not for alignment. Nothing typed into one of these is code.
 */
function admin_textarea($name, $label, $value = '', $help = '', $rows = 8)
{
    $control = '<textarea class="form-control" id="' . e($name) . '" name="' . e($name) . '"'
        . ' rows="' . (int) $rows . '">' . e($value) . '</textarea>';

    admin_field($name, $label, $control, $help);
}

/**
 * A drop down. $options is an array of value => label.
 */
function admin_select($name, $label, $options, $value = '', $help = '')
{
    $control = '<select class="form-select" id="' . e($name) . '" name="' . e($name) . '">';
    foreach ($options as $option_value => $option_label) {
        $control .= '<option value="' . e($option_value) . '"'
            . ((string) $option_value === (string) $value ? ' selected' : '') . '>'
            . e($option_label) . '</option>';
    }
    $control .= '</select>';

    admin_field($name, $label, $control, $help);
}

/**
 * A single tick box, for the is_active columns.
 *
 * The label sits beside the box rather than above it, so this one does not use
 * admin_field()'s frame - a switch with a caption floating over it looks like a
 * form that has lost something.
 */
function admin_checkbox($name, $label, $checked = false, $help = '')
{
    ?>
    <div class="mb-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="<?php echo e($name); ?>"
                   name="<?php echo e($name); ?>" value="1" <?php echo $checked ? 'checked' : ''; ?>>
            <label class="form-check-label" for="<?php echo e($name); ?>"><?php echo e($label); ?></label>
        </div>
        <?php if ($help !== ''): ?>
            <div class="form-text"><?php echo e($help); ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * A date box.
 */
function admin_date_field($name, $label, $value = '', $help = '')
{
    admin_text_field($name, $label, $value, $help, '', 'type="date"');
}

/**
 * A time box. The database keeps TIME, so the box sends 09:00 and MySQL
 * stores 09:00:00.
 */
function admin_time_field($name, $label, $value = '', $help = '')
{
    $value = substr((string) $value, 0, 5);
    admin_text_field($name, $label, $value, $help, '', 'type="time"');
}

/**
 * Who leads this department or family.
 *
 * The drop down is every person in the people table who could lead a group,
 * so choosing one links the two properly and their page is linked from here.
 * "Not listed on the website" is deliberately the first entry: not every
 * leader wants a page of their own, and for those the name typed in the box
 * underneath is what the website shows.
 *
 * $selected_id  the leader_id already saved, or 0
 * $typed_name   the name already typed in, or ''
 * $choices      the result of leader_choices($conn)
 */
function admin_leader_field($selected_id, $typed_name, $choices, $help = '')
{
    $none = 0;
    $options = [$none => 'Not listed on the website'];
    foreach ($choices as $person_id => $label) {
        $options[$person_id] = $label;
    }
    ?>
    <div class="mb-3">
        <label class="form-label" for="leader_id">Who leads it</label>
        <select class="form-select mb-2" id="leader_id" name="leader_id">
            <?php foreach ($options as $person_id => $label): ?>
                <option value="<?php echo (int) $person_id; ?>" <?php echo (int) $selected_id === (int) $person_id ? 'selected' : ''; ?>>
                    <?php echo e($label); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="form-label" for="leader">Or type a name</label>
        <input type="text" class="form-control" id="leader" name="leader"
               value="<?php echo e($typed_name); ?>" maxlength="160"
               placeholder="Bro. Isaac Byaruhanga">
        <div class="form-text">
            <?php if ($help !== ''): ?>
                <?php echo e($help); ?>
            <?php else: ?>
                Used only when the box above is left on "Not listed on the website". A name chosen
                from the box wins over this one, so a leader who is added to the people list later
                does not have to be typed here again.
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Print the published / draft choice. It is two buttons rather than a drop down
 * because it is the one thing an editor changes most often.
 */
function admin_status_field($value = 'published')
{
    $control = '<div class="btn-group w-100" role="group" aria-label="Status">';
    foreach (['published' => 'Published', 'draft' => 'Draft'] as $option => $label) {
        $control .= '<input type="radio" class="btn-check" name="status" id="status-' . e($option) . '"'
            . ' value="' . e($option) . '"' . ($value === $option ? ' checked' : '') . '>'
            . '<label class="btn btn-outline-secondary" for="status-' . e($option) . '">'
            . e($label) . '</label>';
    }
    $control .= '</div>';

    /* $labelled is false because a row of buttons is not one control that a
       single "for" can point at, so it gets a heading rather than a label. */
    admin_field('status', 'Status', $control, 'A draft is saved but nobody can see it on the website.', false);
}

/* -------------------------------------------------------------------------
   6. FINDING A ROW, AND CHECKING WHAT WAS TYPED
   ---------------------------------------------------------------------- */

/**
 * Tables an admin page is allowed to look a row up in. The name comes from the
 * page, never from a browser, but the list is here anyway so that no page can
 * ask for a table that does not belong to it.
 */
function admin_table_allowed($table)
{
    return in_array($table, [
        'events', 'devotionals', 'sermons', 'announcements', 'programs', 'people',
        'departments', 'families', 'gallery', 'messages',
        'subscribers',
    ], true);
}

/**
 * A small box for the display_order column, straight in the table row.
 *
 * The box saves itself the moment it loses focus, which is what the
 * data-submit-on-change attribute in assets/js/main.js looks for. It saves
 * through the module's own list page, so the order can be fixed without
 * opening a form for each row.
 */
function admin_order_field($module, $id, $value)
{
    return '<form method="post" action="' . admin_url($module) . '" class="d-inline-block">'
        . csrf_field()
        . '<input type="hidden" name="action" value="set_order">'
        . '<input type="hidden" name="id" value="' . (int) $id . '">'
        . '<label class="visually-hidden" for="order-' . (int) $id . '">Order</label>'
        . '<input type="number" class="form-control form-control-sm" id="order-' . (int) $id . '" name="display_order"'
        . ' value="' . (int) $value . '" min="0" max="999" style="width:5rem" data-submit-on-change>'
        . '<button type="submit" class="visually-hidden">Save</button>'
        . '</form>';
}

/**
 * Find one row for the edit form. Returns an empty array when there is no such
 * row, which is what happens when a stale bookmark is followed.
 */
function admin_row($conn, $table, $id)
{
    if (!admin_table_allowed($table)) {
        return [];
    }

    $id = (int) $id;
    $statement = $conn->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
    $statement->bind_param('i', $id);
    $statement->execute();
    $result = $statement->get_result();
    $row = $result->fetch_assoc() ?: [];
    $statement->close();

    return $row;
}

/**
 * Say what is missing, without stopping the form from being shown again.
 */
function admin_check_required($value, $label, &$errors)
{
    if (is_empty_value($value)) {
        $errors[] = $label . ' is needed.';
        return false;
    }
    return true;
}

/**
 * Check a date or a time that was typed into a box, and hand back the value in
 * the exact shape the database column wants.
 *
 * A date has to be a real day, and a time has to be a real minute of the day.
 * Both are checked the same way, so they are one function: the format and the
 * words are the only things that change. Keeping them apart meant two copies of
 * the same strict check, and a fix to one of them would have left the other
 * quietly wrong.
 *
 * $kind is 'date' (2000-01-31, stored as it stands) or 'time' (09:30, stored as
 * 09:30:00 because the column is a TIME).
 *
 * $needed is false for a box that may be left empty, such as the end date of
 * something that happens in one day. An empty box then returns an empty string
 * and says nothing. $needed defaults to true here, because a missing date is
 * the more common mistake; a time that is genuinely optional asks for false.
 *
 * Returns '' when there is a problem, after adding a line to $errors, so the
 * form is shown again with the words still in the boxes.
 */
function admin_check_moment($value, $label, &$errors, $kind = 'date', $needed = true)
{
    $value = trim((string) $value);

    if ($value === '') {
        if ($needed) {
            $errors[] = $label . ' is needed.';
        }
        return '';
    }

    $is_time = $kind === 'time';
    $format = $is_time ? 'H:i' : 'Y-m-d';

    /* Comparing what was typed with what the date object reads back is what
       makes the check strict: PHP happily turns "2000-02-31" into the 2nd of
       March, and this is where that is caught. */
    $parsed = date_create_immutable_from_format($format, $value);
    if (!$parsed || $parsed->format($format) !== $value) {
        $errors[] = $label . ($is_time ? ' does not look like a real time.' : ' does not look like a real date.');
        return '';
    }

    return $is_time ? $value . ':00' : $value;
}

/**
 * Check that a family or a department chosen from a drop down is really there.
 *
 * The value arrives from the browser, so it is cast to a number and then looked
 * up in the table itself. 0 is the "not in one" entry and is passed straight
 * back, which is why the column defaults to 0 rather than NULL.
 */
function admin_check_group_choice($conn, $table, $id, $label, &$errors)
{
    $id = (int) $id;

    if ($id < 1) {
        return 0;
    }

    if (!admin_table_allowed($table)) {
        $errors[] = 'That choice was not one of the list.';
        return 0;
    }

    $statement = $conn->prepare("SELECT id FROM `$table` WHERE id = ? LIMIT 1");
    $statement->bind_param('i', $id);
    $statement->execute();
    $exists = $statement->get_result()->num_rows > 0;
    $statement->close();

    if (!$exists) {
        $errors[] = $label . ' is no longer on the list. Choose it again from the box.';
        return 0;
    }

    return $id;
}

/**
 * A year, for example 2019.
 *
 * These boxes are optional and are typed by hand, so they need tidying before
 * they reach the database: a blank box becomes NULL rather than 0, because 0
 * is not a year anybody joined the church in. Anything that is not a plain
 * four digit year between 1900 and the year after next is refused, so a stray
 * full stop cannot end up in the column and quietly sort the list wrongly.
 */
function admin_check_year($value, $label, &$errors)
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (preg_match('/^[0-9]{4}$/', $value) !== 1) {
        $errors[] = $label . ' should be four digits, for example 2019.';
        return null;
    }

    $year = (int) $value;
    $latest = (int) date('Y') + 1;
    if ($year < 1900 || $year > $latest) {
        $errors[] = $label . ' has to be between 1900 and ' . $latest . '.';
        return null;
    }

    return $year;
}
