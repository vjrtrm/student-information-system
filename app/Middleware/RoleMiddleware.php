<?php
namespace App\Middleware;

use App\Helpers\Auth;
use App\Helpers\View;

/** Allows only the listed roles; otherwise renders 403 (Design §3, §6). */
class RoleMiddleware
{
    public static function handle(array $allowedRoles): void
    {
        $role = Auth::role();
        if ($role === null || !in_array($role, $allowedRoles, true)) {
            View::render('errors/403', ['title' => 'Forbidden'], 403);
            exit;
        }
    }
}
