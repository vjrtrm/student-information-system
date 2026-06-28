<?php
namespace App\Helpers;

class UploadException extends \RuntimeException {}

class DocumentUploadHandler
{
    /**
     * Validate and store an uploaded file for a student profile field.
     *
     * @param string $fieldKey   e.g. 'passport_photo_path'
     * @param array  $file       Entry from $_FILES[$fieldKey]
     * @param int    $studentId
     * @param bool   $photoOnly  If true, only image MIME types accepted
     * @param string|null $existingPath  Current stored path (deleted on success)
     * @return string  Relative path from project root
     * @throws UploadException
     */
    public static function handle(
        string $fieldKey,
        array $file,
        int $studentId,
        bool $photoOnly = false,
        ?string $existingPath = null
    ): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new UploadException('Upload error code: ' . ($file['error'] ?? 'unknown'));
        }

        $maxBytes = (int)Config::get('form.upload_max_bytes', 2097152);
        if ($file['size'] > $maxBytes) {
            throw new UploadException("File exceeds the 2 MB limit.");
        }

        // Detect MIME from actual file content, not client header
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        $allowedMimes = $photoOnly
            ? (array)Config::get('form.upload_allowed_photo_mimes', ['image/jpeg','image/png'])
            : (array)Config::get('form.upload_allowed_doc_mimes',  ['application/pdf','image/jpeg','image/png','image/webp']);

        if (!in_array($mimeType, $allowedMimes, true)) {
            $allowed = implode(', ', $allowedMimes);
            throw new UploadException("Invalid file type. Allowed: {$allowed}.");
        }

        $ext       = self::extFromMime($mimeType);
        $basePath  = rtrim((string)Config::get('form.upload_path', 'storage/uploads/students/'), '/');
        $dir       = $basePath . '/' . $studentId . '/';
        $filename  = $fieldKey . '_' . time() . '.' . $ext;
        $fullDir   = dirname(__DIR__, 2) . '/' . $dir;
        $fullPath  = $fullDir . $filename;
        $relPath   = $dir . $filename;

        if (!is_dir($fullDir) && !mkdir($fullDir, 0755, true)) {
            throw new UploadException('Could not create upload directory.');
        }

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new UploadException('Could not save uploaded file.');
        }

        // Delete old file
        if ($existingPath) {
            $oldFull = dirname(__DIR__, 2) . '/' . $existingPath;
            if (is_file($oldFull)) {
                @unlink($oldFull);
            }
        }

        return $relPath;
    }

    /**
     * Throw RuntimeException if form is already submitted (guards replacement uploads).
     */
    public static function guardSubmitted(array $profile): void
    {
        if (($profile['form_status'] ?? 'incomplete') === 'submitted') {
            throw new \RuntimeException('Form is submitted and cannot be modified.');
        }
    }

    private static function extFromMime(string $mime): string
    {
        return match($mime) {
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'application/pdf' => 'pdf',
            default           => 'bin',
        };
    }
}
