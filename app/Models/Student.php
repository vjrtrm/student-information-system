<?php
namespace App\Models;

use App\Helpers\Db;

class Student
{
    // ── M1 Auth methods (kept) ────────────────────────────────────────────

    /** Used by login flow. Returns null if mobile not unique or not found. */
    public static function findByMobile(string $mobile): ?array
    {
        $rows = Db::select(
            "SELECT * FROM students WHERE mobile = ? AND login_enabled = 1 LIMIT 2",
            [$mobile]
        );
        return count($rows) === 1 ? $rows[0] : null;
    }

    public static function find(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM students WHERE id = ? LIMIT 1", [$id]);
    }

    public static function isActive(array $student): bool
    {
        return ($student['status'] ?? 'active') === 'active';
    }

    // ── M3 Onboarding methods ─────────────────────────────────────────────

    /** Duplicate check by mobile (no login_enabled filter — check ALL records). */
    public static function findAnyByMobile(string $mobile): ?array
    {
        return Db::selectOne("SELECT * FROM students WHERE mobile = ? LIMIT 1", [$mobile]);
    }

    /** Duplicate check by name+DOB. */
    public static function findByNameDob(string $firstName, string $lastName, string $dob): ?array
    {
        return Db::selectOne(
            "SELECT * FROM students WHERE first_name = ? AND last_name = ? AND dob = ? LIMIT 1",
            [trim($firstName), trim($lastName), $dob]
        );
    }

    /**
     * Create a new student record (onboarding).
     * $data keys: first_name, last_name, dob, mobile, gender, department_id,
     *             programme_level, academic_year_id, class_id, section_id,
     *             admission_date, created_by, upload_batch_id
     */
    public static function create(array $data): int
    {
        Db::execute(
            "INSERT INTO students
                (first_name, last_name, dob, mobile, gender, department_id, programme_level,
                 academic_year_id, class_id, section_id, admission_date,
                 onboarding_status, login_enabled, created_by, upload_batch_id, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending_enrolment',0,?,?,?)",
            [
                trim($data['first_name']),
                trim($data['last_name']),
                $data['dob'],
                $data['mobile'],
                $data['gender'],
                $data['department_id'],
                $data['programme_level'],
                $data['academic_year_id'],
                $data['class_id'],
                $data['section_id'] ?: null,
                $data['admission_date'],
                $data['created_by'],
                $data['upload_batch_id'] ?: null,
                date('Y-m-d H:i:s'),
            ]
        );
        return (int)Db::conn()->lastInsertId();
    }

    public static function updateStatus(int $id, string $status): void
    {
        Db::execute("UPDATE students SET onboarding_status = ? WHERE id = ?", [$status, $id]);
    }

    public static function enableLogin(int $id): void
    {
        Db::execute("UPDATE students SET login_enabled = 1 WHERE id = ?", [$id]);
    }

    /**
     * Paginated list with optional filters.
     * $filters: department_id, onboarding_status, academic_year_id, search (name|mobile)
     */
    public static function getList(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        [$where, $params] = self::buildWhere($filters);
        $offset = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;
        return Db::select(
            "SELECT s.*, d.name AS dept_name, d.code AS dept_code
             FROM students s
             LEFT JOIN departments d ON d.id = s.department_id
             {$where}
             ORDER BY s.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public static function countList(array $filters = []): int
    {
        [$where, $params] = self::buildWhere($filters);
        $row = Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM students s {$where}",
            $params
        );
        return (int)($row['cnt'] ?? 0);
    }

    private static function buildWhere(array $filters): array
    {
        $clauses = [];
        $params  = [];
        if (!empty($filters['department_id'])) {
            $clauses[] = 's.department_id = ?';
            $params[]  = $filters['department_id'];
        }
        if (!empty($filters['onboarding_status'])) {
            $clauses[] = 's.onboarding_status = ?';
            $params[]  = $filters['onboarding_status'];
        }
        if (!empty($filters['academic_year_id'])) {
            $clauses[] = 's.academic_year_id = ?';
            $params[]  = $filters['academic_year_id'];
        }
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $clauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.mobile LIKE ?)";
            $params = array_merge($params, [$like, $like, $like]);
        }
        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
        return [$where, $params];
    }

    /** Summary counts by onboarding_status, grouped by department. For institution admin summary. */
    public static function summaryByDept(?int $academicYearId = null): array
    {
        $where = $academicYearId ? 'WHERE s.academic_year_id = ?' : '';
        $params = $academicYearId ? [$academicYearId] : [];
        return Db::select(
            "SELECT d.name AS dept_name, d.id AS dept_id,
                    s.onboarding_status,
                    COUNT(*) AS cnt
             FROM students s
             JOIN departments d ON d.id = s.department_id
             {$where}
             GROUP BY d.id, s.onboarding_status
             ORDER BY d.name",
            $params
        );
    }

    // ── M4 Enrolment methods ──────────────────────────────────────────────

