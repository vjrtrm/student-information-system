<?php
namespace Tests\Integration;

use App\Helpers\FormFieldRules;
use Tests\TestCase;

/**
 * Verifies that mother fields are excluded from the required-field count
 * when family_situation is 'guardian', so computeCompletion does not
 * penalise a guardian-only student for missing mother details.
 */
class ConditionalFieldTest extends TestCase
{
    private function student(): array
    {
        return ['programme_level' => 'UG', 'department_id' => 1];
    }

    public function test_guardian_situation_mother_fields_not_in_required_count(): void
    {
        $profile = ['family_situation' => 'guardian'];
        $rules   = FormFieldRules::getApplicableFields($profile, $this->student());

        $requiredKeys = array_column(
            array_filter($rules, fn($f) => $f['required'] && $f['visible']),
            'key'
        );

        // mother_name must NOT be in required fields for guardian
        $this->assertNotContains('mother_name', $requiredKeys);
        $this->assertNotContains('mother_occupation', $requiredKeys);
        $this->assertNotContains('mother_mobile', $requiredKeys);
    }

    public function test_guardian_situation_guardian_fields_are_required(): void
    {
        $profile = ['family_situation' => 'guardian'];
        $rules   = FormFieldRules::getApplicableFields($profile, $this->student());

        $requiredKeys = array_column(
            array_filter($rules, fn($f) => $f['required'] && $f['visible']),
            'key'
        );

        $this->assertContains('guardian_name', $requiredKeys);
        $this->assertContains('guardian_mobile', $requiredKeys);
        $this->assertContains('guardian_relationship', $requiredKeys);
    }

    public function test_compute_completion_guardian_no_mother_penalty(): void
    {
        // A guardian profile with all guardian fields filled (plus other required scalars)
        // should score higher than if mother fields were counted as missing.
        $profile = [
            'family_situation'    => 'guardian',
            'blood_group'         => 'O+',
            'mother_tongue'       => 'Tamil',
            'religion'            => 'Hindu',
            'caste'               => 'Vellalar',
            'caste_category'      => 'OBC',
            'nationality'         => 'Indian',
            'place_of_birth'      => 'Chennai',
            'aadhaar_number'      => '123456789012',
            'student_email'       => 'test@test.com',
            'marital_status'      => 'Single',
            'physically_challenged' => 0,
            'first_graduate'      => 1,
            'annual_family_income'=> 100000,
            'perm_address1'       => '1 Main',
            'perm_city'           => 'Chennai',
            'perm_taluk_id'       => 1,
            'perm_district_id'    => 1,
            'perm_state_id'       => 1,
            'perm_pincode'        => '600001',
            'comm_same_as_perm'   => 0,
            'comm_address1'       => '2 Other',
            'comm_city'           => 'Chennai',
            'comm_taluk_id'       => 1,
            'comm_district_id'    => 1,
            'comm_state_id'       => 1,
            'comm_pincode'        => '600001',
            'father_name'         => 'Father',
            'guardian_name'       => 'Uncle Joe',
            'guardian_relationship'=> 'Uncle',
            'guardian_mobile'     => '9876543210',
            'guardian_address'    => '3 Guardian Road',
            'admission_type'      => 'government',
            'admission_number'    => 'ADM001',
            // no mother fields
        ];

        $rules = FormFieldRules::getApplicableFields($profile, $this->student());
        $pct   = FormFieldRules::computeCompletion($profile, $rules);

        // Mother fields absent — ensure they are not penalising the score by checking
        // that at least some completion is achieved (> 0) without mother data
        $this->assertGreaterThan(0, $pct);

        // Also verify: if we force-compute with both_parents (no guardian data),
        // the score should be lower because mother is required
        $profileBoth = array_merge($profile, ['family_situation' => 'both_parents']);
        $rulesBoth   = FormFieldRules::getApplicableFields($profileBoth, $this->student());
        $pctBoth     = FormFieldRules::computeCompletion($profileBoth, $rulesBoth);

        // guardian profile (no mother required) should score >= both_parents (mother required, missing)
        $this->assertGreaterThanOrEqual($pctBoth, $pct);
    }
}
