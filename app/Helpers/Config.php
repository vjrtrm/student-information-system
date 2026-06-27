<?php
namespace App\Helpers;

/** Loads config/*.php once and exposes dot-notation access: Config::get('auth.lockout_threshold'). */
class Config
{
    private static array $cache = [];
    private static ?string $path = null;

    public static function setPath(string $dir): void { self::$path = rtrim($dir, '/'); self::$cache = []; }

    private static function dir(): string
    {
        return self::$path ?? dirname(__DIR__, 2) . '/config';
    }

    public static function get(string $key, $default = null)
    {
        [$file, $rest] = array_pad(explode('.', $key, 2), 2, null);
        if (!isset(self::$cache[$file])) {
            $f = self::dir() . "/{$file}.php";
            self::$cache[$file] = is_file($f) ? require $f : [];
        }
        $value = self::$cache[$file];
        if ($rest === null) return $value;
        foreach (explode('.', $rest) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        return $value;
    }
}
