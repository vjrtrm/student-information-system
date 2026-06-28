<?php
namespace App\Helpers;

use App\Models\NotificationErrorLog;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Reads unsent notification_events rows and delivers them via PHPMailer.
 * Stateless and idempotent: safe to call multiple times.
 *
 * PHPMailer is obtained via a factory callable so tests can inject a stub:
 *   NotificationProcessor::setMailerFactory(fn() => $mockMailer);
 */
class NotificationProcessor
{
    private static ?\Closure $mailerFactory = null;

    /** Override for testing — inject a PHPMailer stub. Reset to null after test. */
    public static function setMailerFactory(?\Closure $factory): void
    {
        self::$mailerFactory = $factory;
    }

    /**
     * Process unsent notification events, optionally scoped to one department.
     *
     * @param int|null $deptId  null = all departments (institution_admin)
     * @return array ['sent' => int, 'skipped' => int, 'failed' => int]
     */
    public static function process(?int $deptId = null): array
    {
        $maxPerRun = (int)(Config::get('mail.max_per_run') ?? 200);
        $baseUrl   = rtrim((string)(Config::get('app.base_url') ?? ''), '/');

        $params    = [];
        $deptWhere = '';
        if ($deptId !== null) {
            $deptWhere = 'AND s.department_id = ?';
            $params[]  = $deptId;
        }
        $params[] = $maxPerRun;

        $events = Db::select(
            "SELECT ne.*, s.department_id AS student_dept_id
             FROM notification_events ne
             JOIN students s ON s.id = ne.student_id
             WHERE ne.sent_at IS NULL {$deptWhere}
             ORDER BY ne.created_at ASC
             LIMIT ?",
            $params
        );

        $sent    = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($events as $event) {
            $eventId = (int)$event['id'];

            // Resolve recipient email addresses
            $emails = self::resolveEmails($event);
            if (empty($emails)) {
                // resolveEmails already logged the skip reason
                $skipped++;
                continue;
            }

            $subject = EmailTemplate::getSubject($event['event_key'], $event['recipient_type']);
            $body    = EmailTemplate::getBody($event['event_key'], $event['recipient_type'], $baseUrl);

            $anyFailure = false;
            foreach ($emails as $address) {
                try {
                    $mail = self::makeMailer();
                    $mail->addAddress($address);
                    $mail->Subject = $subject;
                    $mail->Body    = $body;
                    $mail->send();
                } catch (\Throwable $e) {
                    NotificationErrorLog::record($eventId, substr($e->getMessage(), 0, 1000));
                    $anyFailure = true;
                }
            }

            if ($anyFailure) {
                $failed++;
                // sent_at left NULL — full retry next run
            } else {
                Db::execute(
                    "UPDATE notification_events SET sent_at = ? WHERE id = ?",
                    [date('Y-m-d H:i:s'), $eventId]
                );
                $sent++;
            }
        }

        return compact('sent', 'skipped', 'failed');
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * Resolve one or more email addresses for an event.
     * Returns [] and logs if no address can be found.
     */
    private static function resolveEmails(array $event): array
    {
        $eventId   = (int)$event['id'];
        $studentId = (int)$event['student_id'];
        $deptId    = (int)$event['student_dept_id'];

        if ($event['recipient_type'] === 'student') {
            $row = Db::selectOne(
                "SELECT student_email FROM student_profiles WHERE student_id = ?",
                [$studentId]
            );
            $email = trim((string)($row['student_email'] ?? ''));
            if ($email === '') {
                NotificationErrorLog::record($eventId, 'No student email on file.');
                return [];
            }
            return [$email];
        }

        if ($event['recipient_type'] === 'dept_admin') {
            $rows = Db::selectAll(
                "SELECT email FROM users
                 WHERE department_id = ? AND role = 'dept_admin' AND status = 'active'",
                [$deptId]
            );
            $emails = array_filter(array_column($rows, 'email'));
            if (empty($emails)) {
                NotificationErrorLog::record($eventId, 'No active dept admin found for department ' . $deptId . '.');
                return [];
            }
            return array_values($emails);
        }

        // Unknown recipient_type — skip
        NotificationErrorLog::record($eventId, "Unknown recipient_type: '{$event['recipient_type']}'.");
        return [];
    }

    private static function makeMailer(): object
    {
        if (self::$mailerFactory !== null) {
            return (self::$mailerFactory)();
        }

        $mail             = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = (string)(Config::get('mail.host') ?? '');
        $mail->SMTPAuth   = true;
        $mail->Username   = (string)(Config::get('mail.username') ?? '');
        $mail->Password   = (string)(Config::get('mail.password') ?? '');
        $mail->SMTPSecure = (string)(Config::get('mail.encryption', 'tls') ?? 'tls');
        $mail->Port       = (int)(Config::get('mail.port', 587) ?? 587);
        $mail->setFrom(
            (string)(Config::get('mail.from_address') ?? ''),
            (string)(Config::get('mail.from_name', 'SIS') ?? 'SIS')
        );
        $mail->isHTML(false);
        return $mail;
    }
}
