<?php
namespace App\Models;

use App\Helpers\Db;

/**
 * Staff/admin user queries for Module 9.
 */
class StaffUser
{
    private static array $whitelist = [
        'name', 'email', 'password_hash', 'role', 'department_id',
        'staff_code', 'status', 'must_change_password',
    ];

    public static function findById(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public static function findByDept(int $deptId): array
    {
        return Db::selectAll(
            "SELECT * FROM users
             WHERE role IN ('staff','dept_admin') AND department_id = ?
             ORDER BY name ASC",
            [$deptId]
        );
    }

    public static function findAll(?int $deptId = null): array
    {
        if ($deptId !== null) {
            return Db::selectAll(
                "SELECT u.*, d.name AS dept_name
                 FROM users u
                 LEFT JOIN departments d ON d.id = u.department_id
                 WHERE u.role IN ('staff','dept_admin') AND u.department_id = ?
                 ORDER BY u.department_id, u.name ASC",
                [$deptId]
            );
        }
        return Db::selectAll(
            "SELECT u.*, d.name AS dept_name
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.role IN ('staff','dept_admin')
             ORDER BY u.department_id, u.name ASC"
        );
    }

    public static function create(array $data): int
    {
        $allowed = array_intersect_key($data, array_flip(self::$whitelist));
        // Always include created_at
        $allowed['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');

        $columns = implode(', ', array_keys($allowed));
        $placeholders = implode(', ', array_fill(0, count($allowed), '?'));

        Db::execute(
            "INSERT INTO users ({$columns}) VALUES ({$placeholders})",
            array_values($allowed)
        );
        return (int)Db::conn()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $allowed = array_intersect_key($data, array_flip(self::$whitelist));
        if (empty($allowed)) return;

        $setParts = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($allowed)));
        $params   = array_values($allowed);
        $params[] = $id;

        Db::execute(
            "UPDATE users SET {$setParts} WHERE id = ?",
            $params
        );
    }

    public static function hasDeptAdmin(int $deptId, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = Db::selectOne(
                "SELECT COUNT(*) AS cnt FROM users
                 WHERE role = 'dept_admin' AND department_id = ? AND id != ?",
                [$deptId, $excludeId]
            );
        } else {
            $row = Db::selectOne(
                "SELECT COUNT(*) AS cnt FROM users
                 WHERE role = 'dept_admin' AND department_id = ?",
                [$deptId]
            );
        }
        return (int)($row['cnt'] ?? 0) > 0;
    }

    public static function pendingItemsCount(int $deptId): int
    {
        $row = Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM change_requests
             WHERE department_id = ? AND status = 'pending'",
            [$deptId]
        );
        return (int)($row['cnt'] ?? 0);
    }
}
