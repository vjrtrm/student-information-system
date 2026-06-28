<?php
namespace App\Models;

use App\Helpers\Db;
use App\Helpers\MasterAuditLogger;
use App\Helpers\RtcUploadHandler;

class ChangeRequest
{
    // ---------------------------------------------------------------
    // Write
    // ---------------------------------------------------------------

    public static function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        Db::execute(
            "INSERT INTO change_requests
                (student_id, department_id, initiated_by, initiator_type,
                 reason, proposed_changes, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)",
            [
                $data['student_id'],
                $data['department_id'],
                $data['initiated_by'],
                $data['initiator_type'],
                $data['reason'],
                is_string($data['proposed_changes'])
                    ? $data['proposed_changes']
                    : json_encode($data['proposed_changes']),
                $now, $now,
            ]
        );
        return (int)Db::conn()->lastInsertId();
    }

    /**
     * Approve an RTC: apply changeset to student_profiles, move temp files,
     * update status, audit log, and fire notification events.
     * All in one transaction.
     */
    public static function approve(int $rtcId, int $reviewedBy): void
    {
        $rtc = self::findById($rtcId);
        if (!$rtc || $rtc['status'] !== 'pending') {
            return; // idempotent
        }

        $changeset  = $rtc['proposed_changes'];
        $studentId  = (int)$rtc['student_id'];
        $now        = date('Y-m-d H:i:s');

        $pdo = Db::conn();
        $pdo->beginTransaction();
        try {
            $fileEntries   = array_values(array_filter($changeset, fn($e) => !empty($e['is_file'])));
            $scalarEntries = array_values(array_filter($changeset, fn($e) => empty($e['is_file'])));

            $committedPaths = [];
            if (!empty($fileEntries)) {
                $committedPaths = RtcUploadHandler::commit($rtcId, $studentId, $fileEntries);
            }

            $applyData = [];
            foreach ($scalarEntries as $entry) {
                $applyData[$entry['field_key']] = $entry['proposed_value'];
            }
            foreach ($committedPaths as $fieldKey => $newPath) {
                $applyData[$fieldKey] = $newPath;
            }

            StudentProfile::applyChangeset($studentId, $applyData);

            Db::execute(
                "UPDATE change_requests
                 SET status = 'approved', reviewed_by = ?, reviewed_at = ?, updated_at = ?
                 WHERE id = ?",
                [$reviewedBy, $now, $now, $rtcId]
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Audit — field keys only, no values
        $changedKeys = array_column($changeset, 'field_key');
        MasterAuditLogger::log('rtc_approved', 'change_request', $rtcId, [
            'student_id'     => $studentId,
            'reviewed_by'    => $reviewedBy,
            'changed_fields' => $changedKeys,
        ]);

        // Notification events
        $payload = [
            'change_request_id' => $rtcId,
            'field_keys'        => $changedKeys,
            'dept_id'           => $rtc['department_id'],
        ];
        NotificationEvent::record('rtc_approved', $studentId, $reviewedBy, 'student',    $studentId, $rtcId, $payload);
        NotificationEvent::record('rtc_approved', $studentId, $reviewedBy, 'dept_admin', null,       $rtcId, $payload);
    }

    /**
     * Reject an RTC: store rejection reason, discard temp files,
     * update status, audit log, and fire notification events.
     */
    public static function reject(int $rtcId, int $reviewedBy, string $reason): void
    {
        $rtc = self::findById($rtcId);
        if (!$rtc || $rtc['status'] !== 'pending') {
            return;
        }

        $studentId = (int)$rtc['student_id'];
        $now       = date('Y-m-d H:i:s');

        Db::execute(
            "UPDATE change_requests
             SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ?
             WHERE id = ?",
            [$reason, $reviewedBy, $now, $now, $rtcId]
        );

        RtcUploadHandler::discard($rtcId);

        MasterAuditLogger::log('rtc_rejected', 'change_request', $rtcId, [
            'student_id'  => $studentId,
            'reviewed_by' => $reviewedBy,
        ]);

        $payload = ['change_request_id' => $rtcId, 'dept_id' => $rtc['department_id']];
        NotificationEvent::record('rtc_rejected', $studentId, $reviewedBy, 'student',    $studentId, $rtcId, $payload);
        NotificationEvent::record('rtc_rejected', $studentId, $reviewedBy, 'dept_admin', null,       $rtcId, $payload);
    }

    // ---------------------------------------------------------------
    // Read
    // ---------------------------------------------------------------

    public static function findById(int $id): ?array
    {
        $row = Db::selectOne(
            "SELECT cr.*,
                    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    s.enrolment_serial,
                    s.department_id AS student_dept_id,
                    u.name AS initiator_name
             FROM change_requests cr
             JOIN students s ON s.id = cr.student_id
             JOIN users    u ON u.id = cr.initiated_by
             WHERE cr.id = ?",
            [$id]
        );
        if ($row && is_string($row['proposed_changes'])) {
            $row['proposed_changes'] = json_decode($row['proposed_changes'], true) ?? [];
        }
        return $row ?: null;
    }

    /** All pending RTCs for a department, oldest first. */
    public static function findPending(int $departmentId): array
    {
        $rows = Db::selectAll(
            "SELECT cr.*,
                    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    s.enrolment_number,
                    u.name AS initiator_name
             FROM change_requests cr
             JOIN students s ON s.id = cr.student_id
             JOIN users    u ON u.id = cr.initiated_by
             WHERE cr.department_id = ? AND cr.status = 'pending'
             ORDER BY cr.created_at ASC",
            [$departmentId]
        );
        foreach ($rows as &$row) {
            if (is_string($row['proposed_changes'])) {
                $row['proposed_changes'] = json_decode($row['proposed_changes'], true) ?? [];
            }
        }
        return $rows;
    }

    /** All pending RTCs across all departments (institution_admin). */
    public static function findAllPending(?int $departmentId = null): array
    {
        $params = [];
        $where  = 'WHERE cr.status = \'pending\'';
        if ($departmentId) {
            $where   .= ' AND cr.department_id = ?';
            $params[] = $departmentId;
        }
        $rows = Db::selectAll(
            "SELECT cr.*,
                    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    s.enrolment_number,
                    d.name AS department_name,
                    u.name AS initiator_name
             FROM change_requests cr
             JOIN students    s ON s.id = cr.student_id
             JOIN departments d ON d.id = cr.department_id
             JOIN users       u ON u.id = cr.initiated_by
             {$where}
             ORDER BY cr.created_at ASC",
            $params
        );
        foreach ($rows as &$row) {
            if (is_string($row['proposed_changes'])) {
                $row['proposed_changes'] = json_decode($row['proposed_changes'], true) ?? [];
            }
        }
        return $rows;
    }

    /** All RTCs for a student (own history), newest first. */
    public static function findByStudent(int $studentId): array
    {
        $rows = Db::selectAll(
            "SELECT cr.*, u.name AS initiator_name
             FROM change_requests cr
             JOIN users u ON u.id = cr.initiated_by
             WHERE cr.student_id = ?
             ORDER BY cr.created_at DESC",
            [$studentId]
        );
        foreach ($rows as &$row) {
            if (is_string($row['proposed_changes'])) {
                $row['proposed_changes'] = json_decode($row['proposed_changes'], true) ?? [];
            }
        }
        return $rows;
    }

    /** True if this student already has a pending RTC. */
    public static function hasPending(int $studentId): bool
    {
        $row = Db::selectOne(
            "SELECT id FROM change_requests WHERE student_id = ? AND status = 'pending' LIMIT 1",
            [$studentId]
        );
        return $row !== null;
    }
}