    /** Students eligible for enrolment number generation:
     *  onboarding_status = 'pending_enrolment' AND enrolment_approval_status IS NULL */
    public static function findPendingForGeneration(int $deptId, int $ayId): array
    {
        return Db::select(
            "SELECT * FROM students
             WHERE department_id = ? AND academic_year_id = ?
               AND onboarding_status = 'pending_enrolment'
               AND enrolment_approval_status IS NULL
             ORDER BY id",
            [$deptId, $ayId]
        );
    }

    /** True if any student in dept+year has enrolment_approval_status = 'pending'. */
    public static function hasPendingBatch(int $deptId, int $ayId): bool
    {
        $row = Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM students
             WHERE department_id = ? AND academic_year_id = ?
               AND enrolment_approval_status = 'pending'",
            [$deptId, $ayId]
        );
        return (int)($row['cnt'] ?? 0) > 0;
    }

    /** Get highest used serial for a dept+year (across all batches). */
    public static function maxSerial(int $deptId, int $ayId): int
    {
        $row = Db::selectOne(
            "SELECT COALESCE(MAX(enrolment_serial), 0) AS max_serial
             FROM students
             WHERE department_id = ? AND academic_year_id = ?
               AND enrolment_serial IS NOT NULL",
            [$deptId, $ayId]
        );
        return (int)($row['max_serial'] ?? 0);
    }

    /** Assign provisional enrolment number to a student (within a generation transaction). */
    public static function assignEnrolmentNumber(
        int $id, string $number, int $serial, int $batchId
    ): void {
        Db::execute(
            "UPDATE students
             SET enrolment_number = ?, enrolment_serial = ?,
                 enrolment_approval_status = 'pending', enrolment_batch_id = ?
             WHERE id = ?",
            [$number, $serial, $batchId, $id]
        );
    }

    /**
     * Bulk-approve enrolment numbers for the given student IDs.
     * Only updates rows that are still 'pending' and belong to $batchId.
     * Returns count of rows actually updated.
     */
    public static function approveNumbers(array $ids, int $batchId, int $approvedBy): int
    {
        if (empty($ids)) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // params order: approvedBy, approvedAt, then ids for IN(), then batchId
        $params = array_merge(
            [$approvedBy, date('Y-m-d H:i:s')],
            $ids,
            [$batchId]
        );
        return Db::execute(
            "UPDATE students
             SET enrolment_approval_status = 'approved',
                 onboarding_status          = 'enrolment_assigned',
                 enrolment_approved_by      = ?,
                 enrolment_approved_at      = ?
             WHERE id IN ({$placeholders})
               AND enrolment_batch_id = ?
               AND enrolment_approval_status = 'pending'",
            $params
        );
    }

    /** Returns ['number' => string|null, 'status' => string|null] for student dashboard. */
    public static function getEnrolmentStatus(int $id): array
    {
        $row = Db::selectOne(
            "SELECT enrolment_number, enrolment_approval_status FROM students WHERE id = ?",
            [$id]
        );
        return [
            'number' => ($row['enrolment_approval_status'] === 'approved')
                        ? $row['enrolment_number'] : null,
            'status' => $row['enrolment_approval_status'] ?? null,
        ];
    }

    /** Paginated student list for a batch detail view. */
    public static function findByBatch(int $batchId, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        [$where, $params] = self::buildBatchWhere($batchId, $filters);
        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;
        return Db::select(
            "SELECT s.*,
                    ov_ay.display AS ay_display,
                    ov_cl.display AS class_display,
                    ov_sc.display AS section_display,
                    u.name        AS approved_by_name
             FROM students s
             LEFT JOIN option_values ov_ay ON ov_ay.id = s.academic_year_id
             LEFT JOIN option_values ov_cl ON ov_cl.id = s.class_id
             LEFT JOIN option_values ov_sc ON ov_sc.id = s.section_id
             LEFT JOIN users u             ON u.id     = s.enrolment_approved_by
             {$where}
             ORDER BY s.enrolment_serial ASC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public static function countByBatch(int $batchId, array $filters = []): int
    {
        [$where, $params] = self::buildBatchWhere($batchId, $filters);
        $row = Db::selectOne("SELECT COUNT(*) AS cnt FROM students s {$where}", $params);
        return (int)($row['cnt'] ?? 0);
    }

    private static function buildBatchWhere(int $batchId, array $filters): array
    {
        $clauses = ["s.enrolment_batch_id = ?"];
        $params  = [$batchId];
        if (!empty($filters['search'])) {
            $like      = '%' . $filters['search'] . '%';
            $clauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.mobile LIKE ?)";
            $params    = array_merge($params, [$like, $like, $like]);
        }
        if (!empty($filters['enrolment_approval_status'])) {
            $clauses[] = "s.enrolment_approval_status = ?";
            $params[]  = $filters['enrolment_approval_status'];
        }
        return ["WHERE " . implode(" AND ", $clauses), $params];
    }
}
