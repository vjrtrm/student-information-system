<?php
namespace App\Helpers;

class OnboardingValidator
{
    /**
     * Validate an onboarding data array.
     * $data keys: first_name, last_name, dob (DD/MM/YYYY), mobile, gender,
     *             department_id, academic_year_id, class_id, section_id (optional),
     *             admission_date (DD/MM/YYYY)
     * $staffDeptId: the department the staff member belongs to (for dept validation)
     * Returns: ['field' => 'error message', ...] — empty means valid
     */
    public static function validate(array $data, int $staffDeptId): array
    {
        $errors = [];

        // First Name
        $fn = trim($data['first_name'] ?? '');
        if ($fn === '') {
            $errors['first_name'] = 'First Name is required.';
        } elseif (mb_strlen($fn) > 100) {
            $errors['first_name'] = 'First Name must be 100 characters or fewer.';
        } elseif (!preg_match("/^[\p{L}\s\-']+$/u", $fn)) {
            $errors['first_name'] = 'First Name may only contain letters, spaces, hyphens and apostrophes.';
        }

        // Last Name
        $ln = trim($data['last_name'] ?? '');
        if ($ln === '') {
            $errors['last_name'] = 'Last Name is required.';
        } elseif (mb_strlen($ln) > 100) {
            $errors['last_name'] = 'Last Name must be 100 characters or fewer.';
        } elseif (!preg_match("/^[\p{L}\s\-']+$/u", $ln)) {
            $errors['last_name'] = 'Last Name may only contain letters, spaces, hyphens and apostrophes.';
        }

        // Date of Birth
        $dobRaw = trim($data['dob'] ?? '');
        $dobObj  = \DateTime::createFromFormat('d/m/Y', $dobRaw);
        if (!$dobRaw || !$dobObj || $dobObj->format('d/m/Y') !== $dobRaw) {
            $errors['dob'] = 'Date of Birth must be in DD/MM/YYYY format.';
        } else {
            // Age >= 15 from admission_date (or today)
            $admRaw = trim($data['admission_date'] ?? '');
            $ref = \DateTime::createFromFormat('d/m/Y', $admRaw) ?: new \DateTime();
            $age = $dobObj->diff($ref)->y;
            if ($age < 15) {
                $errors['dob'] = 'Student must be at least 15 years old on admission date.';
            }
        }

        // Mobile
        $mobile = trim($data['mobile'] ?? '');
        if (!preg_match('/^\d{10}$/', $mobile)) {
            $errors['mobile'] = 'Mobile must be exactly 10 digits.';
        }

        // Gender
        if (!in_array($data['gender'] ?? '', ['male', 'female', 'other'], true)) {
            $errors['gender'] = 'Gender must be Male, Female, or Other.';
        }

        // Department
        $deptId = (int)($data['department_id'] ?? 0);
        if ($deptId <= 0) {
            $errors['department_id'] = 'Department is required.';
        } elseif ($deptId !== $staffDeptId) {
            $errors['department_id'] = 'Department does not match your department.';
        }

        // Academic Year
        if (empty($data['academic_year_id'])) {
            $errors['academic_year_id'] = 'Academic Year is required.';
        }

        // Class
        if (empty($data['class_id'])) {
            $errors['class_id'] = 'Class is required.';
        }

        // Admission Date (future dates are allowed — students can be registered for upcoming terms)
        $admRaw = trim($data['admission_date'] ?? '');
        $admObj = \DateTime::createFromFormat('d/m/Y', $admRaw);
        if (!$admRaw || !$admObj || $admObj->format('d/m/Y') !== $admRaw) {
            $errors['admission_date'] = 'Admission Date must be in DD/MM/YYYY format.';
        }

        return $errors;
    }

    /** Convert DD/MM/YYYY to Y-m-d for DB storage. Returns null on failure. */
    public static function toDbDate(string $dmY): ?string
    {
        $d = \DateTime::createFromFormat('d/m/Y', $dmY);
        return $d ? $d->format('Y-m-d') : null;
    }
}
