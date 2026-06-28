<?php
namespace App\Helpers;

/**
 * Plain-text email templates for M7 notifications.
 * PII rule: bodies contain ONLY neutral text and a /login link — no names,
 * mobiles, Aadhaar numbers, or any personal data.
 */
class EmailTemplate
{
    /** Map of [event_key][recipient_type] => subject string. */
    private const SUBJECTS = [
        'submission_approved' => [
            'student'    => 'Your form submission has been approved — {system_name}',
            'dept_admin' => 'Student submission approved — {system_name}',
        ],
        'rtc_created_by_student' => [
            'dept_admin' => 'New change request pending review — {system_name}',
        ],
        'rtc_created_by_staff' => [
            'student'    => 'A change request has been raised on your record — {system_name}',
            'dept_admin' => 'New staff-initiated change request pending — {system_name}',
        ],
        'rtc_approved' => [
            'student'    => 'Your change request has been approved — {system_name}',
            'dept_admin' => 'Change request approved — {system_name}',
        ],
        'rtc_rejected' => [
            'student'    => 'Your change request could not be approved — {system_name}',
            'dept_admin' => 'Change request rejected — {system_name}',
        ],
    ];

    /** Map of [event_key][recipient_type] => body string (uses {link} placeholder). */
    private const BODIES = [
        'submission_approved' => [
            'student'    => "Your information form submission has been approved by the department.\n\nLog in to view your approved record:\n{link}\n\nThis is an automated message. Please do not reply.",
            'dept_admin' => "A student submission in your department has been approved.\n\nLog in to view the approvals queue:\n{link}\n\nThis is an automated message. Please do not reply.",
        ],
        'rtc_created_by_student' => [
            'dept_admin' => "A student has submitted a change request that requires your review.\n\nLog in to view pending requests:\n{link}\n\nThis is an automated message. Please do not reply.",
        ],
        'rtc_created_by_staff' => [
            'student'    => "A staff member has submitted a change request on your information record. The request is pending review.\n\nLog in to view your change request history:\n{link}\n\nThis is an automated message. Please do not reply.",
            'dept_admin' => "A staff-initiated change request has been submitted in your department and requires review.\n\nLog in to view pending requests:\n{link}\n\nThis is an automated message. Please do not reply.",
        ],
        'rtc_approved' => [
            'student'    => "Your change request has been reviewed and the requested changes have been applied to your information form.\n\nLog in to verify your updated record:\n{link}\n\nThis is an automated message. Please do not reply.",
            'dept_admin' => "A change request in your department has been approved and the changes have been applied.\n\nLog in to view the record:\n{link}\n\nThis is an automated message. Please do not reply.",
        ],
        'rtc_rejected' => [
            'student'    => "Your change request has been reviewed and could not be approved at this time. Log in to view the reason and submit a new request if needed:\n{link}\n\nThis is an automated message. Please do not reply.",
            'dept_admin' => "A change request in your department has been rejected.\n\nLog in to view the details:\n{link}\n\nThis is an automated message. Please do not reply.",
        ],
    ];

    /**
     * @throws \InvalidArgumentException for unknown event_key + recipientType combination
     */
    public static function getSubject(string $eventKey, string $recipientType): string
    {
        $subject = self::SUBJECTS[$eventKey][$recipientType] ?? null;
        if ($subject === null) {
            throw new \InvalidArgumentException(
                "No email subject defined for event '{$eventKey}' / recipient '{$recipientType}'."
            );
        }
        $systemName = Config::get('mail.system_name', 'SIS');
        return str_replace('{system_name}', $systemName, $subject);
    }

    /**
     * @param string $baseUrl e.g. 'https://college.edu/sis' — link appended with /login
     * @throws \InvalidArgumentException for unknown event_key + recipientType combination
     */
    public static function getBody(string $eventKey, string $recipientType, string $baseUrl): string
    {
        $body = self::BODIES[$eventKey][$recipientType] ?? null;
        if ($body === null) {
            throw new \InvalidArgumentException(
                "No email body defined for event '{$eventKey}' / recipient '{$recipientType}'."
            );
        }
        $link = rtrim($baseUrl, '/') . '/login';
        return str_replace('{link}', $link, $body);
    }
}
