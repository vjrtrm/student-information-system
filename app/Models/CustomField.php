<?php
namespace App\Models;

use App\Helpers\Db;

class CustomField
{
    public static function findAll(): array
    {
        return Db::selectAll(
            "SELECT cf.*, d.name AS dept_name
             FROM custom_fields cf
             LEFT JOIN departments d ON d.id = cf.department_id
             ORDER BY cf.sort_order ASC, cf.id ASC"
        );
    }

    public static function findActive(int $deptId): array
    {
        return Db::selectAll(
            "SELECT * FROM custom_fields WHERE status = 'active' AND (scope = 'institution' OR department_id = ?) ORDER BY sort_order ASC, id ASC",
            [$deptId]
        );
    }

    public static function findById(int $id): ?array
    {
        return Db::selectOne('SELECT * FROM custom_fields WHERE id = ?', [$id]);
    }

    public static function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        Db::execute(
            'INSERT INTO custom_fields (label, field_type, section, scope, department_id, mode, options, status, sort_order, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['label'],
                $data['field_type'],
                $data['section'],
                $data['scope'],
                $data['department_id'] ?? null,
                $data['mode'],
                $data['options'] ?? null,
                'active',
                $data['sort_order'] ?? 0,
                $data['created_by'],
                $now,
                $now,
            ]
        );
        return (int) Db::lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        // field_key and field_type are intentionally excluded from updates
        $now = date('Y-m-d H:i:s');
        Db::execute(
            'UPDATE custom_fields SET label = ?, mode = ?, options = ?, status = ?, updated_at = ? WHERE id = ?',
            [$data['label'], $data['mode'], $data['options'] ?? null, $data['status'], $now, $id]
        );
    }
}
