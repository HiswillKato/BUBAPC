<?php
/**
 * BUBAPC - Admin: add or change one family
 *
 * The leader is chosen from the people the website already knows about, so
 * their page is linked from this family and their name follows them if it is
 * ever changed. The box underneath is the escape hatch for a leader who is not
 * on the website, which is how it worked before the people list existed.
 *
 * The students who belong to this family are not typed in here. They are
 * pointed at the family from their own form under People, so a student only
 * ever has to be recorded once.
 */

require_once __DIR__ . '/../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../app/includes/admin/parts.php';

$active_module = 'families';

$family_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$family = $family_id ? admin_row($conn, 'families', $family_id) : [];

if ($family_id && !$family) {
    flash('error', 'That family is no longer here.');
    redirect(admin_url('families'));
}

$is_new = $family_id === 0;

$values = array_merge([
    'name' => '',
    'slug' => '',
    'leader_id' => 0,
    'leader' => '',
    'description' => '',
    'active' => 1,
    'display_order' => 0,
], $family);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values['name'] = post_text('name');
    $values['leader'] = post_text('leader');
    $values['description'] = post_text('description');
    $values['display_order'] = (int) ($_POST['display_order'] ?? 0);
    $values['active'] = post_checkbox('active') ? 1 : 0;

    admin_check_required($values['name'], 'The name of the family', $errors);
    admin_check_required($values['description'], 'The description', $errors);

    /* A leader chosen from the list has to be a real person, or a family
       would end up pointing at nothing. 0 means "type the name below". */
    $values['leader_id'] = admin_check_group_choice(
        $conn, 'people', (int) ($_POST['leader_id'] ?? 0), 'The leader', $errors
    );

    /* Choosing a person and typing a name as well is not an error, it is just
       the typed name being ignored. Say so rather than making the editor
       remember which one wins. */
    if ($values['leader_id'] > 0 && $values['leader'] !== '') {
        $values['leader'] = '';
    }

    if (!$errors) {
        /* The address is made from the name and never typed, so it cannot arrive
           with a capital or a space in it. slug() adds a number when another
           family already has this one. On an edit the address that is already saved
           is kept, so a corrected name does not break links to the page. */
        $values['slug'] = $is_new
            ? slug($conn, 'families', $values['name'])
            : $family['slug'];


        if ($is_new) {
            $statement = $conn->prepare(
                "INSERT INTO families (name, slug, leader, typed, description, active, display_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            /* The order of these letters has to match the order of the
               question marks: name, slug, leader, typed, description,
               active, display_order. */
            $statement->bind_param(
                'ssissii',
                $values['name'],
                $values['slug'],
                $values['leader_id'],
                $values['leader'],
                $values['description'],
                $values['active'],
                $values['display_order']
            );
            $statement->execute();
            $family_id = (int) db_last_id($conn);
            $statement->close();

            log_action($conn, 'family_created', 'The family "' . $values['name'] . '"');
            flash('success', 'The family "' . $values['name'] . '" has been added.');
        } else {
            $statement = $conn->prepare(
                "UPDATE families
                 SET name = ?, slug = ?, leader = ?, typed = ?, description = ?,
                     active = ?, display_order = ?
                 WHERE id = ?"
            );
            /* Same order as the insert, then the id of the row to change. */
            $statement->bind_param(
                'ssissiii',
                $values['name'],
                $values['slug'],
                $values['leader_id'],
                $values['leader'],
                $values['description'],
                $values['active'],
                $values['display_order'],
                $family_id
            );
            $statement->execute();
            $statement->close();

            log_action($conn, 'family_updated', 'The family "' . $values['name'] . '"');
            flash('success', 'The family "' . $values['name'] . '" has been saved.');
        }

        redirect(admin_url('families'));
    }
}

$page_title = $is_new ? 'Add family' : 'Edit family';
$page_heading = $is_new ? 'Add a family' : 'Edit family';
$page_actions = '<a class="btn btn-outline-secondary" href="' . admin_url('families') . '">'
    . '<i class="fa-solid fa-list me-1" aria-hidden="true"></i>Back to the list</a>';

require_once __DIR__ . '/../../app/includes/admin/header.php';
?>

    <div class="row g-4">
        <div class="col-lg-8">

            <?php admin_errors($errors); ?>

            <form method="post" novalidate>
                <?php echo csrf_field(); ?>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <?php
                        admin_text_field('name', 'Name of the family', $values['name'], '',
                            'The Grace Family', 'data-slug-source');
                        admin_leader_field((int) $values['leader_id'], $values['leader'], leader_choices($conn));
                        admin_textarea('description', 'Who it is for, and when it meets', $values['description'],
                            'Leave an empty line between paragraphs. Mention the year of study it is for, and '
                            . 'the day and time it meets, because that is what a student is looking for.', 8);
                        ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 text-uppercase text-body-secondary mb-3">On the website</h2>
                        <?php admin_slug_field(url('families'), $values['slug']); ?>
                        <?php
                        admin_text_field('display_order', 'Order on the page', (string) $values['display_order'],
                            'Lower numbers come first. You can also change this from the list.', '0');
                        admin_checkbox('active', 'Show this family on the website', (int) $values['active'] === 1);
                        ?>
                    </div>
                </div>

                <?php admin_form_buttons(admin_url('families'), $is_new ? 'Add family' : 'Save changes'); ?>
            </form>
        </div>

        <div class="col-lg-4">
            <?php if (!$is_new): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-3">On the website</h2>
                        <p class="small text-body-secondary mb-2">
                            Address:
                            <a class="d-block text-break" target="_blank" rel="noopener"
                               href="<?php echo url('families/' . $family['slug']); ?>">
                                <?php echo url('families/' . $family['slug']); ?>
                            </a>
                        </p>
                        <p class="small text-body-secondary mb-0">
                            <?php echo (int) $family['active'] === 1
                                ? 'Currently shown on the families page.'
                                : 'Currently hidden from the website.'; ?>
                        </p>
                    </div>
                </div>

                <div class="card border-danger rounded-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">Delete this family</h2>
                        <p class="small text-body-secondary">
                            It comes off the website at once and the picture is deleted. If you only want it
                            off the website for a while, use the switch on the form instead.
                        </p>
                        <?php echo admin_delete_button('families', $family_id, 'the family "' . $family['name'] . '"'); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h2 class="h6 mb-2">What a student needs to read</h2>
                        <p class="small text-body-secondary mb-0">
                            Somebody opening this page is usually alone on a campus and deciding whether to walk
                            into a room of strangers. Say the day, the time, and what happens when they arrive.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../app/includes/admin/footer.php'; ?>
