<?php
namespace App\Helpers;

/**
 * Checks whether a master-data record is referenced by downstream tables.
 * Used before deactivate/delete to prevent orphaned foreign keys.
 *
 * MAP format: 'entity' => ['table' => 'foreign_key_column']
 */
class ReferenceCheck
{
    private const MAP = [
        'department' => [
            'users'    => 'department_id',
            'students' => 'department_id',
        ],
        'state' => [
            'districts' => 'state_id',
        ],
        'district' => [
            'taluks' => 'district_id',
        ],
        'taluk' => [],
        'option_value' => [
            'students' => 'academic_year_id',
        ],
    ];

    /**
     * Returns true if any configured downstream table has at least one row
     * with the given $id in the mapped foreign-key column.
     */
    public static function inUse(string $entity, int $id): bool
    {
        $checks = self::MAP[$entity] ?? [];
        foreach ($checks as $table => $column) {
            try {
                $count = Db::selectOne(
                    "SELECT COUNT(*) AS cnt FROM `{$table}` WHERE `{$column}` = ?",
                    [$id]
                );
                if (($count['cnt'] ?? 0) > 0) return true;
            } catch (\Throwable $e) {
                // Table does not exist yet (e.g. during early migrations); skip safely.
            }
        }
        return false;
    }
}
