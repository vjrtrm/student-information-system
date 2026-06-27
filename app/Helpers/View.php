<?php
namespace App\Helpers;

/** Minimal view renderer with a shared layout. Output is escaped at the view level. */
class View
{
    public static function render(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        $viewsDir = dirname(__DIR__) . '/Views';
        extract($data, EXTR_SKIP);
        $content = (function () use ($viewsDir, $template, $data) {
            extract($data, EXTR_SKIP);
            ob_start();
            require $viewsDir . '/' . $template . '.php';
            return ob_get_clean();
        })();
        $layout = $viewsDir . '/layouts/app.php';
        if (is_file($layout)) {
            require $layout; // uses $content + $data
        } else {
            echo $content;
        }
    }

    /** Escape helper for use inside views. */
    public static function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function redirect(string $path): void
    {
        $base = Config::get('app.base_url', '');
        header('Location: ' . $base . $path);
        exit;
    }
}
