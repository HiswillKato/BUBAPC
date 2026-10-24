<?php
/**
 * BUBAPC - Contact us and find us
 *
 * The form at the bottom stores a row in the messages table and sends a copy
 * to the church mailbox, so the pastor is told the moment somebody writes.
 * The message is saved BEFORE the email is attempted and the thank-you is
 * never held up by the mail server, because a website that says "we will be
 * in touch" and then silently drops the message is worse than one that says
 * "we have your message". The admin reads the list in /dashboard/admin/messages.
 *
 * The page is a POST handler as well as a page, so it starts by checking
 * $_POST before it prints anything.
 */

require_once __DIR__ . '/../../../app/init.php';

$page_title = 'Contact us';
$page_description = 'Find ' . SITE_NAME . ', call us, or send a message and we will reply.';
$canonical = 'contact';

/* ---------------------------------------------------------------------
   1. THE MESSAGE FORM
   A GET request never changes anything, so all of this only runs when a
   form was actually posted.
   --------------------------------------------------------------------- */
$formErrors = [];
$name = '';
$email = '';
$phone = '';
$subject = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name    = postText('name');
    $email   = postText('email');
    $phone   = postText('phone');
    $subject = postText('subject');
    $message = postText('message');

    if (isEmptyValue($name)) {
        $formErrors['name'] = 'Please tell us your name.';
    }
    if (!isValidEmail($email)) {
        $formErrors['email'] = 'Please give an email address we can reply to.';
    }
    if (mb_strlen($phone) > 40) {
        $formErrors['phone'] = 'That phone number is too long.';
    }
    if (isEmptyValue($subject)) {
        $formErrors['subject'] = 'Please give the message a subject.';
    }
    if (mb_strlen($message) < 10) {
        $formErrors['message'] = 'Please write a little more, so we know how to help.';
    }

    // A simple honeypot. A real person never sees this field, so anything in
    // it means a robot filled the form in. Nothing is stored, and the sender
    // is thanked, so the robot does not try again.
    $looks_like_a_robot = postText('website') !== '';

    if (!$formErrors && !$looks_like_a_robot) {
        $statement = $conn->prepare("INSERT INTO messages (name, email, phone, subject, message, seen)
                                     VALUES (?, ?, ?, ?, ?, 0)");
        $statement->bind_param('sssss', $name, $email, $phone, $subject, $message);
        $statement->execute();
        $statement->close();

        /* A copy of the message goes to the church's mailbox as well as into
           the messages table, so the pastor is told the moment somebody writes
           rather than only when the admin panel is opened. Best-effort: a mail
           server that is down must not stop the thank-you below. */
        mailContactNotice($name, $email, $phone, $subject, $message);

        /* Written after the row is saved, and saying only that a message
           arrived rather than repeating what was said in it, so the activity
           log does not become a second copy of the messages table. */
        logAction($conn, 'contact_received', 'A message arrived from ' . $name);

        flash('success', 'Thank you. Your message has reached us and someone will reply soon.');
        redirect('contact');
    }

    if ($looks_like_a_robot) {
        flash('success', 'Thank you. Your message has reached us and someone will reply soon.');
        redirect('contact');
    }
}

require_once __DIR__ . '/../partials/public/header.php';

$programs_by_day = weeklyProgramByDay($conn);

