<?php
namespace App\Helpers;

/** Session, login/logout, password hashing, RBAC accessors (Design §7). */
class Auth
{
    /** Start a hardened session (HttpOnly, Secure, SameSite). Safe to call repeatedly. */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        if (PHP_SAPI === 'cli') { // tests: use the $_SESSION superglobal without a real session
            if (!isset($_SESSION)) $_SESSION = [];
            return;
        }
        $secure = ($_SERVER['HTTPS'] ?? '') !== '' || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $secure,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /** Establish an authenticated session for a principal. */
    public static function login(string $type, int $id, string $role, ?int $departmentId): void
    {
        self::start();
        if (PHP_SAPI !== 'cli') {
            session_regenerate_id(true); // prevent fixation (Design §7)
        }
        $_SESSION['principal_type'] = $type;          // 'student' | 'user'
        $_SESSION['principal_id']   = $id;
        $_SESSION['role']           = $role;          // student|staff|dept_admin|institution_admin
        $_SESSION['department_id']  = $departmentId;
        $_SESSION['last_activity']  = time();
    }

    /** True if a valid, non-expired session exists; refreshes the activity timestamp. */
    public static function check(): bool
    {
        self::start();
        if (empty($_SESSION['principal_id'])) return false;

        $timeout = (int) Config::get('auth.session_timeout_minutes', 30) * 60;
        $last = (int)($_SESSION['last_activity'] ?? 0);
        if ($last > 0 && (time() - $last) > $timeout) {
            self::logout();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function id(): ?int            { return $_SESSION['principal_id'] ?? null; }
    public static function type(): ?string       { return $_SESSION['principal_type'] ?? null; }
    public static function role(): ?string       { return $_SESSION['role'] ?? null; }
    public static function departmentId(): ?int  { return $_SESSION['department_id'] ?? null; }

    public static function user(): ?array
    {
        if (empty($_SESSION['principal_id'])) return null;
        return [
            'type'          => $_SESSION['principal_type'] ?? null,
            'id'            => $_SESSION['principal_id'],
            'role'          => $_SESSION['role'] ?? null,
            'department_id' => $_SESSION['department_id'] ?? null,
        ];
    }

    public static function isAdmin(): bool
    {
        return in_array(self::role(), ['dept_admin', 'institution_admin'], true);
    }

    public static function logout(): void
    {
        self::start();
        $type = self::type(); $id = self::id();
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        if ($id) AuditLogger::log('logout', $type, (int)$id);
    }
}
