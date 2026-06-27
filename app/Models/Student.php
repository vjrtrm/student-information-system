<?php
namespace App\Models;

use App\Helpers\Db;

/** Auth-relevant subset of students (full record in Module 5). */
class Student
{
    /** Returns the single active student for a mobile, or null. Mobile is expected unique post-onboarding. */
    public static function findByMobile(string $mobile): ?array
    {
        $rows = Db::select(
            "SELECT * FROM students WHERE mobile = ?",
            [trim($mobile)]
        );
        // Data-integrity guard (Design §10): never authenticate an ambiguous mobile.
        if (count($rows) !== 1) return null;
        return $rows[0];
    }

    public static function find(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM students WHERE id = ? LIMIT 1", [$id]);
    }

    public static function isActive(array $student): bool
    {
        return ($student['status'] ?? 'active') === 'active';
    }
}
