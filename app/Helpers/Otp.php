<?php
namespace App\Helpers;

/** Time-boxed, single-use one-time codes (Design §5.3). Stored hashed. */
class Otp
{
    /** Generate a 6-digit code, store its hash, return the plain code to send. */
    public static function generate(string $principalType, int $principalId): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl  = (int) Config::get('auth.otp_ttl_minutes', 15);
        Db::execute(
            "INSERT INTO login_otps (principal_type, principal_id, code_hash, expires_at, created_at)
             VALUES (?,?,?,?,?)",
            [
                $principalType,
                $principalId,
                password_hash($code, PASSWORD_BCRYPT),
                date('Y-m-d H:i:s', time() + $ttl * 60),
                date('Y-m-d H:i:s'),
            ]
        );
        return $code;
    }

    /** Verify a code against the latest unused, unexpired OTP; consume it on success. */
    public static function verify(string $principalType, int $principalId, string $code): bool
    {
        $rows = Db::select(
            "SELECT id, code_hash, expires_at, used_at FROM login_otps
             WHERE principal_type = ? AND principal_id = ? AND used_at IS NULL
             ORDER BY id DESC",
            [$principalType, $principalId]
        );
        foreach ($rows as $row) {
            if (strtotime($row['expires_at']) < time()) continue;
            if (password_verify($code, $row['code_hash'])) {
                Db::execute("UPDATE login_otps SET used_at = ? WHERE id = ?",
                    [date('Y-m-d H:i:s'), $row['id']]);
                return true;
            }
        }
        return false;
    }
}
