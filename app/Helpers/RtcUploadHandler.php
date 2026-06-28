<?php
namespace App\Helpers;

/**
 * Manages file uploads for RTC (Request-to-Change) requests.
 * Temp storage: storage/uploads/rtc/temp/{rtcId}/{fieldKey}_{time}.{ext}
 * Final storage: storage/uploads/students/{studentId}/{fieldKey}_{time}.{ext}
 */
class RtcUploadHandler
{
    private static string $baseDir = '';

    private static function base(): string
    {
        if (!self::$baseDir) {
            self::$baseDir = dirname(__DIR__, 2);
        }
        return self::$baseDir;
    }

    /**
     * Store an uploaded file in the RTC temp directory.
     *
     * @param string $fieldKey  Profile field key (e.g. 'qual_sslc_doc_path')
     * @param array  $file      Entry from $_FILES
     * @param int    $rtcId     The change_request id
     * @param bool   $photoOnly True for passport_photo_path
     * @return string Relative path to stored temp file
     * @throws UploadException on invalid file
     */
    public static function storeTemp(string $fieldKey, array $file, int $rtcId, bool $photoOnly = false): string
    {
        $tempDir = self::base() . "/storage/uploads/rtc/temp/{$rtcId}";
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $maxBytes     = (int)(Config::get('form.upload_max_bytes') ?? 2097152);
        $allowedDoc   = Config::get('form.upload_allowed_doc_mimes')   ?? ['application/pdf','image/jpeg','image/png','image/webp'];
        $allowedPhoto = Config::get('form.upload_allowed_photo_mimes') ?? ['image/jpeg','image/png'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new UploadException('File upload error.');
        }
        if ($file['size'] > $maxBytes) {
            throw new UploadException('File exceeds 2 MB limit.');
        }

        $finfo   = new \finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($file['tmp_name']);
        $allowed = $photoOnly ? $allowedPhoto : $allowedDoc;
        if (!in_array($mime, $allowed, true)) {
            throw new UploadException('Invalid file type.');
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $fieldKey . '_' . time() . '.' . strtolower($ext);
        $dest     = $tempDir . '/' . $filename;
        move_uploaded_file($file['tmp_name'], $dest);

        return "storage/uploads/rtc/temp/{$rtcId}/{$filename}";
    }

    /**
     * Move temp files to the student's upload directory on RTC approval.
     * Deletes old file for same field if exists.
     * Must be called inside the caller's transaction.
     *
     * @param int   $rtcId
     * @param int   $studentId
     * @param array $fileEntries Changeset entries where is_file === true
     * @return array field_key => new relative path
     */
    public static function commit(int $rtcId, int $studentId, array $fileEntries): array
    {
        $studentDir = self::base() . "/storage/uploads/students/{$studentId}";
        if (!is_dir($studentDir)) {
            mkdir($studentDir, 0755, true);
        }

        $committed = [];
        foreach ($fileEntries as $entry) {
            $tempPath = self::base() . '/' . ltrim($entry['proposed_value'], '/');
            if (!file_exists($tempPath)) {
                continue;
            }

            // Delete old file for same field
            if (!empty($entry['current_value'])) {
                $oldPath = self::base() . '/' . ltrim($entry['current_value'], '/');
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $filename = basename($tempPath);
            $newPath  = $studentDir . '/' . $filename;
            rename($tempPath, $newPath);
            $committed[$entry['field_key']] = "storage/uploads/students/{$studentId}/{$filename}";
        }

        return $committed;
    }

    /**
     * Delete all temp files for an RTC (on rejection or cancellation).
     */
    public static function discard(int $rtcId): void
    {
        $tempDir = self::base() . "/storage/uploads/rtc/temp/{$rtcId}";
        if (!is_dir($tempDir)) return;
        foreach (glob($tempDir . '/*') as $file) {
            if (is_file($file)) unlink($file);
        }
        @rmdir($tempDir);
    }
}
