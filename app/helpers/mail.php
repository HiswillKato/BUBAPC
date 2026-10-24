<?php
/**
 * BUBAPC - The single outbound-email path
 *
 * Every email this website sends goes through send_mail(). That function is
 * the whole reason PHPMailer is in vendor/: it builds a message, delivers it
 * through the SMTP details in app/config.php, and quietly reports whether the
 * delivery worked. Nothing else in the project may call PHPMailer (or worse,
 * mail()) directly - a page that wants to send email calls one of the helpers
 * below, and those are the only callers of send_mail().
 *
 * The helpers are best-effort on purpose. The website must never decide
 * whether something happened based on whether an email was sent: a contact
 * message is always saved in the messages table, a reset link is always
 * saved in the tokens table. Email is a copy on the way out, not a receipt.
 */

require_once APP_ROOT . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Deliver one email. Returns true when the mail server accepted it.
 *
 * $to and $subject are the recipient and the subject line. $html is the
 * finished message body, and $plain is the same words without any markup.
 * Both are always sent, on purpose: some church members read on phones whose
 * mail program shows HTML badly, and a reset link is worth being able to read
 * out and type in by hand.
 *
 * Everything here is defensive, because a broken mail setting must never
 * crash a public page:
 *
 *   * The SMTP server is only attempted when MAIL_HOST is set and is not
 *     localhost, otherwise the mail is handed to sendmail, which on this
 *     machine is Postfix itself.
 *   * Every exception is swallowed and reported as a failed send.
 *   * MAIL_FROM is the only From ever used, so no visitor's address can be
 *     forged from a form field.
 */
function send_mail($to, $subject, $html, $plain = '')
{
    $mail = new PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->XMailer = 'BUBAPC website';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);

        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $plain !== ''
            ? $plain
            : trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));

        if (MAIL_HOST !== '' && MAIL_HOST !== 'localhost') {
            /* Provide an SMTP server, as a live host would. Localhost is
               deliberately left to sendmail, because Postfix on this machine
               is faster and more honest at the loopback than a fake relay.

               STARTTLS is only asked for when there is a username. That is
               the signal that MAIL_HOST is somebody else's server rather
               than the local one: a provider expects TLS, while Postfix on the
               loopback answers with a certificate it made for itself, which
               this code cannot verify, and the send would fail on a warning
               nobody would ever read. */
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->Port = (int) MAIL_PORT;
            $mail->SMTPAuth = MAIL_USER !== '';
            if ($mail->SMTPAuth) {
                $mail->Username = MAIL_USER;
                $mail->Password = MAIL_PASS;
                $mail->SMTPAutoTLS = true;
            } else {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure = '';
            }
            $mail->Timeout = 10;
        }

        $mail->send();
        return true;
    } catch (Exception $problem) {
        // Never let a mail failure take down a page. The caller decides
        // what a failed send means; here it simply means "did not send".
        return false;
    }
}

/**
 * The HTML shell every message is wrapped in, because repeating a full table
 * layout three times in three helpers below would guarantee they drift apart.
 *
 * Mail clients throw away almost all styling and several of them refuse <style>
 * outright, so the only things here are inline attributes. The look follows the
 * website: the dark band with the gold accent, the same rounded card.
 *
 * $body_html is already-escaped HTML, because a password link has to be a real
 * <a href> and escaping the whole message would print the tag instead of
 * following it. Everything that goes into $body_html must go through e() first,
 * which is what the helpers below do.
 */
function mail_layout($body_html)
{
    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e(SITE_NAME) . '</title></head>'
        . '<body style="margin:0;padding:24px;background:#f5f6fb;font-family:Arial,Helvetica,sans-serif;color:#1b1f3b">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
        . 'style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;border:1px solid #ecebf5">'
        . '<tr><td style="padding:16px 24px;background:#1b1f3b;border-radius:16px 16px 0 0">'
        . '<strong style="color:#ffffff;font-size:16px">' . e(SITE_NAME) . '</strong></td></tr>'
        . '<tr><td style="padding:24px;font-size:15px;line-height:1.6">' . $body_html . '</td></tr>'
        . '<tr><td style="padding:12px 24px;background:#f9f9fb;border-radius:0 0 16px 16px;color:#7a7f99;font-size:12px">'
        . e(SITE_FULL_NAME) . '</td></tr>'
        . '</table></body></html>';
}

/**
 * Turn a plain paragraph of text into HTML: the lines are escaped, and a line
 * that is only a web address becomes a link you can tap.
 *
 * This is what keeps a reset link usable. Escaping the whole message, as a
 * simpler version of this file did, printed the address as visible text with
 * <br /> at the end of it: readable, but not clickable, and impossible for
 * somebody reading on a phone to type correctly.
 *
 * Only a line that begins with http:// or https:// is linked, so a sentence
 * that merely mentions a website is never turned into one.
 */
