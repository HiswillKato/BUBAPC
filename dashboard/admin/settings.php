<?php
/**
 * BUBAPC - Admin: settings
 *
 * Two things can be changed from here:
 *
 *  - maintenance mode, which hides the website from visitors while it is being
 *    worked on. Everything is still readable from this panel.
 *  - the wording shown while maintenance mode is on.
 *
 * The church's name, address, phone number and social pages are NOT here. They
 * live in app/config.php, and the reason is worth knowing: the installer has to
 * be able to read the site name and the database details before the database
 * exists at all, so those values cannot depend on the database. The panel at
 * the bottom of this page shows what they are set to, and which file to open
 * to change them.
 */

require_once __DIR__ . '/../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../app/includes/admin/parts.php';
require_admin();

$active_module = 'settings';

/* The things a visitor could type into, with a note about what each one does.
   A new one is added here first, and nowhere else. */
$setting_fields = [
    'maintenance_message' => [
        'label' => 'The message shown while the site is closed',
        'type' => 'textarea',
        'rows' => 4,
        'default' => 'We are doing a little work on the website. Please come back shortly.',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $was_on = is_maintenance_mode($conn);

    setting_set($conn, 'maintenance_mode', post_checkbox('maintenance_mode') ? '1' : '0');

    foreach ($setting_fields as $key => $field) {
        setting_set($conn, $key, post_text($key));
    }

    /* What was actually changed is named in the log, because "settings changed"
       on its own would be useless to whoever reads the screen in six months. */
    $now_on = post_checkbox('maintenance_mode');
    if ($was_on !== $now_on) {
        log_action($conn, 'settings_changed', $now_on
            ? 'Maintenance mode was turned ON. Visitors cannot see the website.'
            : 'Maintenance mode was turned OFF.');
    }

    flash('success', 'The settings have been saved.');
    redirect(admin_url('settings'));
}

$maintenance_on = is_maintenance_mode($conn);

/* The values that live in the config file, shown so nobody has to go
   searching for them. */
$config_values = [
    'Church name (short)'      => [SITE_NAME, "define('SITE_NAME', ...)"],
    'Church name (full)'       => [SITE_FULL_NAME, "define('SITE_FULL_NAME', ...)"],
    'Motto'                    => [SITE_TAGLINE, "define('SITE_TAGLINE', ...)"],
    'Where you are'            => [SITE_LOCATION, "define('SITE_LOCATION', ...)"],
    'Phone'                    => [SITE_PHONE, "define('SITE_PHONE', ...)"],
    'Email'                    => [SITE_EMAIL, "define('SITE_EMAIL', ...)"],
    'Facebook page'            => [FACEBOOK_URL, "define('FACEBOOK_URL', ...)"],
    'YouTube channel'          => [YOUTUBE_URL, "define('YOUTUBE_URL', ...)"],
    'Instagram'                => [INSTAGRAM_URL, "define('INSTAGRAM_URL', ...)"],
    'TikTok'                   => [TIKTOK_URL, "define('TIKTOK_URL', ...)"],
    'WhatsApp'                 => [WHATSAPP_URL, "define('WHATSAPP_URL', ...)"],
    'Live website address'     => [BASE_URL !== '' ? BASE_URL : url('') . '  (worked out from the address bar)', "define('BASE_URL', ...)"],
];

$page_title = 'Settings';
$page_heading = 'Settings';

require_once __DIR__ . '/../../app/includes/admin/header.php';
?>

    <div class="row g-4">

        <div class="col-lg-7">

            <form method="post" novalidate>
                <?php echo csrf_field(); ?>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">
                            <i class="fa-solid fa-screwdriver-wrench me-2 text-gold" aria-hidden="true"></i>
                            Closing the website
                        </h2>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="maintenance_mode" name="maintenance_mode" value="1"
                                       <?php echo $maintenance_on ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="maintenance_mode">
                                    Maintenance mode
                                </label>
                            </div>
                            <div class="form-text">
                                When this is on, every visitor sees the message below instead of the
                                website. This panel still works, and so does <?php echo url('login'); ?>,
                                so nobody is locked out.
                            </div>
                        </div>

                        <?php
                        foreach ($setting_fields as $key => $field) {
                            $value = setting_get($conn, $key, $field['default']);
                            if ($field['type'] === 'textarea') {
                                admin_textarea($key, $field['label'], $value, '', $field['rows']);
                            } else {
                                admin_text_field($key, $field['label'], $value);
                            }
                        }
                        ?>

                        <?php admin_form_buttons(admin_url('settings'), 'Save settings'); ?>
                    </div>
                </div>
            </form>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-2">
                        <i class="fa-solid fa-shield-halved me-2 text-gold" aria-hidden="true"></i>
                        Safety
                    </h2>
                    <p class="small text-body-secondary mb-3">
                        A few of the things that keep the website safe, and where they are turned up.
                        You do not need to change any of them to run a church website.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle small mb-0">
                            <tbody>
                                <tr>
                                    <td>Wrong passwords are locked out after</td>
                                    <td class="text-end"><?php echo (int) LOGIN_MAX_ATTEMPTS; ?> tries, for <?php echo (int) LOGIN_LOCK_MINUTES; ?> minutes</td>
                                </tr>
                                <tr>
                                    <td>Shortest password allowed</td>
                                    <td class="text-end"><?php echo (int) MIN_PASSWORD_LENGTH; ?> characters</td>
                                </tr>
                                <tr>
                                    <td>Largest picture that can be uploaded</td>
                                    <td class="text-end"><?php echo round(MAX_UPLOAD_SIZE / 1048576); ?> MB</td>
                                </tr>
                                <tr>
                                    <td>PHP errors on the page</td>
                                    <td class="text-end">
                                        <?php echo SHOW_ERRORS ? 'Shown' : 'Hidden'; ?>
                                        <?php if (SHOW_ERRORS): ?>
                                            <span class="badge rounded-pill text-bg-warning ms-1">Turn off before going live</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-2">
                        <i class="fa-solid fa-address-card me-2 text-gold" aria-hidden="true"></i>
                        Who you are
                    </h2>
                    <p class="small text-body-secondary mb-3">
                        These are the details printed in the footer, on the contact page and on the buttons
                        that open WhatsApp and Facebook. They live in the file
                        <code>app/config.php</code>, one line each.
                    </p>
                    <p class="small text-body-secondary mb-3">
                        They are not on this page on purpose. The installer has to be able to read the church
                        name before the database exists, so these cannot be kept in the database.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle small mb-3">
                            <tbody>
                                <?php foreach ($config_values as $label => $pair): ?>
                                    <tr>
                                        <td class="text-body-secondary" style="min-width:9rem"><?php echo e($label); ?></td>
                                        <td>
                                            <?php if (trim((string) $pair[0]) === ''): ?>
                                                <span class="text-body-secondary fst-italic">not set</span>
                                            <?php else: ?>
                                                <span class="text-break"><?php echo e($pair[0]); ?></span>
                                            <?php endif; ?>
                                            <br>
                                            <code class="text-body-secondary"><?php echo e($pair[1]); ?></code>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="small text-body-secondary mb-0">
                        Change a line, save the file, and the whole website uses the new value at once.
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../../app/includes/admin/footer.php'; ?>
