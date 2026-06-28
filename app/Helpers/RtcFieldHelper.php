<?php
namespace App\Helpers;

/**
 * Builds a structured changeset from an RTC POST submission.
 * Each entry: [field_key, label, current_value, proposed_value, is_file => false]
 * File fields are handled separately by RtcUploadHandler and appended after.
 */
class RtcFieldHelper
{
    /** Fields from onboarding that cannot be changed via RTC. */
    private const LOCKED_KEYS = ['first_name','last_name','dob','mobile','id','student_id'];

    /**
     * @param array $postedFields  field_key => proposed_value from $_POST['fields']
     * @param array $currentProfile StudentProfile::findByStudent() result (or [])
     * @param array $student        Student::find() result
     * @return array changeset entries
     * @throws \InvalidArgumentException on validation failure or empty changeset
     */
    public static function buildChangeset(array $postedFields, array $currentProfile, array $student): array
    {
        if (empty($postedFields)) {
            throw new \InvalidArgumentException('No changes specified.');
        }

        // Build label map from FormFieldRules
        $rules    = FormFieldRules::getApplicableFields($currentProfile, $student);
        $labelMap = [];
        foreach ($rules as $f) {
            $labelMap[$f['key']] = $f['label'];
        }

        $changeset = [];
        foreach ($postedFields as $key => $proposedValue) {
            $key = (string)$key;

            if (in_array($key, self::LOCKED_KEYS, true)) {
                throw new \InvalidArgumentException("Field '{$key}' cannot be changed via RTC.");
            }
            if (!array_key_exists($key, $labelMap)) {
                throw new \InvalidArgumentException("Unknown field key: '{$key}'.");
            }

            $currentValue = $currentProfile[$key] ?? null;
            if (is_array($currentValue)) {
                $currentValue = json_encode($currentValue);
            }
            $proposed = is_string($proposedValue) ? trim($proposedValue) : $proposedValue;

            // Skip no-op entries (same value)
            if ((string)$currentValue === (string)$proposed) {
                continue;
            }

            $changeset[] = [
                'field_key'      => $key,
                'label'          => $labelMap[$key],
                'current_value'  => $currentValue,
                'proposed_value' => $proposed,
                'is_file'        => false,
            ];
        }

        if (empty($changeset)) {
            throw new \InvalidArgumentException('No changes specified.');
        }

        return $changeset;
    }
}
