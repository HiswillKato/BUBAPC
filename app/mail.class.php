<?php
/**
 * BUBAPC - Email handling
 *
 * Single outbound email path using PHPMailer.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mail
{
    /**
     * Send an email.
     */
    public static function send(string $to, string $subject, string $html, string $plain = ''): bool
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
        } catch (Exception) {
            return false;
        }
    }

    /**
     * HTML layout wrapper for emails.
     */
    public static function layout(string $body_html): string
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
     * Convert plain text to HTML paragraphs, auto-linking URLs.
     */
    public static function paragraphs(string $text): string
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
     * Send password reset email.
     */
    public static function passwordReset(array $user, string $token_hash): bool
    {
        $name = trim((string) ($user['full_name'] ?? ''));
        if ($name === '') {
            $name = $user['username'] ?? 'there';
        }

        $reset_url = url('reset.php?token=' . rawurlencode($token_hash));

        $plain = "Hello $name,\n\n"
            . "Someone asked to reset the password for your " . SITE_NAME . " account. "
            . "If that was you, open the link below. It works once and expires after "
            . TOKEN_RESET_HOURS . " hours:\n\n"
            . "$reset_url\n\n"
            . "If it was not you, you can ignore this email - your password has not been changed.";

        $html = self::layout(
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

        return self::send($user['email'], 'Reset your ' . SITE_NAME . ' password', $html, $plain);
    }

    /**
     * Send contact form notification to admin.
     */
    public static function contactNotice(string $name, string $sender, string $subject, string $message): bool
    {
        $subject = trim((string) $subject);
        if ($subject === '') {
            $subject = '(no subject)';
        }

        $plain = "New message from the contact form:\n\n"
            . "From:     $name\n"
            . "Reply to: $sender\n"
            . "Subject:  $subject\n\n"
            . str_repeat('-', 48) . "\n\n"
            . "$message\n";

        $html = self::layout(
            '<p style="margin:0 0 14px;font-weight:bold">A new message has come in from the '
            . 'contact form.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px">'
            . '<tr><td style="padding:2px 12px 2px 0;color:#626a8a">From</td><td>' . e($name) . '</td></tr>'
            . '<tr><td style="padding:2px 12px 2px 0;color:#626a8a">Reply to</td><td>'
            . e($sender) . '</td></tr>'
            . '<tr><td style="padding:2px 12px 2px 0;color:#626a8a">Subject</td><td>'
            . e($subject) . '</td></tr></table>'
            . '<hr style="border:none;border-top:1px solid #e6e8f0;margin:16px 0">'
            . self::paragraphs($message)
        );

        return self::send(MAIL_ADMIN, 'Contact message: ' . $subject, $html, $plain);
    }

    /**
     * Send newsletter to subscriber.
     */
    public static function newsletter(string $to, string $subject, string $content): bool
    {
        $plain = "Hello,\n\n$content\n\n"
            . 'You are receiving this because your address is on the ' . SITE_NAME
            . ' newsletter list. If you no longer want it, the admin panel can '
            . 'switch your address off.';

        $html = self::layout(
            '<p style="margin:0 0 14px">Hello,</p>'
            . self::paragraphs($content)
            . '<p style="margin:16px 0 0;color:#626a8a;font-size:13px">You are receiving this '
            . 'because your address is on the ' . e(SITE_NAME) . ' newsletter list. If you no '
            . 'longer want it, the admin panel can switch your address off.</p>'
        );

        return self::send($to, $subject, $html, $plain);
    }
}