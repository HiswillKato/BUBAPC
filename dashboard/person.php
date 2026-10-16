<?php
/**
 * BUBAPC - Your own page in the directory
 *
 * A directory person - a student, a leader, a pastor - may change the parts of
 * their entry that are theirs to tell: their name, how the church reaches them,
 * the short life story and the testimony, and the photograph. Everything that
 * says where they stand in the church is left to the office: the group, the
 * office they hold, their family, their department, the slot they sit in and
 * whether the page shows at all. Those are decisions about the church, not
 * about the person, so they are not on this form.
 *
 * The row that changes is always the one linked to the signed in account,
 * read from the session. The id is never taken from the form.
 */

require_once __DIR__ . '/../app/init.php';

require_login();

/* An account with no directory page behind it - a website admin, or a member
   who signed up themselves - has nothing to edit here, so they are sent back
   to the account page rather than shown an empty form. */
$person = current_person($conn);
if (!$person) {
    redirect(member_url());
}

$errors = [];

$values = [
    'name' => $person['name'],
    'email' => $person['email'],
    'phone' => $person['phone'],
    'bio' => (string) $person['bio'],
    'testimony' => (string) $person['testimony'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values['name'] = post_text('name');
    $values['email'] = post_text('email');
    $values['phone'] = post_text('phone');
    $values['bio'] = post_text('bio');
    $values['testimony'] = post_text('testimony');

    if (is_empty_value($values['name'])) {
        $errors[] = 'Please tell us your name.';
    } elseif (mb_strlen($values['name']) > 160) {
        $errors[] = 'That name is too long. Please use 160 characters or fewer.';
    }

    if (!is_valid_email($values['email'])) {
        $errors[] = 'Please type a real email address, for example name@example.com.';
    } elseif ($values['email'] !== $person['email']) {
        /* The address is also the sign-in name, so no other account may use
           it. A directory-only person may share it, so the check is limited
           to rows that carry a password. */
        $statement = $conn->prepare(
            "SELECT id FROM people WHERE password IS NOT NULL AND (username = ? OR email = ?) AND id <> ? LIMIT 1"
        );
        $statement->bind_param('ssi', $values['email'], $values['email'], $person['id']);
        $statement->execute();
        $clash = (bool) $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($clash) {
            $errors[] = 'Another sign in already uses that email address. Please use a different one.';
        }
    }

    if (!$errors) {
        $image = save_image(
            $_FILES['image'] ?? null,
            post_checkbox('remove_image'),
            'people',
            $person['image']
        );

        $statement = $conn->prepare(
            "UPDATE people SET name = ?, email = ?, username = ?, phone = ?, bio = ?, testimony = ?, image = ?
             WHERE id = ?"
        );
        /* The row is both the person and the sign-in, so the name, the email,
           the phone and the sign-in name are written together. A member signs
           in with the address they typed, so the username follows it; a staff
           account keeps the username an admin gave it, and a row with no
           sign-in keeps its NULL. */
        $sign_in_name = ($person['password'] !== null && $person['role'] === 'member')
            ? mb_substr($values['email'], 0, 190)
            : $person['username'];
        $statement->bind_param(
            'sssssssi',
            mb_substr($values['name'], 0, 160),
            mb_substr($values['email'], 0, 190),
            $sign_in_name,
            mb_substr($values['phone'], 0, 40),
            $values['bio'],
            $values['testimony'],
            $image['path'],
            $person['id']
        );
        $statement->execute();
        $statement->close();

        /* The session holds a copy of the account row, so the change is not
           visible until that copy is thrown away. */
        unset($_SESSION['user_data']);

        /* The page itself is saved either way; a problem with the picture is
           only worth a footnote, not a second try at the whole form. */
        $message = 'Your church profile has been saved.';
        if ($image['error'] !== '') {
            $message .= ' The picture was not changed: ' . $image['error'];
        }

        log_action($conn, 'person_self_updated', $values['name']);
        flash('success', $message);
        redirect('dashboard/person');
    }
}

$page_title = 'My church profile';
require_once __DIR__ . '/../app/includes/public/header.php';
?>

    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-md-9 col-lg-8">
                    <?php page_header(
                        'My church profile',
                        'The page the church shows about you.',
                        [['Home', url('')], ['My account', member_url()], ['My church profile', null]]
                    ); ?>

                    <?php form_errors($errors); ?>
                    <?php show_flashes(); ?>

                    <div class="card border-0 shadow rounded-4 mb-4">
                        <div class="card-body p-4 p-md-5">
                            <form method="post" action="<?php echo url('dashboard/person'); ?>"
                                  enctype="multipart/form-data" novalidate>
                                <?php echo csrf_field(); ?>

                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <?php if (!empty($person['image'])): ?>
                                        <img src="<?php echo upload_url($person['image']); ?>"
                                             alt="<?php echo e($person['name']); ?>"
                                             class="rounded-circle object-fit-cover flex-shrink-0"
                                             width="72" height="72">
                                    <?php else: ?>
                                        <span class="d-inline-flex align-items-center justify-content-center
                                                     rounded-circle bg-secondary-subtle text-body-secondary
                                                     fw-semibold flex-shrink-0"
                                              style="width: 72px; height: 72px; font-size: 1.5rem;">
                                            <?php echo initials($person['name']); ?>
                                        </span>
                                    <?php endif; ?>

                                    <div class="flex-grow-1">
                                        <label class="form-label" for="image">Your photograph</label>
                                        <input type="file" class="form-control" id="image" name="image"
                                               accept="image/jpeg,image/png,image/webp">
                                        <div class="form-text">
                                            JPG, PNG or WEBP, up to
                                            <?php echo round(MAX_UPLOAD_SIZE / 1048576); ?> MB.
                                        </div>
                                        <?php if (!empty($person['image'])): ?>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" value="1"
                                                       id="remove_image" name="remove_image">
                                                <label class="form-check-label" for="remove_image">
                                                    Remove my photograph
                                                </label>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="name">Your name</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           value="<?php echo e($values['name']); ?>"
                                           required maxlength="160" autocomplete="name">
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="email">Email</label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                   value="<?php echo e($values['email']); ?>"
                                                   required maxlength="190" autocomplete="email"
                                                   autocapitalize="none" spellcheck="false">
                                            <div class="form-text">This is also how you sign in.</div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="phone">Phone</label>
                                            <input type="text" class="form-control" id="phone" name="phone"
                                                   value="<?php echo e($values['phone']); ?>"
                                                   maxlength="40" autocomplete="tel">
                                            <div class="form-text">How the church reaches you.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="bio">About you</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="4"
                                              placeholder="A few lines about yourself."><?php echo e($values['bio']); ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label" for="testimony">Your testimony</label>
                                    <textarea class="form-control" id="testimony" name="testimony" rows="5"
                                              placeholder="How God has worked in your life."><?php echo e($values['testimony']); ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
                                    Save my profile
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card border-0 shadow rounded-4">
                        <div class="card-body p-4">
                            <h2 class="h6 text-uppercase small text-body-secondary mb-3">
                                Kept by the church office
                            </h2>
                            <dl class="row mb-0 small">
                                <dt class="col-sm-4 text-body-secondary fw-normal">In the church as</dt>
                                <dd class="col-sm-8 mb-2">
                                    <?php echo e(people_categories()[$person['category']] ?? $person['category']); ?>
                                </dd>

                                <?php if ($person['position'] !== ''): ?>
                                    <dt class="col-sm-4 text-body-secondary fw-normal">Office</dt>
                                    <dd class="col-sm-8"><?php echo e($person['position']); ?></dd>
                                <?php endif; ?>
                            </dl>

                            <p class="small text-body-secondary mb-0">
                                To change any of these, or your password,
                                <a href="<?php echo member_url(); ?>">go to your account</a>
                                or speak to the church office.
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 mb-0">
                        <a href="<?php echo member_url(); ?>">
                            <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
                            Back to my account
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; ?>
