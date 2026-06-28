<?php
namespace App\Models;

use App\Helpers\Db;

class DuplicateOverrideRequest
{
    public static function create(array $data): int
    {
        Db::execute(
            "INSERT INTO duplicate_override_requests
                (upload_batch_id, source_row_number, student_data, flagged_reason,
                 existing_student_id, requested_by, reason_note, status)
             VALUES (?,?,?,?,?,?,'','pending')",
            [
                $data['upload_batch_id'] ?? null,
                $data['source_row_number'] ?? null,
                json_encode($data['student_data']),
                $data['flagged_reason'],
                $data['existing_student_id'],
                $data['requested_by'],
            ]
        );
        return (int)Db::conn()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM duplicate_override_requests WHERE id = ?", [$id]);
    }

    public static function findPendingByBatch(int $batchId): array
    {
        return Db::select(
            "SELECT d.*, s.first_name AS ex_first, s.last_name AS ex_last, s.mobile AS ex_mobile
             FROM duplicate_override_requests d
             JOIN students s ON s.id = d.existing_student_id
             WHERE d.upload_batch_id = ? AND d.status = 'pending'",
            [$batchId]
        );
    }

    public static function findPendingByDept(int $deptId): array
    {
        return Db::select(
            "SELECT d.*, s.first_name AS ex_first, s.last_name AS ex_last, s.mobile AS ex_mobile,
                    u.name AS requester_name
             FROM duplicate_override_requests d
             JOIN students s ON s.id = d.existing_student_id
             JOIN users u ON u.id = d.requested_by
             WHERE d.status = 'pending'
               AND EXISTS (
                   SELECT 1 FROM upload_batches ub
                   WHERE ub.id = d.upload_batch_id AND ub.department_id = ?
               )",
            [$deptId]
        );
    }

    public static function countPendingByDept(int $deptId): int
    {
        $row = Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM duplicate_override_requests d
             WHERE d.status = 'pending'
               AND EXISTS (SELECT 1 FROM upload_batches ub WHERE ub.id = d.upload_batch_id AND ub.department_id = ?)",
            [$deptId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public static function updateReasonAndStatus(int $id, string $reasonNote, string $status): void
    {
        Db::execute(
            "UPDATE duplicate_override_requests SET reason_note = ?, status = ? WHERE id = ?",
            [$reasonNote, $status, $id]
        );
    }

    public static function approve(int $id, int $adminId): void
    {
        Db::execute(
            "UPDATE duplicate_override_requests SET status = 'approved', reviewed_by = ?, reviewed_at = ? WHERE id = ?",
            [$adminId, date('Y-m-d H:i:s'), $id]
        );
    }

    public static function reject(int $id, int $adminId): void
    {
        Db::execute(
            "UPDATE duplicate_override_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = ? WHERE id = ?",
            [$adminId, date('Y-m-d H:i:s'), $id]
        );
    }
}
