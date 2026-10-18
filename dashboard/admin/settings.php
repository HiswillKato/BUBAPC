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
 * exists at all, so those values cannot depend on the database.
 */

require_once __DIR__ . '/../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../app/includes/admin/parts.php';
if (current_user()['role'] ?? '' !== 'admin') {
    flash('error', 'Only website administrators can open that page.');
    redirect(admin_url());
}
require_staff();

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

$page_title = 'Settings';
$page_heading = 'Settings';

require_once __DIR__ . '/../../app/includes/admin/header.php';
?>

    <div class="row justify-content-center">

        <div class="col-12 col-xl-10">

            <form method="post" novalidate>
                <?php echo csrf_field(); ?>

                <div class="card border-0 shadow-sm rounded-4">
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
        </div>

    </div>

<?php require_once __DIR__ . '/../../app/includes/admin/footer.php'; ?>
