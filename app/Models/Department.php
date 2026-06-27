<?php
namespace App\Models;

use App\Helpers\Db;

/** Minimal department lookups needed by Module 1 (Module 2 owns full management). */
class Department
{
    public static function find(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM departments WHERE id = ? LIMIT 1", [$id]);
    }

    public static function all(): array
    {
        return Db::select("SELECT * FROM departments WHERE status = 'active' ORDER BY name");
    }
}
