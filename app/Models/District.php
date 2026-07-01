<?php
namespace App\Models;

use App\Helpers\Db;
use App\Helpers\ReferenceCheck;

/**
 * District model — second level of the State → District → Taluk geography hierarchy.
 */
class District
{
    // -------------------------------------------------------------------------
    // Lookups
    // -------------------------------------------------------------------------

    /** All districts regardless of status, ordered by name. */
    public static function all(): array
    {
        return Db::select("SELECT d.*, s.name AS state_name FROM districts d LEFT JOIN states s ON s.id = d.state_id ORDER BY d.name");
    }

    /** Only active districts. */
    public static function allActive(): array
    {
        return Db::select("SELECT * FROM districts WHERE status = 'active' ORDER BY name");
    }

    /**
     * Districts belonging to a given state.
     *
     * @param bool $activeOnly  When true, only returns active rows (default).
     */
    public static function byState(int $stateId, bool $activeOnly = true): array
    {
        if ($activeOnly) {
            return Db::select(
                "SELECT * FROM districts WHERE state_id = ? AND status = 'active' ORDER BY name",
                [$stateId]
            );
        }
        return Db::select(
            "SELECT * FROM districts WHERE state_id = ? ORDER BY name",
            [$stateId]
        );
    }

    public static function find(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM districts WHERE id = ?", [$id]);
    }

    public static function findByNameAndState(string $name, int $stateId): ?array
    {
        return Db::selectOne(
            "SELECT * FROM districts WHERE name = ? AND state_id = ?",
            [trim($name), $stateId]
        );
    }

    // -------------------------------------------------------------------------
    // Writes
    // -------------------------------------------------------------------------

    /**
     * Insert a new district with default status = 'active'.
     *
     * @return int  New row id.
     */
    public static function create(int $stateId, string $name): int
    {
        Db::execute(
            "INSERT INTO districts (state_id, name) VALUES (?,?)",
            [$stateId, trim($name)]
        );
        return (int)Db::conn()->lastInsertId();
    }

    public static function update(int $id, int $stateId, string $name, string $status): void
    {
        Db::execute(
            "UPDATE districts SET state_id = ?, name = ?, status = ? WHERE id = ?",
            [$stateId, trim($name), $status, $id]
        );
    }

    public static function deactivate(int $id): void
    {
        Db::execute("UPDATE districts SET status = 'inactive' WHERE id = ?", [$id]);
    }

    public static function reactivate(int $id): void
    {
        Db::execute("UPDATE districts SET status = 'active' WHERE id = ?", [$id]);
    }

    // -------------------------------------------------------------------------
    // Reference safety
    // -------------------------------------------------------------------------

    /** Returns true if any taluks reference this district. */
    public static function inUse(int $id): bool
    {
        return ReferenceCheck::inUse('district', $id);
    }
}
