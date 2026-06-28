<?php
namespace App\Helpers;

use App\Models\Student;

class DuplicateDetector
{
    /**
     * Check if a student record would be a duplicate.
     * Returns null if no duplicate, or:
     * ['type' => 'mobile_exists'|'name_dob_exists'|'both', 'existing_student_id' => N]
     */
    public static function check(array $data, ?int $excludeId = null): ?array
    {
        $mobileMatch = Student::findAnyByMobile($data['mobile'] ?? '');
        if ($excludeId && $mobileMatch && (int)$mobileMatch['id'] === $excludeId) {
            $mobileMatch = null;
        }

        $dob = OnboardingValidator::toDbDate($data['dob'] ?? '') ?? ($data['dob'] ?? '');
        $nameDobMatch = Student::findByNameDob(
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $dob
        );
        if ($excludeId && $nameDobMatch && (int)$nameDobMatch['id'] === $excludeId) {
            $nameDobMatch = null;
        }

        if ($mobileMatch && $nameDobMatch) {
            return ['type' => 'both', 'existing_student_id' => (int)$mobileMatch['id']];
        }
        if ($mobileMatch) {
            return ['type' => 'mobile_exists', 'existing_student_id' => (int)$mobileMatch['id']];
        }
        if ($nameDobMatch) {
            return ['type' => 'name_dob_exists', 'existing_student_id' => (int)$nameDobMatch['id']];
        }
        return null;
    }
}
