<?php
namespace App\Helpers;

use PDO;

/**
 * PDO connection holder. Uses config/database.php by default, but a PDO can be
 * injected (e.g. an in-memory SQLite for tests) via setConnection().
 */
class Db
{
    private static ?PDO $pdo = null;

    public static function setConnection(PDO $pdo): void { self::$pdo = $pdo; }

    public static function conn(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;

        $c = Config::get('database');
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $c['driver'], $c['host'], $c['port'], $c['database'], $c['charset']
        );
        self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return self::$pdo;
    }

    /** Alias for select() — kept for callers that use the longer name. */
    public static function selectAll(string $sql, array $params = []): array
    {
        return self::select($sql, $params);
    }

    /** Convenience: prepared SELECT returning all rows. */
    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Convenience: prepared SELECT returning one row or null. */
    public static function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Convenience: prepared write (INSERT/UPDATE/DELETE). Returns affected rows. */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
