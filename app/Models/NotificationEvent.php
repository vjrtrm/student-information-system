<?php
namespace App\Models;

use App\Helpers\Db;

/**
 * Records notification events for M7 email/SMS delivery.
 * Payload must contain NO PII — field keys, dept_id, enrolment_serial only.
 * sent_at = NULL until M7 processes the event.
 */
class NotificationEvent
{
    public static function record(
        string $eventKey,
        int    $studentId,
        int    $actorId,
        string $recipientType,
        ?int   $recipientId,
        ?int   $changeRequestId,
        array  $payload
    ): void {
        Db::execute(
            "INSERT INTO notification_events
                (event_key, student_id, actor_id, recipient_type, recipient_id,
                 change_request_id, payload, sent_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?)",
            [
                $eventKey,
                $studentId,
                $actorId,
                $recipientType,
                $recipientId,
                $changeRequestId,
                json_encode($payload),
                date('Y-m-d H:i:s'),
            ]
        );
    }
}
