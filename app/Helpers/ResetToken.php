<?php
namespace App\Helpers;

/** Create and single-use-consume password-reset tokens (Design §5.4). Stored as SHA-256 hash. */
class ResetToken
{
    /** Create a reset token for a user; returns the raw token to embed in the email link. */
    public static function create(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $ttl   = (int) Config::get('auth.reset_ttl_minutes', 15);
        Db::execute(
            "INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?,?,?,?)",
            [$userId, hash('sha256', $token), date('Y-m-d H:i:s', time() + $ttl * 60), date('Y-m-d H:i:s')]
        );
        return $token;
    }

    /** Validate and consume a token (single-use, time-boxed). */
    public static function consume(int $userId, string $token): bool
    {
        if ($userId <= 0 || $token === '') return false;
        $rows = Db::select(
            "SELECT id, token_hash, expires_at FROM password_resets
             WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC",
            [$userId]
        );
        $hash = hash('sha256', $token);
        foreach ($rows as $row) {
            if (strtotime($row['expires_at']) < time()) continue;
            if (hash_equals($row['token_hash'], $hash)) {
                Db::execute("UPDATE password_resets SET used_at = ? WHERE id = ?",
                    [date('Y-m-d H:i:s'), $row['id']]);
                return true;
            }
        }
        return false;
    }
}
