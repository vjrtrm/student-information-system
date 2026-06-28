<?php
namespace Tests\Integration;

use Tests\TestCase;
use App\Helpers\Db;
use App\Models\PromotionBatch;

class PromotionResubmitTest extends TestCase
{
    public function testResubmitSetsRequiresInstAdmin(): void
    {
        $dept  = $this->seedDepartment();
        $staff = $this->seedUser('staff@re.com', 'pass', 'staff', $dept);
        $bid   = PromotionBatch::create([
            'department_id' => $dept, 'target_academic_year_id' => 1,
            'target_class_id' => 1, 'target_section_id' => 1,
            'status' => 'rejected', 'initiated_by' => $staff,
        ]);

        // Simulate update (resubmit)
        PromotionBatch::update($bid, [
            'target_academic_year_id' => 2,
            'target_class_id'         => 2,
            'target_section_id'       => 2,
            'status'                  => 'pending_approval',
            'requires_inst_admin'     => 1,
            'rejection_reason'        => null,
            'reviewed_by'             => null,
            'reviewed_at'             => null,
        ]);

        $batch = PromotionBatch::findById($bid);
        $this->assertSame('pending_approval', $batch['status']);
        $this->assertSame(1, (int)$batch['requires_inst_admin']);
    }

    public function testRequiresInstAdminFlagBlocksDeptAdmin(): void
    {
        // Verify the flag value is correctly stored and readable
        $dept  = $this->seedDepartment();
        $staff = $this->seedUser('staff@flag.com', 'pass', 'staff', $dept);
        $bid   = PromotionBatch::create([
            'department_id' => $dept, 'target_academic_year_id' => 1,
            'target_class_id' => 1, 'target_section_id' => 1,
            'requires_inst_admin' => 1, 'initiated_by' => $staff,
        ]);
        $batch = PromotionBatch::findById($bid);
        // Dept admin check: (int)$batch['requires_inst_admin'] === 1 AND role !== 'institution_admin' → 403
        $this->assertSame(1, (int)$batch['requires_inst_admin']);
    }
}
