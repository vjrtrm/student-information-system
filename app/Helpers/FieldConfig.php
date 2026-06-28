<?php
namespace App\Helpers;

/**
 * Resolves the effective field configuration for a department.
 * Merges: registry defaults → institution-wide overrides → department overrides → active custom fields.
 * Request-scoped cache via static property.
 */
class FieldConfig
{
    private static array $cache = [];

    /**
     * Resolve the effective mode for each configurable (and custom) field for a department.
     * Returns: field_key => 'required'|'optional'|'hidden'
     */
    public static function resolve(int $deptId): array
    {
        if (isset(self::$cache[$deptId])) return self::$cache[$deptId];

        // 1. Start with registry defaults
        $config = [];
        foreach (FieldRegistry::configurableFields() as $key => $field) {
            $config[$key] = $field['default_mode'];
        }

        // 2. Apply institution-wide overrides (department_id = 0)
        $rows = Db::selectAll('SELECT field_key, mode FROM field_configs WHERE department_id = 0');
        foreach ($rows as $row) {
            if (isset($config[$row['field_key']])) {
                $config[$row['field_key']] = $row['mode'];
            }
        }

        // 3. Apply department overrides
        $rows = Db::selectAll('SELECT field_key, mode FROM field_configs WHERE department_id = ?', [$deptId]);
        foreach ($rows as $row) {
            if (isset($config[$row['field_key']])) {
                $config[$row['field_key']] = $row['mode'];
            }
        }

        // 4. Append active custom fields for this dept
        $customFields = Db::selectAll(
            "SELECT id, mode FROM custom_fields WHERE status = 'active' AND (scope = 'institution' OR department_id = ?)",
            [$deptId]
        );
        foreach ($customFields as $cf) {
            $config['custom_' . $cf['id']] = $cf['mode'];
        }

        self::$cache[$deptId] = $config;
        return $config;
    }

    /**
     * Returns all active custom fields applicable to a department, ordered by sort_order.
     */
    public static function resolveCustomFields(int $deptId): array
    {
        return Db::selectAll(
            "SELECT id, field_key, label, field_type, section, scope, department_id, mode, options
             FROM custom_fields
             WHERE status = 'active' AND (scope = 'institution' OR department_id = ?)
             ORDER BY sort_order ASC, id ASC",
            [$deptId]
        );
    }

    /**
     * Clear the request-scoped cache (call after saving field config changes).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
