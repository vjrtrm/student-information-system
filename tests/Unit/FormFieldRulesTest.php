<?php
namespace Tests\Unit;

use App\Helpers\FormFieldRules;
use PHPUnit\Framework\TestCase;

class FormFieldRulesTest extends TestCase
{
    private function student(string $level = 'UG'): array
    {
        return ['programme_level' => $level, 'department_id' => 1];
    }

    private function findField(array $fields, string $key): ?array
    {
        foreach ($fields as $f) {
            if ($f['key'] === $key) return $f;
        }
        return null;
    }

    // ── Both Parents + UG ──────────────────────────────────────────────────

    public function test_both_parents_ug_father_name_required(): void
    {
        $profile = ['family_situation' => 'both_parents'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'father_name');
        $this->assertNotNull($f);
        $this->assertTrue($f['required']);
        $this->assertTrue($f['visible']);
    }

    public function test_both_parents_ug_mother_name_required(): void
    {
        $profile = ['family_situation' => 'both_parents'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'mother_name');
        $this->assertNotNull($f);
        $this->assertTrue($f['required']);
        $this->assertTrue($f['visible']);
    }

    public function test_both_parents_ug_guardian_name_not_visible(): void
    {
        $profile = ['family_situation' => 'both_parents'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'guardian_name');
        $this->assertNotNull($f);
        $this->assertFalse($f['visible']);
    }

    public function test_both_parents_ug_qual_ug_not_visible(): void
    {
        $profile = ['family_situation' => 'both_parents'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'qual_ug');
        $this->assertNotNull($f);
        $this->assertFalse($f['visible']);
    }

    // ── Both Parents + PG ──────────────────────────────────────────────────

    public function test_both_parents_pg_qual_ug_visible_and_required(): void
    {
        $profile = ['family_situation' => 'both_parents'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('PG'));
        $f       = $this->findField($fields, 'qual_ug');
        $this->assertNotNull($f);
        $this->assertTrue($f['visible']);
        $this->assertTrue($f['required']);
    }

    // ── Single Parent Father + UG ──────────────────────────────────────────

    public function test_single_parent_father_ug_father_name_required(): void
    {
        $profile = ['family_situation' => 'single_parent_father'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'father_name');
        $this->assertNotNull($f);
        $this->assertTrue($f['required']);
    }

    public function test_single_parent_father_ug_father_occupation_required(): void
    {
        $profile = ['family_situation' => 'single_parent_father'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'father_occupation');
        $this->assertNotNull($f);
        $this->assertTrue($f['required']);
    }

    public function test_single_parent_father_ug_mother_name_visible_but_not_required(): void
    {
        $profile = ['family_situation' => 'single_parent_father'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'mother_name');
        $this->assertNotNull($f);
        $this->assertTrue($f['visible']);
        $this->assertFalse($f['required']);
    }

    public function test_single_parent_father_ug_guardian_name_visible_but_not_required(): void
    {
        $profile = ['family_situation' => 'single_parent_father'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'guardian_name');
        $this->assertNotNull($f);
        $this->assertTrue($f['visible']);
        $this->assertFalse($f['required']);
    }

    // ── Single Parent Mother + UG ──────────────────────────────────────────

    public function test_single_parent_mother_ug_father_name_required(): void
    {
        $profile = ['family_situation' => 'single_parent_mother'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'father_name');
        $this->assertNotNull($f);
        $this->assertTrue($f['required']);
    }

    public function test_single_parent_mother_ug_mother_name_required(): void
    {
        $profile = ['family_situation' => 'single_parent_mother'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'mother_name');
        $this->assertNotNull($f);
        $this->assertTrue($f['required']);
    }

    public function test_single_parent_mother_ug_father_occupation_not_required(): void
    {
        $profile = ['family_situation' => 'single_parent_mother'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'father_occupation');
        $this->assertNotNull($f);
        $this->assertFalse($f['required']);
    }

    // ── Guardian + UG ─────────────────────────────────────────────────────

    public function test_guardian_ug_father_name_required(): void
    {
        $profile = ['family_situation' => 'guardian'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'father_name');
        $this->assertNotNull($f);
        $this->assertTrue($f['required']);
    }

    public function test_guardian_ug_guardian_name_required(): void
    {
        $profile = ['family_situation' => 'guardian'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'guardian_name');
        $this->assertNotNull($f);
        $this->assertTrue($f['required']);
    }

    public function test_guardian_ug_mother_name_not_visible(): void
    {
        $profile = ['family_situation' => 'guardian'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'mother_name');
        $this->assertNotNull($f);
        $this->assertFalse($f['visible']);
        $this->assertFalse($f['required']);
    }

    // ── Lateral Entry ──────────────────────────────────────────────────────

    public function test_lateral_entry_qual_diploma_visible_and_required(): void
    {
        $profile = ['family_situation' => 'both_parents', 'admission_type' => 'lateral_entry'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'qual_diploma');
        $this->assertNotNull($f);
        $this->assertTrue($f['visible']);
        $this->assertTrue($f['required']);
    }

