<?php
namespace App\Middleware;

use App\Helpers\Auth;
use App\Helpers\View;

/** Requires a valid, non-expired session; otherwise redirects to login (Design §3). */
class AuthMiddleware
{
    public static function handle(): void
    {
        if (!Auth::check()) {
            View::redirect('/login');
        }
    }
}
