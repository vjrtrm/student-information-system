<?php
namespace App\Models;

use App\Helpers\Db;

/**
 * OptionList model — represents a named group of selectable values (e.g. academic_year,
 * blood_group, religion).  Lists are seeded; they are never created/deleted at runtime.
 */
class OptionList
{
    // -------------------------------------------------------------------------
    // Lookups
    // -------------------------------------------------------------------------

    /** All option lists ordered by label. */
    public static function all(): array
    {
        return Db::select("SELECT * FROM option_lists ORDER BY label");
    }

    public static function find(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM option_lists WHERE id = ?", [$id]);
    }

    public static function findByKey(string $key): ?array
    {
        return Db::selectOne("SELECT * FROM option_lists WHERE list_key = ?", [$key]);
    }

    /**
     * All option lists with a 'value_count' field showing how many active values
     * each list currently has. Useful for the admin overview grid.
     */
    public static function withCounts(): array
    {
        return Db::select(
            "SELECT ol.*, COUNT(ov.id) AS value_count
             FROM option_lists ol
             LEFT JOIN option_values ov ON ov.list_id = ol.id AND ov.status = 'active'
             GROUP BY ol.id
             ORDER BY ol.label"
        );
    }
}
