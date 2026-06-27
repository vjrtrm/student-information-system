<?php
namespace App\Helpers;

/** Reusable validation rules (Foundation §4). Server always re-validates. */
class Validator
{
    public static function mobile($v): bool   { return (bool) preg_match('/^\d{10}$/', (string)$v); }
    public static function aadhaar($v): bool  { return (bool) preg_match('/^\d{12}$/', (string)$v); }
    public static function pincode($v): bool  { return (bool) preg_match('/^\d{6}$/', (string)$v); }
    public static function email($v): bool    { return (bool) filter_var((string)$v, FILTER_VALIDATE_EMAIL); }

    public static function date($v): bool
    {
        $v = (string)$v;
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return $d !== false && $d->format('Y-m-d') === $v;
    }

    /** Min length (config default) and at least one digit (Design §5.4). */
    public static function password($v, ?int $min = null): bool
    {
        $min = $min ?? (int) Config::get('auth.password_min_length', 8);
        $v = (string)$v;
        return strlen($v) >= $min && preg_match('/\d/', $v) === 1;
    }

    /** PDF document upload <= 2 MB. $file = ['type'=>..,'size'=>..]. */
    public static function pdfUpload(array $file, int $maxBytes = 2097152): bool
    {
        return ($file['type'] ?? '') === 'application/pdf' && (int)($file['size'] ?? 0) <= $maxBytes;
    }

    /** Image-only upload (passport photo) <= 2 MB. */
    public static function imageUpload(array $file, int $maxBytes = 2097152): bool
    {
        return in_array($file['type'] ?? '', ['image/jpeg', 'image/png'], true)
            && (int)($file['size'] ?? 0) <= $maxBytes;
    }
}