function mail_paragraphs($text)
{
    $html = '';

    foreach (preg_split('/\n\s*\n/', trim((string) $text)) as $paragraph) {
        $lines = '';
        foreach (explode("\n", rtrim($paragraph)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('#^https?://\S+$#', $line) === 1) {
                $lines .= '<p style="margin:0 0 12px"><a href="' . e($line) . '" '
                    . 'style="color:#33419c">' . e($line) . '</a></p>';
                continue;
            }
            $lines .= '<p style="margin:0 0 12px">' . e($line) . '</p>';
        }
        if ($lines !== '') {
            $html .= $lines;
        }
    }

    return $html;
}

/**
 * Send a password reset link.
 *
 * $user is a users row and $token_hash is the SHA-256 hash in the tokens
 * table. The link carries the hash (checked by reset), never the
 * password and never a token a stranger could read back into text.
 */
function mail_password_reset($user, $token_hash)
{
    $name = trim((string) ($user['full_name'] ?? ''));
    if ($name === '') {
        $name = $user['username'] ?? 'there';
    }

    $reset_url = url_raw('reset?token=' . rawurlencode($token_hash));

    $plain = 'Hello ' . $name . ",\n\n"
        . 'Someone asked to reset the password for your ' . SITE_NAME . ' account. '
        . "If that was you, open the link below. It works once and expires after "
        . TOKEN_RESET_HOURS . ' hours:' . "\n\n"
        . $reset_url . "\n\n"
        . "If it was not you, you can ignore this email - your password has not been changed.";

    $html = mail_layout(
        '<p style="margin:0 0 14px">Hello ' . e($name) . ',</p>'
        . '<p style="margin:0 0 14px">Someone asked to reset the password for your '
        . e(SITE_NAME) . ' account. If that was you, open the link below. It works '
        . 'once and expires after ' . e(TOKEN_RESET_HOURS) . ' hours.</p>'
        . '<p style="margin:0 0 14px"><a href="' . e($reset_url) . '" '
        . 'style="display:inline-block;background:#33419c;color:#ffffff;text-decoration:none;'
        . 'padding:12px 20px;border-radius:10px;font-weight:bold">Choose a new password</a></p>'
        . '<p style="margin:0 0 14px;font-size:13px;color:#626a8a">If the button does not work, '
        . 'copy this address into your browser:<br>' . e($reset_url) . '</p>'
        . '<p style="margin:0;color:#626a8a;font-size:13px">If it was not you, you can ignore '
        . 'this email. Your password has not been changed.</p>'
    );

    return send_mail($user['email'], 'Reset your ' . SITE_NAME . ' password', $html, $plain);
}

/**
 * Send a contact-form message to the church mailbox.
 *
 * The message is already saved in the messages table when this runs; email
 * is only so the pastor sees it without opening the admin panel. $sender is
 * the address the visitor typed, kept in the body so the reply address is
 * not lost if the mail server mangles the headers. $phone is optional and is
 * only printed when the visitor gave one.
 */
function mail_contact_notice($name, $sender, $phone, $subject, $message)
{
    $subject = trim((string) $subject);
    if ($subject === '') {
        $subject = '(no subject)';
    }

    $plain = 'New message from the contact form:' . "\n\n"
        . 'From:     ' . $name . "\n"
        . 'Reply to: ' . $sender . "\n"
        . ($phone !== '' ? 'Phone:    ' . $phone . "\n" : '')
        . 'Subject:  ' . $subject . "\n\n"
        . str_repeat('-', 48) . "\n\n"
        . $message . "\n";

    /* The reply address is printed in the body as well as used in the headers.
       That is deliberate: a mail program that rewrites the reply-to, or a
       forward through somebody else's mailbox, then still leaves the person
       who wrote able to answer. */
    $html = mail_layout(
        '<p style="margin:0 0 14px;font-weight:bold">A new message has come in from the '
        . 'contact form.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px">'
        . '<tr><td style="padding:2px 12px 2px 0;color:#626a8a">From</td><td>' . e($name) . '</td></tr>'
        . '<tr><td style="padding:2px 12px 2px 0;color:#626a8a">Reply to</td><td>'
        . e($sender) . '</td></tr>'
        . ($phone !== ''
            ? '<tr><td style="padding:2px 12px 2px 0;color:#626a8a">Phone</td><td>'
                . e($phone) . '</td></tr>'
            : '')
        . '<tr><td style="padding:2px 12px 2px 0;color:#626a8a">Subject</td><td>'
        . e($subject) . '</td></tr></table>'
        . '<hr style="border:none;border-top:1px solid #e6e8f0;margin:16px 0">'
        . mail_paragraphs($message)
    );

    return send_mail(MAIL_ADMIN, 'Contact message: ' . $subject, $html, $plain);
}

