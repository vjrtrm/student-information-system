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
}
