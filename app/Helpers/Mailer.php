<?php
namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Email via PHPMailer (SMTP). Templates are PII-safe: greeting + code/link only,
 * never DOB, IDs, address, etc. (Foundation §4, Spec 3.7.1).
 */
class Mailer
{
    /** Low-level send. Returns true on success. */
    public static function send(string $toEmail, string $toName, string $subject, string $html): bool
    {
        $cfg = Config::get('mail');

        // Fallback to PHP mail() if PHPMailer isn't installed yet (e.g. before composer install).
        if (!class_exists(PHPMailer::class)) {
            $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
            $headers .= 'From: ' . ($cfg['from_name'] ?? 'SIS') . ' <' . ($cfg['from_email'] ?? '') . ">\r\n";
            return @mail($toEmail, $subject, $html, $headers);
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $cfg['host'];
            $mail->Port       = $cfg['port'];
            if (!empty($cfg['username'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $cfg['username'];
                $mail->Password = $cfg['password'];
            }
            if (!empty($cfg['encryption'])) {
                $mail->SMTPSecure = $cfg['encryption'];
            }
            $mail->setFrom($cfg['from_email'], $cfg['from_name']);
            $mail->addAddress($toEmail, $toName); // per-recipient send (no shared To)
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = strip_tags($html);
            return $mail->send();
        } catch (\Throwable $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    public static function sendLoginOtp(string $email, string $firstName, string $code): bool
    {
        $html = "<p>Hello " . htmlspecialchars($firstName, ENT_QUOTES) . ",</p>"
              . "<p>Your one-time login code is: <strong>{$code}</strong></p>"
              . "<p>It expires in " . (int) Config::get('auth.otp_ttl_minutes', 15) . " minutes. "
              . "If you did not request this, you can ignore this email.</p>";
        return self::send($email, $firstName, 'Your sign-in code', $html);
    }

    public static function sendPasswordReset(string $email, string $firstName, string $link): bool
    {
        $safeLink = htmlspecialchars($link, ENT_QUOTES);
        $html = "<p>Hello " . htmlspecialchars($firstName, ENT_QUOTES) . ",</p>"
              . "<p>We received a request to reset your password. "
              . "<a href=\"{$safeLink}\">Set a new password</a>.</p>"
              . "<p>This link expires in " . (int) Config::get('auth.reset_ttl_minutes', 15) . " minutes. "
              . "If you didn't request it, no action is needed.</p>";
        return self::send($email, $firstName, 'Reset your SIS password', $html);
    }
}
