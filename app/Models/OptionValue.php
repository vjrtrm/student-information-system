<?php
namespace App\Models;

use App\Helpers\Db;

/**
 * OptionValue model — individual selectable items that belong to an OptionList.
 *
 * Schema assumption:
 *   option_values (id, list_id, value, display, sort_order, status)
 *   option_lists  (id, list_key, label, ...)
 */
class OptionValue
{
    // -------------------------------------------------------------------------
    // Lookups
    // -------------------------------------------------------------------------

    /**
     * Values for a list identified by its numeric id.
     *
     * @param bool $activeOnly  When true (default), only returns active rows.
     */
    public static function byList(int $listId, bool $activeOnly = true): array
    {
        if ($activeOnly) {
            return Db::select(
                "SELECT * FROM option_values
                 WHERE list_id = ? AND status = 'active'
                 ORDER BY sort_order, display",
                [$listId]
            );
        }
        return Db::select(
            "SELECT * FROM option_values
             WHERE list_id = ?
             ORDER BY sort_order, display",
            [$listId]
        );
    }

    /**
     * Values for a list identified by its string key (e.g. 'academic_year').
     * Joins option_lists so callers do not need to resolve the id themselves.
     *
     * @param bool $activeOnly  When true (default), only returns active rows.
     */
    public static function byListKey(string $key, bool $activeOnly = true): array
    {
        if ($activeOnly) {
            return Db::select(
                "SELECT ov.*
                 FROM option_values ov
                 INNER JOIN option_lists ol ON ol.id = ov.list_id
                 WHERE ol.list_key = ? AND ov.status = 'active'
                 ORDER BY ov.sort_order, ov.display",
                [$key]
            );
        }
        return Db::select(
            "SELECT ov.*
             FROM option_values ov
             INNER JOIN option_lists ol ON ol.id = ov.list_id
             WHERE ol.list_key = ?
             ORDER BY ov.sort_order, ov.display",
            [$key]
        );
    }

    public static function find(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM option_values WHERE id = ?", [$id]);
    }

    // -------------------------------------------------------------------------
    // Writes
    // -------------------------------------------------------------------------

    /**
     * Insert a new option value.
     *
     * @return int  New row id.
     */
    public static function create(int $listId, string $value, string $display, int $sortOrder): int
    {
        Db::execute(
            "INSERT INTO option_values (list_id, value, display, sort_order) VALUES (?,?,?,?)",
            [$listId, trim($value), trim($display), $sortOrder]
        );
        return (int)Db::conn()->lastInsertId();
    }

    public static function update(int $id, string $value, string $display, int $sortOrder): void
    {
        Db::execute(
            "UPDATE option_values SET value = ?, display = ?, sort_order = ? WHERE id = ?",
            [trim($value), trim($display), $sortOrder, $id]
        );
    }

    public static function deactivate(int $id): void
    {
        Db::execute("UPDATE option_values SET status = 'inactive' WHERE id = ?", [$id]);
    }

    public static function reactivate(int $id): void
    {
        Db::execute("UPDATE option_values SET status = 'active' WHERE id = ?", [$id]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Highest sort_order currently used in a list, or 0 when the list is empty.
     * Useful for appending a new value at the end: create(..., maxSortOrder($listId) + 1).
     */
    public static function maxSortOrder(int $listId): int
    {
        $row = Db::selectOne(
            "SELECT MAX(sort_order) AS max_sort FROM option_values WHERE list_id = ?",
            [$listId]
        );
        return (int)($row['max_sort'] ?? 0);
    }
}
