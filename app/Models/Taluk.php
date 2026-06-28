<?php
namespace App\Models;

use App\Helpers\Db;
use App\Helpers\ReferenceCheck;

/**
 * Taluk model — leaf level of the State → District → Taluk geography hierarchy.
 */
class Taluk
{
    // -------------------------------------------------------------------------
    // Lookups
    // -------------------------------------------------------------------------

    /** All taluks regardless of status, ordered by name. */
    public static function all(): array
    {
        return Db::select("SELECT * FROM taluks ORDER BY name");
    }

    /** Only active taluks. */
    public static function allActive(): array
    {
        return Db::select("SELECT * FROM taluks WHERE status = 'active' ORDER BY name");
    }

    /**
     * Taluks belonging to a given district.
     *
     * @param bool $activeOnly  When true, only returns active rows (default).
     */
    public static function byDistrict(int $districtId, bool $activeOnly = true): array
    {
        if ($activeOnly) {
            return Db::select(
                "SELECT * FROM taluks WHERE district_id = ? AND status = 'active' ORDER BY name",
                [$districtId]
            );
        }
        return Db::select(
            "SELECT * FROM taluks WHERE district_id = ? ORDER BY name",
            [$districtId]
        );
    }

    public static function find(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM taluks WHERE id = ?", [$id]);
    }

    public static function findByNameAndDistrict(string $name, int $districtId): ?array
    {
        return Db::selectOne(
            "SELECT * FROM taluks WHERE name = ? AND district_id = ?",
            [trim($name), $districtId]
        );
    }

    // -------------------------------------------------------------------------
    // Writes
    // -------------------------------------------------------------------------

    /**
     * Insert a new taluk with default status = 'active'.
     *
     * @return int  New row id.
     */
    public static function create(int $districtId, string $name): int
    {
        Db::execute(
            "INSERT INTO taluks (district_id, name) VALUES (?,?)",
            [$districtId, trim($name)]
        );
        return (int)Db::conn()->lastInsertId();
    }

    public static function update(int $id, int $districtId, string $name, string $status): void
    {
        Db::execute(
            "UPDATE taluks SET district_id = ?, name = ?, status = ? WHERE id = ?",
            [$districtId, trim($name), $status, $id]
        );
    }

    public static function deactivate(int $id): void
    {
        Db::execute("UPDATE taluks SET status = 'inactive' WHERE id = ?", [$id]);
    }

    public static function reactivate(int $id): void
    {
        Db::execute("UPDATE taluks SET status = 'active' WHERE id = ?", [$id]);
    }

    // -------------------------------------------------------------------------
    // Reference safety
    // -------------------------------------------------------------------------

    /**
     * Taluks are the leaf of the hierarchy; the MAP entry is empty so this always
     * returns false unless a future module adds a taluk_id foreign key.
     */
    public static function inUse(int $id): bool
    {
        return ReferenceCheck::inUse('taluk', $id);
    }
}
