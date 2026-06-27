<?php
namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;

/** Base controller: input access, CSRF guard, rendering helpers. */
abstract class Controller
{
    protected function input(string $key, $default = null)
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    /** Verify CSRF on state-changing requests; 403 + stop on failure. */
    protected function requireCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            View::render('errors/403', ['title' => 'Invalid request'], 403);
            exit;
        }
    }

    protected function render(string $template, array $data = [], int $status = 200): void
    {
        View::render($template, $data, $status);
    }

    protected function redirect(string $path): void
    {
        View::redirect($path);
    }
}