pageHeader(
    'Contact us',
    'Whether you want to visit, ask a question, or tell us how we can pray for you, '
    . 'this is the fastest way to reach us.',
    [['Home', url('')], ['Contact', '']]
);
?>

    <section class="py-5">
        <div class="container">
            <div class="row g-4 g-lg-5">

                <!-- ============ THE FORM ============ -->
                <div class="col-lg-7">
                    <?php sectionHeading('Send us a message', 'Write to us', 'We read every message that arrives here.'); ?>

                    <?php showFlashes(); ?>

                    <?php if ($formErrors): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fa-solid fa-circle-exclamation me-1" aria-hidden="true"></i>
                            Please check the form and try again.
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo url('contact'); ?>" novalidate>
                        <?php echo csrfField(); ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Your name</label>
                                <input type="text" class="form-control<?php echo isset($formErrors['name']) ? ' is-invalid' : ''; ?>"
                                       id="name" name="name" value="<?php echo e($name); ?>"
                                       maxlength="160" required autocomplete="name">
                                <?php if (isset($formErrors['name'])): ?>
                                    <div class="invalid-feedback"><?php echo e($formErrors['name']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="email">Your email</label>
                                <input type="email" class="form-control<?php echo isset($formErrors['email']) ? ' is-invalid' : ''; ?>"
                                       id="email" name="email" value="<?php echo e($email); ?>"
                                       maxlength="190" required autocomplete="email">
                                <?php if (isset($formErrors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo e($formErrors['email']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="phone">Your phone <span class="text-body-secondary fw-normal">(optional)</span></label>
                                <input type="tel" class="form-control<?php echo isset($formErrors['phone']) ? ' is-invalid' : ''; ?>"
                                       id="phone" name="phone" value="<?php echo e($phone); ?>"
                                       maxlength="40" autocomplete="tel">
                                <?php if (isset($formErrors['phone'])): ?>
                                    <div class="invalid-feedback"><?php echo e($formErrors['phone']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="subject">Subject</label>
                                <input type="text" class="form-control<?php echo isset($formErrors['subject']) ? ' is-invalid' : ''; ?>"
                                       id="subject" name="subject" value="<?php echo e($subject); ?>"
                                       maxlength="200" required>
                                <?php if (isset($formErrors['subject'])): ?>
                                    <div class="invalid-feedback"><?php echo e($formErrors['subject']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="message">Your message</label>
                                <textarea class="form-control<?php echo isset($formErrors['message']) ? ' is-invalid' : ''; ?>"
                                          id="message" name="message" rows="6" required><?php echo e($message); ?></textarea>
                                <?php if (isset($formErrors['message'])): ?>
                                    <div class="invalid-feedback"><?php echo e($formErrors['message']); ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Honeypot: hidden from people, tempting to robots. -->
                            <div class="visually-hidden" aria-hidden="true">
                                <label for="website">Leave this field empty</label>
                                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i>Send message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ============ THE DETAILS ============ -->
                <div class="col-lg-5">
                    <?php sectionHeading('Find us', 'Where we are', ''); ?>

                    <div class="list-group rounded-4 shadow-sm">
                        <div class="list-group-item d-flex gap-3 p-3">
                            <i class="fa-solid fa-location-dot text-brand fs-5" aria-hidden="true"></i>
                            <div>
                                <p class="fw-semibold mb-0">Where we meet</p>
                                <span class="text-body-secondary small">
                                    <?php echo e(SITE_LOCATION !== '' ? SITE_LOCATION : SITE_FULL_NAME); ?>
                                </span>
                            </div>
                        </div>

                        <?php if (SITE_PHONE !== ''): ?>
                            <div class="list-group-item d-flex gap-3 p-3">
                                <i class="fa-solid fa-phone text-brand fs-5" aria-hidden="true"></i>
                                <div>
                                    <p class="fw-semibold mb-0">Call us</p>
                                    <a class="small"
                                       href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', SITE_PHONE)); ?>">
                                        <?php echo e(SITE_PHONE); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (SITE_EMAIL !== ''): ?>
                            <div class="list-group-item d-flex gap-3 p-3">
                                <i class="fa-solid fa-envelope text-brand fs-5" aria-hidden="true"></i>
                                <div>
                                    <p class="fw-semibold mb-0">Email us</p>
                                    <a class="small" href="mailto:<?php echo e(SITE_EMAIL); ?>">
                                        <?php echo e(SITE_EMAIL); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="list-group-item d-flex gap-3 p-3">
                            <i class="fa-solid fa-clock text-brand fs-5" aria-hidden="true"></i>
                            <div>
                                <p class="fw-semibold mb-0">When we meet</p>
                                <a class="small" href="<?php echo url('programs'); ?>">
                                    See the weekly program
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4 mt-4">
                        <div class="card-body p-4">
                            <p class="fw-semibold mb-2">Coming for the first time?</p>
                            <p class="text-body-secondary small mb-3">
                                Just come as you are, a little early so you can find a seat, and
                                stay for the whole meeting. If you have a question and would rather
                                not ask it in front of everyone, send a message instead &mdash;
                                somebody will come and find you.
                            </p>
                            <a class="btn btn-soft btn-sm" href="<?php echo url('about'); ?>">
                                About <?php echo e(SITE_NAME); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ THE WEEK AT A GLANCE ============ -->
            <?php if ($programs_by_day): ?>
                <div class="mt-5 pt-4">
                    <?php sectionHeading('The week at a glance', 'When we meet', '', 2); ?>

                    <div class="row g-3">
                        <?php foreach ($programs_by_day as $day_label => $day_programs): ?>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="card h-100 border-0 shadow-sm rounded-4">
                                    <div class="card-body p-3">
                                        <p class="fw-semibold mb-2"><?php echo e($day_label); ?></p>
                                        <?php foreach ($day_programs as $program): ?>
                                            <?php
                                            /* "When we meet", so a meeting whose two teams meet at
                                               different hours shows those hours, one per line; the one
                                               window is only right when everybody meets together. */
                                            $time_lines = programTimes($program);
                                            ?>
                                            <p class="small text-body-secondary mb-1">
                                                <?php if ($time_lines): ?>
                                                    <?php foreach ($time_lines as $time_line): ?>
                                                        <span class="d-block"><?php echo $time_line; ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <?php echo formatTimeRange($program['start_time'], $program['end_time'] ?? ''); ?>
                                                <?php endif; ?>
                                                <?php if ($program['title'] !== ''): ?>
                                                    <span class="d-block text-truncate"><?php echo e($program['title']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

<?php require_once __DIR__ . '/../partials/public/footer.php'; ?>
