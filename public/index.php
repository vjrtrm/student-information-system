<?php
/**
 * Front controller — all requests route here (Foundation §1).
 * Middleware chain runs before the controller, so authorisation is enforced first.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Helpers\Auth;
use App\Helpers\Config;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Controllers\AuthController;
use App\Controllers\PasswordResetController;
use App\Controllers\DashboardController;

Config::setPath(dirname(__DIR__) . '/config');
Auth::start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'");

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$base   = Config::get('app.base_url', '');
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($base && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base)) ?: '/';
}

// route => [ [METHOD, handler, middleware[]], ... ]
$routes = [
    ['GET',  '/',                 fn() => View::redirect(Auth::check() ? '/dashboard' : '/login'), []],
    ['GET',  '/login',            [AuthController::class, 'showLogin'], []],
    ['POST', '/login/student',    [AuthController::class, 'studentLogin'], []],
    ['POST', '/login/staff',      [AuthController::class, 'staffLogin'], []],
    ['GET',  '/login/otp',        [AuthController::class, 'showOtp'], []],
    ['POST', '/login/otp',        [AuthController::class, 'verifyOtp'], []],
    ['POST', '/logout',           [AuthController::class, 'logout'], ['auth']],
    ['GET',  '/forgot-password',  [PasswordResetController::class, 'showForgot'], []],
    ['POST', '/forgot-password',  [PasswordResetController::class, 'sendReset'], []],
    ['GET',  '/reset-password',   [PasswordResetController::class, 'showReset'], []],
    ['POST', '/reset-password',   [PasswordResetController::class, 'submitReset'], []],
    ['GET',  '/dashboard',        [DashboardController::class, 'index'], ['auth']],
];

foreach ($routes as [$m, $path, $handler, $mw]) {
    if ($m !== $method || $path !== $uri) continue;

    foreach ($mw as $name) {
        if ($name === 'auth') AuthMiddleware::handle();
    }

    if ($handler instanceof \Closure) { $handler(); exit; }
    [$class, $action] = $handler;
    (new $class())->{$action}();
    exit;
}

// No route matched
View::render('errors/404', ['title' => 'Not found'], 404);
