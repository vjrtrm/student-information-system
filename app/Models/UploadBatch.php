<?php
namespace App\Models;

use App\Helpers\Db;

class UploadBatch
{
    public static function create(int $deptId, int $userId, string $filename, int $totalRows): int
    {
        Db::execute(
            "INSERT INTO upload_batches (department_id, uploaded_by, original_filename, total_rows)
             VALUES (?,?,?,?)",
            [$deptId, $userId, $filename, $totalRows]
        );
        return (int)Db::conn()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM upload_batches WHERE id = ?", [$id]);
    }

    public static function updateCounts(int $id, int $created, int $held, int $failed): void
    {
        Db::execute(
            "UPDATE upload_batches SET created_count = ?, duplicate_held_count = ?, failed_count = ? WHERE id = ?",
            [$created, $held, $failed, $id]
        );
    }

    public static function incrementCreated(int $id): void
    {
        Db::execute("UPDATE upload_batches SET created_count = created_count + 1 WHERE id = ?", [$id]);
    }
}
