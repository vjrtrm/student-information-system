<?php
namespace App\Models;

use App\Helpers\Db;
use App\Helpers\FormFieldRules;
use App\Helpers\MasterAuditLogger;

class StudentProfile
{
    /** Scalar columns safe to mass-assign (file paths and tracking excluded). */
    private const SCALAR_COLUMNS = [
        'blood_group','mother_tongue','religion','caste','caste_category','sub_caste',
        'nationality','place_of_birth','aadhaar_number','student_email','alternate_mobile',
        'marital_status','physically_challenged','disability_nature','first_graduate',
        'annual_family_income',
        'perm_address1','perm_address2','perm_city','perm_taluk_id','perm_district_id',
        'perm_state_id','perm_pincode','comm_same_as_perm','comm_address1','comm_address2',
        'comm_city','comm_taluk_id','comm_district_id','comm_state_id','comm_pincode',
        'family_situation','father_name','father_occupation','father_qualification',
        'father_annual_income','father_mobile','father_email','mother_name','mother_occupation',
        'mother_qualification','mother_annual_income','mother_mobile','mother_email',
        'guardian_name','guardian_relationship','guardian_mobile','guardian_address',
        'guardian_email',
        'admission_type','entrance_exam_name','entrance_hall_ticket','entrance_rank_score',
        'admission_number','community_cert_number','transfer_cert_number',
        'bank_account_holder','bank_name','bank_branch','bank_account_number','bank_ifsc',
        'scholarship_applied','scholarship_scheme','scholarship_app_number',
    ];

    /** JSON qualification columns. */
    private const JSON_COLUMNS = [
        'qual_sslc','qual_hsc','qual_ug','qual_diploma','qual_other_1','qual_other_2',
    ];

    /** File path columns. */
    private const PATH_COLUMNS = [
        'passport_photo_path','qual_sslc_doc_path','qual_hsc_doc_path','qual_ug_doc_path',
        'qual_diploma_doc_path','community_cert_path','transfer_cert_path','conduct_cert_path',
        'migration_cert_path','income_cert_path','nativity_cert_path','aadhaar_copy_path',
        'bank_passbook_path',
    ];

    public static function findByStudent(int $studentId): ?array
    {
        $row = Db::selectOne(
            "SELECT sp.*,
                    pt.name AS perm_taluk_name,   pd.name AS perm_district_name,  ps.name AS perm_state_name,
                    ct.name AS comm_taluk_name,   cd.name AS comm_district_name,  cs.name AS comm_state_name
             FROM student_profiles sp
             LEFT JOIN taluks    pt ON pt.id = sp.perm_taluk_id
             LEFT JOIN districts pd ON pd.id = sp.perm_district_id
             LEFT JOIN states    ps ON ps.id = sp.perm_state_id
             LEFT JOIN taluks    ct ON ct.id = sp.comm_taluk_id
             LEFT JOIN districts cd ON cd.id = sp.comm_district_id
             LEFT JOIN states    cs ON cs.id = sp.comm_state_id
             WHERE sp.student_id = ?",
            [$studentId]
        );
        if (!$row) return null;
        // Decode JSON columns
        foreach (self::JSON_COLUMNS as $col) {
            if (isset($row[$col]) && is_string($row[$col])) {
                $row[$col] = json_decode($row[$col], true) ?? null;
            }
        }
        return $row;
    }

