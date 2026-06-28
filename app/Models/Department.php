<?php
namespace App\Models;

use App\Helpers\Db;
use App\Helpers\ReferenceCheck;

/**
 * Department model — full Module 2 implementation.
 * Replaces the minimal stub written for Module 1.
 */
class Department
{
    // -------------------------------------------------------------------------
    // Lookups
    // -------------------------------------------------------------------------

    public static function find(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM departments WHERE id = ?", [$id]);
    }

    /** All departments regardless of status, ordered by name. */
    public static function all(): array
    {
        return Db::select("SELECT * FROM departments ORDER BY name");
    }

    /** Only active departments (used in dropdowns etc.). */
    public static function allActive(): array
    {
        return Db::select("SELECT * FROM departments WHERE status = 'active' ORDER BY name");
    }

    /**
     * Paginated search across name and code columns.
     *
     * @return array  Rows for the requested page.
     */
    public static function search(string $q = '', int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $like   = '%' . $q . '%';
        return Db::select(
            "SELECT * FROM departments
             WHERE (name LIKE ? OR code LIKE ?)
             ORDER BY name
             LIMIT ? OFFSET ?",
            [$like, $like, $perPage, $offset]
        );
    }

    /** Total rows matching the search query (for pagination). */
    public static function count(string $q = ''): int
    {
        $like = '%' . $q . '%';
        $row  = Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM departments WHERE name LIKE ? OR code LIKE ?",
            [$like, $like]
        );
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Check whether a department code is already taken.
     *
     * @param int|null $excludeId  Pass the current record's id on update to allow self-match.
     */
    public static function codeExists(string $code, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = Db::selectOne(
                "SELECT id FROM departments WHERE code = ? AND id != ?",
                [strtoupper($code), $excludeId]
            );
        } else {
            $row = Db::selectOne(
                "SELECT id FROM departments WHERE code = ?",
                [strtoupper($code)]
            );
        }
        return $row !== null;
    }

    // -------------------------------------------------------------------------
    // Writes
    // -------------------------------------------------------------------------

    /**
     * Insert a new department.
     *
     * @param string $level  'UG' | 'PG'
     * @return int  New row id.
     */
    public static function create(string $name, string $code, string $level): int
    {
        Db::execute(
            "INSERT INTO departments (name, code, level) VALUES (?,?,?)",
            [trim($name), strtoupper(trim($code)), $level]
        );
        return (int)Db::conn()->lastInsertId();
    }

    public static function update(int $id, string $name, string $code, string $level): void
    {
        Db::execute(
            "UPDATE departments SET name = ?, code = ?, level = ? WHERE id = ?",
            [trim($name), strtoupper(trim($code)), $level, $id]
        );
    }

    public static function deactivate(int $id): void
    {
        Db::execute("UPDATE departments SET status = 'inactive' WHERE id = ?", [$id]);
    }

    public static function reactivate(int $id): void
    {
        Db::execute("UPDATE departments SET status = 'active' WHERE id = ?", [$id]);
    }

    // -------------------------------------------------------------------------
    // Reference safety
    // -------------------------------------------------------------------------

    /** Returns true if users or students are linked to this department. */
    public static function inUse(int $id): bool
    {
        return ReferenceCheck::inUse('department', $id);
    }
}
