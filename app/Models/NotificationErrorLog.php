<?php
namespace App\Models;

use App\Helpers\Db;

/**
 * Persists failed notification send attempts.
 * A failed event (sent_at = NULL) is retried on every subsequent Send Now run.
 */
class NotificationErrorLog
{
    public static function record(int $eventId, string $message): void
    {
        Db::execute(
            "INSERT INTO notification_error_log (notification_event_id, error_message, attempted_at)
             VALUES (?, ?, ?)",
            [$eventId, $message, date('Y-m-d H:i:s')]
        );
    }

    /**
     * Fetch error log rows for a department (via notification_events → students join).
     * Pass null $deptId for institution_admin (all departments).
     */
    public static function findByDept(?int $deptId, int $limit = 50, int $offset = 0): array
    {
        $params    = [];
        $deptWhere = '';
        if ($deptId !== null) {
            $deptWhere = 'AND s.department_id = ?';
            $params[]  = $deptId;
        }
        $params[] = $limit;
        $params[] = $offset;

        return Db::selectAll(
            "SELECT nel.id, nel.notification_event_id, nel.error_message, nel.attempted_at,
                    ne.event_key, ne.recipient_type, ne.sent_at
             FROM notification_error_log nel
             JOIN notification_events ne ON ne.id = nel.notification_event_id
             JOIN students            s  ON s.id  = ne.student_id
             WHERE 1=1 {$deptWhere}
             ORDER BY nel.attempted_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    /**
     * Count distinct failed event IDs for a department.
     * Used for the badge on the notifications page.
     */
    public static function countByDept(?int $deptId): int
    {
        $params    = [];
        $deptWhere = '';
        if ($deptId !== null) {
            $deptWhere = 'AND s.department_id = ?';
            $params[]  = $deptId;
        }
        $row = Db::selectOne(
            "SELECT COUNT(DISTINCT nel.notification_event_id) AS cnt
             FROM notification_error_log nel
             JOIN notification_events ne ON ne.id = nel.notification_event_id
             JOIN students            s  ON s.id  = ne.student_id
             WHERE ne.sent_at IS NULL {$deptWhere}",
            $params
        );
        return (int)($row['cnt'] ?? 0);
    }
}