    public function test_non_lateral_entry_qual_diploma_not_visible(): void
    {
        $profile = ['family_situation' => 'both_parents', 'admission_type' => 'government'];
        $fields  = FormFieldRules::getApplicableFields($profile, $this->student('UG'));
        $f       = $this->findField($fields, 'qual_diploma');
        $this->assertNotNull($f);
        $this->assertFalse($f['visible']);
    }

    // ── computeCompletion ──────────────────────────────────────────────────

    public function test_compute_completion_empty_profile_returns_zero(): void
    {
        $profile = [];  // no fields filled; family_situation defaults inside getApplicableFields
        $student = $this->student('UG');
        $rules   = FormFieldRules::getApplicableFields($profile, $student);
        $pct     = FormFieldRules::computeCompletion($profile, $rules);
        $this->assertSame(0, $pct);
    }

    public function test_compute_completion_partial_scalar_no_files(): void
    {
        $profile = [
            'family_situation'    => 'both_parents',
            'blood_group'         => 'O+',
            'mother_tongue'       => 'Tamil',
            'religion'            => 'Hindu',
            'caste'               => 'Vellalar',
            'caste_category'      => 'OBC',
            'nationality'         => 'Indian',
            'place_of_birth'      => 'Chennai',
            'aadhaar_number'      => '123456789012',
            'student_email'       => 'test@example.com',
            'marital_status'      => 'Single',
            'physically_challenged' => 0,
            'first_graduate'      => 1,
            'annual_family_income'=> 200000,
            'perm_address1'       => '123 Main St',
            'perm_city'           => 'Chennai',
            'perm_taluk_id'       => 1,
            'perm_district_id'    => 1,
            'perm_state_id'       => 1,
            'perm_pincode'        => '600001',
            'comm_same_as_perm'   => 0,
            'comm_address1'       => '456 Other St',
            'comm_city'           => 'Chennai',
            'comm_taluk_id'       => 1,
            'comm_district_id'    => 1,
            'comm_state_id'       => 1,
            'comm_pincode'        => '600001',
            'father_name'         => 'Father',
            'father_occupation'   => 'Farmer',
            'father_qualification'=> 'HSC',
            'father_annual_income'=> 150000,
            'father_mobile'       => '9876543210',
            'mother_name'         => 'Mother',
            'mother_occupation'   => 'Housewife',
            'mother_qualification'=> 'HSC',
            'mother_annual_income'=> 0,
            'mother_mobile'       => '9876543211',
            'admission_type'      => 'government',
            'admission_number'    => 'ADM001',
        ];
        $student = $this->student('UG');
        $rules   = FormFieldRules::getApplicableFields($profile, $student);
        $pct     = FormFieldRules::computeCompletion($profile, $rules);
        $this->assertGreaterThan(0, $pct);
        $this->assertLessThan(100, $pct);
    }

    public function test_compute_completion_with_files_increases_pct(): void
    {
        $baseProfile = [
            'family_situation'    => 'both_parents',
            'blood_group'         => 'O+',
            'mother_tongue'       => 'Tamil',
            'religion'            => 'Hindu',
            'caste'               => 'Vellalar',
            'caste_category'      => 'OBC',
            'nationality'         => 'Indian',
            'place_of_birth'      => 'Chennai',
            'aadhaar_number'      => '123456789012',
            'student_email'       => 'test@example.com',
            'marital_status'      => 'Single',
            'physically_challenged' => 0,
            'first_graduate'      => 1,
            'annual_family_income'=> 200000,
            'perm_address1'       => '123 Main St',
            'perm_city'           => 'Chennai',
            'perm_taluk_id'       => 1,
            'perm_district_id'    => 1,
            'perm_state_id'       => 1,
            'perm_pincode'        => '600001',
            'comm_same_as_perm'   => 0,
            'comm_address1'       => '456 Other St',
            'comm_city'           => 'Chennai',
            'comm_taluk_id'       => 1,
            'comm_district_id'    => 1,
            'comm_state_id'       => 1,
            'comm_pincode'        => '600001',
            'father_name'         => 'Father',
            'father_occupation'   => 'Farmer',
            'father_qualification'=> 'HSC',
            'father_annual_income'=> 150000,
            'father_mobile'       => '9876543210',
            'mother_name'         => 'Mother',
            'mother_occupation'   => 'Housewife',
            'mother_qualification'=> 'HSC',
            'mother_annual_income'=> 0,
            'mother_mobile'       => '9876543211',
            'admission_type'      => 'government',
            'admission_number'    => 'ADM001',
        ];
        $student  = $this->student('UG');
        $rules    = FormFieldRules::getApplicableFields($baseProfile, $student);
        $pctBefore = FormFieldRules::computeCompletion($baseProfile, $rules);

        $withFiles = $baseProfile + [
            'passport_photo_path' => 'storage/uploads/students/1/photo.jpg',
            'aadhaar_copy_path'   => 'storage/uploads/students/1/aadhaar.pdf',
        ];
        $rulesAfter = FormFieldRules::getApplicableFields($withFiles, $student);
        $pctAfter   = FormFieldRules::computeCompletion($withFiles, $rulesAfter);

        $this->assertGreaterThan($pctBefore, $pctAfter);
    }
}
