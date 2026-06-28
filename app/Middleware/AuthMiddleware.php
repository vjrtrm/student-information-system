<?php
namespace App\Middleware;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\View;

/** Requires a valid, non-expired session; otherwise redirects to login (Design §3). */
class AuthMiddleware
{
    public static function handle(): void
    {
        if (!Auth::check()) {
            View::redirect('/login');
        }

        // M9: force password change when must_change_password flag is set
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($uri !== '/staff/change-password' && $uri !== '/logout') {
            $user = Auth::user();
            if ($user && $user['type'] === 'user') {
                $row = Db::selectOne(
                    "SELECT must_change_password FROM users WHERE id = ?",
                    [(int)$user['id']]
                );
                if ($row && (int)$row['must_change_password'] === 1) {
                    View::redirect('/staff/change-password');
                }
            }
        }
    }
}