/**
 * Send one day's devotional to one person on the list.
 *
 * $devotional is a row from the devotionals table, so every word is written by
 * the church and never by a visitor; the address is the only thing a person
 * supplied. The mail repeats the message and points back to the same devotional
 * on the website, where the reading bar, the sharing buttons and the whole
 * archive live.
 */
function mail_devotional($to, $devotional)
{
    $title = (string) ($devotional['title'] ?? '');
    $date = date('l, j F Y', strtotime((string) $devotional['devotional_date']));
    $link = url_raw('devotionals/' . $devotional['slug']);
    $verse = trim((string) ($devotional['scripture_text'] ?? ''));
    $reference = trim((string) ($devotional['scripture_reference'] ?? ''));
    $message = trim((string) ($devotional['message'] ?? ''));

    /* The plain-text copy is written out by hand rather than stripped from the
       HTML, so a member reading on a phone still gets the verse set apart from
       the message and the link on a line of its own that can be tapped. */
    $plain = SITE_NAME . ' devotional for ' . $date . "\n\n" . $title . "\n\n";

    if ($verse !== '') {
        $plain .= '"' . $verse . '"' . ($reference !== '' ? ' - ' . $reference : '') . "\n\n";
    } elseif ($reference !== '') {
        $plain .= $reference . "\n\n";
    }

    $plain .= $message . "\n\n";

    $study = trim((string) ($devotional['further_study'] ?? ''));
    if ($study !== '') {
        $plain .= "Further study:\n" . $study . "\n\n";
    }

    $plain .= 'Read it on the website: ' . $link . "\n\n"
        . 'You are on the ' . SITE_NAME . ' daily devotional list. If you would '
        . 'rather not receive it, reply to this email and we will take your address off the list.';

    /* The verse is set in the same gold panel the website uses. Mail programs
       throw away stylesheets, so the panel is a small table with the colours
       written on it. */
    $scripture = '';
    if ($verse !== '' || $reference !== '') {
        $scripture = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
            . 'style="margin:0 0 18px;background:#fdf7e3;border-left:4px solid #e0a800;border-radius:12px">'
            . '<tr><td style="padding:14px 16px">';
        if ($verse !== '') {
            $scripture .= '<p style="margin:0 0 8px;font-style:italic">&ldquo;' . e($verse) . '&rdquo;</p>';
        }
        if ($reference !== '') {
            $scripture .= '<p style="margin:0;font-weight:bold;color:#8a6d00;font-size:13px">' . e($reference) . '</p>';
        }
        $scripture .= '</td></tr></table>';
    }

    $study_html = '';
    if ($study !== '') {
        $items = '';
        foreach (preg_split('/\r\n|\r|\n/', $study) as $line) {
            if (trim($line) !== '') {
                $items .= '<li style="margin:0 0 4px">' . e(trim($line)) . '</li>';
            }
        }
        if ($items !== '') {
            $study_html = '<h3 style="margin:22px 0 8px;font-size:16px">Further study</h3>'
                . '<ul style="margin:0;padding:0 0 0 18px;color:#626a8a;font-size:14px">' . $items . '</ul>';
        }
    }

    $html = mail_layout(
        '<p style="margin:0 0 6px;text-transform:uppercase;letter-spacing:.04em;font-size:12px;'
            . 'color:#8a6d00;font-weight:bold">' . e(SITE_NAME) . ' devotional for ' . e($date) . '</p>'
        . '<h1 style="margin:0 0 16px;font-size:22px">' . e($title) . '</h1>'
        . $scripture
        . mail_paragraphs($message)
        . $study_html
        . '<p style="margin:22px 0 0"><a href="' . e($link) . '" '
            . 'style="display:inline-block;background:#1b1f3b;color:#ffffff;text-decoration:none;'
            . 'padding:11px 18px;border-radius:10px;font-weight:bold">Read on the website</a></p>'
        . '<p style="margin:18px 0 0;color:#626a8a;font-size:13px">You are receiving this because '
            . 'you are on the ' . e(SITE_NAME) . ' daily devotional list. If you would rather not '
            . 'receive it, reply to this email and we will take your address off the list.</p>'
    );

    return send_mail($to, 'Today\'s devotional: ' . $title, $html, $plain);
}
