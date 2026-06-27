<?php
namespace App\Helpers;

/**
 * Shared brute-force lockout for students and users (Design §D1).
 * Timestamps computed in PHP so the logic is DB-agnostic (MySQL + SQLite tests).
 */
class Lockout
{
    private const TABLES = ['users', 'students']; // whitelist — table name never comes from user input

    private static function table(string $table): string
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new \InvalidArgumentException("Invalid principal table: {$table}");
        }
        return $table;
    }

    public static function isLocked(string $table, int $id): bool
    {
        $t = self::table($table);
        $row = Db::selectOne("SELECT locked_until FROM {$t} WHERE id = ?", [$id]);
        if (!$row || empty($row['locked_until'])) return false;
        return strtotime($row['locked_until']) > time();
    }

    public static function minutesRemaining(string $table, int $id): int
    {
        $t = self::table($table);
        $row = Db::selectOne("SELECT locked_until FROM {$t} WHERE id = ?", [$id]);
        if (!$row || empty($row['locked_until'])) return 0;
        $secs = strtotime($row['locked_until']) - time();
        return $secs > 0 ? (int)ceil($secs / 60) : 0;
    }

    /** Record a failed attempt; locks the account when the threshold is reached. */
    public static function registerFailure(string $table, int $id): array
    {
        $t = self::table($table);
        $threshold = (int) Config::get('auth.lockout_threshold', 5);
        $minutes   = (int) Config::get('auth.lockout_minutes', 15);

        $row = Db::selectOne("SELECT failed_attempts FROM {$t} WHERE id = ?", [$id]);
        $attempts = (int)($row['failed_attempts'] ?? 0) + 1;

        if ($attempts >= $threshold) {
            $lockedUntil = date('Y-m-d H:i:s', time() + $minutes * 60);
            Db::execute("UPDATE {$t} SET failed_attempts = ?, locked_until = ? WHERE id = ?",
                [$attempts, $lockedUntil, $id]);
            return ['locked' => true, 'attempts' => $attempts];
        }

        Db::execute("UPDATE {$t} SET failed_attempts = ? WHERE id = ?", [$attempts, $id]);
        return ['locked' => false, 'attempts' => $attempts];
    }

    /** Clear counters after a successful login. */
    public static function reset(string $table, int $id): void
    {
        $t = self::table($table);
        Db::execute("UPDATE {$t} SET failed_attempts = 0, locked_until = NULL WHERE id = ?", [$id]);
    }
}
