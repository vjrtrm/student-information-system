<?php
namespace App\Models;

use App\Helpers\Db;

/** Staff & admin accounts. */
class User
{
    public static function findByEmail(string $email): ?array
    {
        return Db::selectOne(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [strtolower(trim($email))]
        );
    }

    public static function find(int $id): ?array
    {
        return Db::selectOne("SELECT * FROM users WHERE id = ? LIMIT 1", [$id]);
    }

    public static function updatePassword(int $id, string $hash): void
    {
        Db::execute("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $id]);
    }

    public static function isActive(array $user): bool
    {
        return ($user['status'] ?? 'active') === 'active';
    }

    public static function firstName(array $user): string
    {
        return trim(explode(' ', trim($user['name'] ?? ''))[0] ?? '') ?: 'there';
    }
}
