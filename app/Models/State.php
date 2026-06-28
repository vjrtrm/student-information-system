<?php
namespace App\Models;

use App\Helpers\Db;
use App\Helpers\ReferenceCheck;

/**
 * State model — top level of the State → District → Taluk geography hierarchy.
 */
class State
{
    // -------------------------------------------------------------------------
    // Lookups
    // -------------------------------------------------------------------------

    /** All states regardless of status, ordered by name. */
    public static function all(): array
    {
        return Db::select("SELECT * FROM states ORDER BY name");
    }

    /** Only active states (used in dropdowns). */
    public static function allActive(): array
    {
        return Db::select("SELECT * FROM states WHERE status = 'active' ORDER BY name");
    }

    public static function find(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM states WHERE id = ?", [$id]);
    }

    public static function findByName(string $name): ?array
    {
        return Db::selectOne("SELECT * FROM states WHERE name = ?", [trim($name)]);
    }

    // -------------------------------------------------------------------------
    // Writes
    // -------------------------------------------------------------------------

    /**
     * Insert a new state with default status = 'active'.
     *
     * @return int  New row id.
     */
    public static function create(string $name): int
    {
        Db::execute("INSERT INTO states (name) VALUES (?)", [trim($name)]);
        return (int)Db::conn()->lastInsertId();
    }

    public static function update(int $id, string $name, string $status): void
    {
        Db::execute(
            "UPDATE states SET name = ?, status = ? WHERE id = ?",
            [trim($name), $status, $id]
        );
    }

    public static function deactivate(int $id): void
    {
        Db::execute("UPDATE states SET status = 'inactive' WHERE id = ?", [$id]);
    }

    public static function reactivate(int $id): void
    {
        Db::execute("UPDATE states SET status = 'active' WHERE id = ?", [$id]);
    }

    // -------------------------------------------------------------------------
    // Reference safety
    // -------------------------------------------------------------------------

    /** Returns true if any districts reference this state. */
    public static function inUse(int $id): bool
    {
        return ReferenceCheck::inUse('state', $id);
    }
}
