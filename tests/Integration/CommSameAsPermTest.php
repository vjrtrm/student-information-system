<?php
namespace Tests\Integration;

use Tests\TestCase;

/**
 * Tests the comm_same_as_perm data-copy logic directly on arrays,
 * mirroring what StudentFormController::save() does.
 */
class CommSameAsPermTest extends TestCase
{
    /**
     * Replicates the controller's comm-copy block for isolated testing.
     */
    private function applyCommSameAsPerm(array $data, array $profile): array
    {
        if (!empty($data['comm_same_as_perm'])) {
            $data['comm_address1']    = $data['perm_address1']    ?? ($profile['perm_address1']    ?? null);
            $data['comm_address2']    = $data['perm_address2']    ?? ($profile['perm_address2']    ?? null);
            $data['comm_city']        = $data['perm_city']        ?? ($profile['perm_city']        ?? null);
            $data['comm_taluk_id']    = $data['perm_taluk_id']    ?? ($profile['perm_taluk_id']    ?? null);
            $data['comm_district_id'] = $data['perm_district_id'] ?? ($profile['perm_district_id'] ?? null);
            $data['comm_state_id']    = $data['perm_state_id']    ?? ($profile['perm_state_id']    ?? null);
            $data['comm_pincode']     = $data['perm_pincode']     ?? ($profile['perm_pincode']     ?? null);
        }
        return $data;
    }

    public function test_comm_same_as_perm_copies_perm_values_from_post(): void
    {
        $data = [
            'comm_same_as_perm' => 1,
            'perm_address1'     => '10 Temple Street',
            'perm_address2'     => 'Near Park',
            'perm_city'         => 'Coimbatore',
            'perm_taluk_id'     => 5,
            'perm_district_id'  => 3,
            'perm_state_id'     => 2,
            'perm_pincode'      => '641001',
        ];

        $result = $this->applyCommSameAsPerm($data, []);

        $this->assertSame('10 Temple Street', $result['comm_address1']);
        $this->assertSame('Near Park',        $result['comm_address2']);
        $this->assertSame('Coimbatore',       $result['comm_city']);
        $this->assertSame(5,                  $result['comm_taluk_id']);
        $this->assertSame(3,                  $result['comm_district_id']);
        $this->assertSame(2,                  $result['comm_state_id']);
        $this->assertSame('641001',           $result['comm_pincode']);
    }

    public function test_comm_same_as_perm_falls_back_to_existing_profile(): void
    {
        $data = [
            'comm_same_as_perm' => 1,
            // perm fields not in $data (already saved in profile)
        ];

        $profile = [
            'perm_address1'   => '20 Church Road',
            'perm_city'       => 'Madurai',
            'perm_taluk_id'   => 8,
            'perm_district_id'=> 6,
            'perm_state_id'   => 2,
            'perm_pincode'    => '625001',
        ];

        $result = $this->applyCommSameAsPerm($data, $profile);

        $this->assertSame('20 Church Road', $result['comm_address1']);
        $this->assertSame('Madurai',        $result['comm_city']);
        $this->assertSame(8,                $result['comm_taluk_id']);
        $this->assertSame('625001',         $result['comm_pincode']);
    }

    public function test_comm_same_as_perm_false_does_not_overwrite_comm_fields(): void
    {
        $data = [
            'comm_same_as_perm' => 0,
            'perm_address1'     => '10 Temple Street',
            'comm_address1'     => '99 Different Road',
        ];

        $result = $this->applyCommSameAsPerm($data, []);

        // comm_address1 should remain what was set explicitly
        $this->assertSame('99 Different Road', $result['comm_address1']);
    }

    public function test_comm_same_as_perm_unset_does_not_overwrite_comm_fields(): void
    {
        $data = [
            'perm_address1' => '10 Temple Street',
            'comm_address1' => '77 Other Lane',
        ];

        $result = $this->applyCommSameAsPerm($data, []);

        $this->assertSame('77 Other Lane', $result['comm_address1']);
    }
}
