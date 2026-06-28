<?php
namespace App\Models;

use App\Helpers\Db;

class EnrolmentBatch
{
    public static function create(int $deptId, int $ayId, int $generatedBy): int
    {
        Db::execute(
            "INSERT INTO enrolment_batches (department_id, academic_year_id, generated_by, student_count, created_at)
             VALUES (?, ?, ?, 0, ?)",
            [$deptId, $ayId, $generatedBy, date('Y-m-d H:i:s')]
        );
        return (int)Db::conn()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        return Db::selectOne(
            "SELECT b.*, d.name AS dept_name, d.code AS dept_code, d.level AS dept_level,
                    ov.value AS ay_value, ov.display AS ay_display,
                    u.name AS generated_by_name
             FROM enrolment_batches b
             JOIN departments d   ON d.id  = b.department_id
             JOIN option_values ov ON ov.id = b.academic_year_id
             JOIN users u          ON u.id  = b.generated_by
             WHERE b.id = ?",
            [$id]
        );
    }

    public static function findByDept(int $deptId, array $filters = []): array
    {
        $where  = "WHERE b.department_id = ?";
        $params = [$deptId];
        if (!empty($filters['academic_year_id'])) {
            $where   .= " AND b.academic_year_id = ?";
            $params[] = (int)$filters['academic_year_id'];
        }
        return Db::select(
            "SELECT b.*, d.name AS dept_name, d.code AS dept_code,
                    ov.display AS ay_display,
                    u.name AS generated_by_name,
                    SUM(CASE WHEN s.enrolment_approval_status = 'pending'  THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN s.enrolment_approval_status = 'approved' THEN 1 ELSE 0 END) AS approved_count
             FROM enrolment_batches b
             JOIN departments  d   ON d.id  = b.department_id
             JOIN option_values ov ON ov.id = b.academic_year_id
             JOIN users        u   ON u.id  = b.generated_by
             LEFT JOIN students s  ON s.enrolment_batch_id = b.id
             {$where}
             GROUP BY b.id
             ORDER BY b.created_at DESC",
            $params
        );
    }

    public static function findAll(array $filters = []): array
    {
        $clauses = [];
        $params  = [];
        if (!empty($filters['department_id'])) {
            $clauses[] = "b.department_id = ?";
            $params[]  = (int)$filters['department_id'];
        }
        if (!empty($filters['academic_year_id'])) {
            $clauses[] = "b.academic_year_id = ?";
            $params[]  = (int)$filters['academic_year_id'];
        }
        $where = $clauses ? "WHERE " . implode(" AND ", $clauses) : "";
        return Db::select(
            "SELECT b.*, d.name AS dept_name, d.code AS dept_code,
                    ov.display AS ay_display, u.name AS generated_by_name,
                    SUM(CASE WHEN s.enrolment_approval_status = 'pending'  THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN s.enrolment_approval_status = 'approved' THEN 1 ELSE 0 END) AS approved_count
             FROM enrolment_batches b
             JOIN departments  d   ON d.id  = b.department_id
             JOIN option_values ov ON ov.id = b.academic_year_id
             JOIN users        u   ON u.id  = b.generated_by
             LEFT JOIN students s  ON s.enrolment_batch_id = b.id
             {$where}
             GROUP BY b.id
             ORDER BY b.created_at DESC",
            $params
        );
    }

    /**
     * Derives batch status from its student rows.
     * Returns 'pending' | 'in_progress' | 'approved'
     */
    public static function deriveStatus(int $batchId): string
    {
        $row = Db::selectOne(
            "SELECT
               SUM(CASE WHEN enrolment_approval_status = 'pending'  THEN 1 ELSE 0 END) AS pending_count,
               SUM(CASE WHEN enrolment_approval_status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
               COUNT(*) AS total
             FROM students
             WHERE enrolment_batch_id = ?",
            [$batchId]
        );
        $pending  = (int)($row['pending_count']  ?? 0);
        $approved = (int)($row['approved_count'] ?? 0);
        $total    = (int)($row['total']          ?? 0);
        if ($total === 0 || $pending === 0) return 'approved';
        if ($approved === 0)               return 'pending';
        return 'in_progress';
    }

    public static function updateStudentCount(int $id, int $count): void
    {
        Db::execute("UPDATE enrolment_batches SET student_count = ? WHERE id = ?", [$count, $id]);
    }

    public static function summaryByDept(?int $ayId = null): array
    {
        $where  = $ayId ? "WHERE b.academic_year_id = ?" : "";
        $params = $ayId ? [$ayId] : [];
        return Db::select(
            "SELECT d.id AS dept_id, d.name AS dept_name,
                    COUNT(DISTINCT b.id) AS total_batches,
                    SUM(CASE WHEN s.enrolment_approval_status = 'pending'  THEN 1 ELSE 0 END) AS total_pending,
                    SUM(CASE WHEN s.enrolment_approval_status = 'approved' THEN 1 ELSE 0 END) AS total_approved
             FROM enrolment_batches b
             JOIN departments d ON d.id = b.department_id
             LEFT JOIN students s ON s.enrolment_batch_id = b.id
             {$where}
             GROUP BY d.id
             ORDER BY d.name",
            $params
        );
    }
}
