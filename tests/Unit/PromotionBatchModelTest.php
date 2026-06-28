<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\Db;
use App\Models\PromotionBatch;

class PromotionBatchModelTest extends TestCase
{
    public function testIsWindowOpenFalseByDefault(): void
    {
        $this->assertFalse(PromotionBatch::isWindowOpen());
    }

    public function testIsWindowOpenTrueWhenSet(): void
    {
        Db::execute("UPDATE settings SET value = '1' WHERE key = 'promotion_window_open'");
        $this->assertTrue(PromotionBatch::isWindowOpen());
    }

    public function testFindPendingForDeptReturnsNullWhenNone(): void
    {
        $dept = $this->seedDepartment();
        $this->assertNull(PromotionBatch::findPendingForDept($dept));
    }

    public function testCreateAndFindById(): void
    {
        $dept = $this->seedDepartment();
        $user = $this->seedUser('staff@test.com', 'pass', 'staff', $dept);
        $id = PromotionBatch::create([
            'department_id'           => $dept,
            'target_academic_year_id' => 1,
            'target_class_id'         => 1,
            'target_section_id'       => 1,
            'initiated_by'            => $user,
        ]);
        $this->assertGreaterThan(0, $id);
        $batch = PromotionBatch::findById($id);
        $this->assertNotNull($batch);
        $this->assertSame('pending_approval', $batch['status']);
        $this->assertSame(0, (int)$batch['requires_inst_admin']);
    }

    public function testFindPendingForDeptReturnsRow(): void
    {
        $dept = $this->seedDepartment();
        $user = $this->seedUser('staff2@test.com', 'pass', 'staff', $dept);
        PromotionBatch::create([
            'department_id'           => $dept,
            'target_academic_year_id' => 1,
            'target_class_id'         => 1,
            'target_section_id'       => 1,
            'initiated_by'            => $user,
        ]);
        $this->assertNotNull(PromotionBatch::findPendingForDept($dept));
    }
}
