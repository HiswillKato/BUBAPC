<?php
/**
 * BUBAPC - Admin: one message in full
 *
 * A GET on this page marks the message as read, because opening it is what
 * reading it means. That is the one GET in the admin panel that changes
 * something, and it changes nothing anybody could lose.
 */

require_once __DIR__ . '/../../../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../../../app/includes/admin/parts.php';

$active_module = 'messages';

$message_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$message = admin_row($conn, 'messages', $message_id);

if (!$message) {
    flash('error', 'That message is no longer here. It may already have been deleted.');
    redirect(admin_url('messages'));
}

if ((int) $message['seen'] === 0) {
    $statement = $conn->prepare("UPDATE messages SET seen = 1 WHERE id = ?");
    $statement->bind_param('i', $message_id);
    $statement->execute();
    $statement->close();
}

$page_title = 'Message';
$page_heading = 'Message';
$page_actions = '<a class="btn btn-outline-secondary" href="' . admin_url('messages') . '">'
    . '<i class="fa-solid fa-list me-1" aria-hidden="true"></i>All messages</a>';

require_once __DIR__ . '/../../../../app/includes/admin/header.php';
?>

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">

                    <p class="text-body-secondary small mb-1">
                        <i class="fa-solid fa-envelope me-1" aria-hidden="true"></i>
                        From
                        <strong><?php echo e($message['name']); ?></strong>
                        &lt;<a href="mailto:<?php echo e($message['email']); ?>"><?php echo e($message['email']); ?></a>&gt;
                    </p>
                    <?php if (!empty($message['phone'])): ?>
                        <p class="text-body-secondary small mb-1">
                            <i class="fa-solid fa-phone me-1" aria-hidden="true"></i>
                            Phone
                            <a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', $message['phone'])); ?>">
                                <?php echo e($message['phone']); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    <p class="text-body-secondary small mb-4">
                        Sent <?php echo format_date($message['created'], 'j F Y \a\t g:ia'); ?>
                        (<?php echo e(ago($message['created'])); ?>)
                    </p>

                    <?php if ($message['subject'] !== ''): ?>
                        <h2 class="h5 mb-3"><?php echo e($message['subject']); ?></h2>
                    <?php endif; ?>

                    <div class="border-start border-3 border-warning ps-3">
                        <?php echo paragraphs($message['message']); ?>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h2 class="h6 mb-3">Write back</h2>
                    <p class="small text-body-secondary">
                        Replying opens your own email program, so the reply comes from your own address.
                    </p>
                    <a class="btn btn-primary"
                       href="mailto:<?php echo e($message['email']); ?>?subject=<?php echo rawurlencode('Re: ' . ($message['subject'] !== '' ? $message['subject'] : 'Your message')); ?>">
                        <i class="fa-solid fa-reply me-1" aria-hidden="true"></i>Open in my email program
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../../../../app/includes/admin/footer.php'; ?>