    /**
     * Insert or update only the columns present in $data.
     * Never overwrites columns absent from $data.
     * Encodes JSON columns automatically.
     * Updates last_saved_at.
     */
    public static function upsert(int $studentId, array $data): void
    {
        // Encode JSON qual fields
        foreach (self::JSON_COLUMNS as $col) {
            if (array_key_exists($col, $data) && is_array($data[$col])) {
                $data[$col] = json_encode($data[$col]);
            }
        }

        // Only allow known columns (scalar + json + path)
        $allowed = array_merge(self::SCALAR_COLUMNS, self::JSON_COLUMNS, self::PATH_COLUMNS);
        $data    = array_intersect_key($data, array_flip($allowed));

        if (empty($data)) {
            // Just touch last_saved_at
            Db::execute(
                "INSERT INTO student_profiles (student_id, last_saved_at)
                 VALUES (?, NOW())
                 ON DUPLICATE KEY UPDATE last_saved_at = NOW()",
                [$studentId]
            );
            return;
        }

        $setClauses = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $params     = array_values($data);

        Db::execute(
            "INSERT INTO student_profiles (student_id, " . implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data))) . ", last_saved_at)
             VALUES (?, " . implode(', ', array_fill(0, count($data), '?')) . ", NOW())
             ON DUPLICATE KEY UPDATE {$setClauses}, last_saved_at = NOW()",
            array_merge([$studentId], $params, $params)
        );
    }

    /**
     * Update only the form_completion_pct after a save.
     */
    public static function updateCompletion(int $studentId, int $pct): void
    {
        Db::execute(
            "UPDATE student_profiles SET form_completion_pct = ? WHERE student_id = ?",
            [$pct, $studentId]
        );
    }

    /**
     * Submit the form: lock it + advance onboarding_status.
     * Runs in a transaction.
     */
    public static function submit(int $studentId): void
    {
        $pdo = Db::conn();
        $pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            Db::execute(
                "UPDATE student_profiles
                 SET form_status = 'submitted', form_submitted_at = ?,
                     form_completion_pct = 100, last_saved_at = ?
                 WHERE student_id = ?",
                [$now, $now, $studentId]
            );
            Db::execute(
                "UPDATE students SET onboarding_status = 'form_submitted' WHERE id = ?",
                [$studentId]
            );
            $profile = Db::selectOne("SELECT id FROM student_profiles WHERE student_id = ?", [$studentId]);
            $pdo->commit();
            MasterAuditLogger::log('student_form_submitted', 'student_profile', (int)($profile['id'] ?? 0), [
                'student_id' => $studentId,
            ]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Returns completion summary for a student.
     * ['pct' => int, 'sections' => [1=>bool, ...], 'missing' => [key=>label]]
     */
    public static function getCompletionSummary(array $profile, array $student): array
    {
        $rules    = FormFieldRules::getApplicableFields($profile, $student);
        $pct      = FormFieldRules::computeCompletion($profile, $rules);
        $sections = [];
        $missing  = [];

        foreach (FormFieldRules::sectionLabels() as $sec => $label) {
            $secFields  = array_filter($rules, fn($f) => $f['section'] === $sec && $f['required'] && $f['visible']);
            $allFilled  = true;
            foreach ($secFields as $f) {
                $val = $profile[$f['key']] ?? null;
                $filled = self::isFieldFilled($f, $val);
                if (!$filled) {
                    $allFilled = false;
                    $missing[$f['key']] = $f['label'];
                }
            }
            $sections[$sec] = $allFilled;
        }

        return compact('pct', 'sections', 'missing');
    }

    private static function isFieldFilled(array $field, mixed $val): bool
    {
        if ($field['type'] === 'json_qual') {
            $data = is_array($val) ? $val : (is_string($val) ? json_decode($val, true) : null);
            if (!is_array($data)) return false;
            foreach (['exam','board','institution','year','percentage'] as $k) {
                if (empty($data[$k])) return false;
            }
            return true;
        }
        if (in_array($field['type'], ['file','photo'], true)) return !empty($val);
        if (in_array($field['type'], ['radio','checkbox'], true)) return $val !== null && $val !== '';
        return $val !== null && trim((string)$val) !== '';
    }

    /**
     * Apply a pre-validated changeset (from an approved RTC) to the student's profile.
     * Performs a single batch UPDATE.
     * Must be called inside the caller's transaction — does NOT open its own.
     *
     * @param int   $studentId
     * @param array $data  field_key => new_value
     */
    public static function applyChangeset(int $studentId, array $data): void
    {
        if (empty($data)) return;

        foreach (self::JSON_COLUMNS as $col) {
            if (array_key_exists($col, $data) && is_array($data[$col])) {
                $data[$col] = json_encode($data[$col]);
            }
        }

        $allowed = array_merge(self::SCALAR_COLUMNS, self::JSON_COLUMNS, self::PATH_COLUMNS);
        $data    = array_intersect_key($data, array_flip($allowed));

        if (empty($data)) return;

        $now        = date('Y-m-d H:i:s');
        $setClauses = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $params     = array_values($data);
        $params[]   = $now;
        $params[]   = $studentId;

        Db::execute(
            "UPDATE student_profiles SET {$setClauses}, last_saved_at = ? WHERE student_id = ?",
            $params
        );
    }
}
