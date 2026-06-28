<?php
namespace Tests\Integration;

use Tests\TestCase;
use App\Helpers\Db;
use App\Models\PromotionBatch;

class PromotionBatchCreateTest extends TestCase
{
    public function testCreateBatchWithInclusionsAndExclusions(): void
    {
        $dept = $this->seedDepartment();
        $user = $this->seedUser('staff@dept.com', 'pass', 'staff', $dept);
        $s1   = $this->seedFullStudent(['department_id' => $dept, 'onboarding_status' => 'active', 'enrolment_approval_status' => 'approved']);
        $s2   = $this->seedFullStudent(['department_id' => $dept, 'onboarding_status' => 'active', 'enrolment_approval_status' => 'approved']);
        $s3   = $this->seedFullStudent(['department_id' => $dept, 'onboarding_status' => 'active', 'enrolment_approval_status' => 'approved']);

        $batchId = PromotionBatch::create([
            'department_id'           => $dept,
            'target_academic_year_id' => 2,
            'target_class_id'         => 2,
            'target_section_id'       => 1,
            'initiated_by'            => $user,
        ]);

        // Include s1, s2; exclude s3
        Db::execute('INSERT INTO promotion_batch_students (batch_id, student_id) VALUES (?,?)', [$batchId, $s1]);
        Db::execute('INSERT INTO promotion_batch_students (batch_id, student_id) VALUES (?,?)', [$batchId, $s2]);
        Db::execute('INSERT INTO promotion_exclusions (batch_id, student_id, reason) VALUES (?,?,?)', [$batchId, $s3, 'Detained']);

        $included = PromotionBatch::getIncluded($batchId);
        $excluded = PromotionBatch::getExcluded($batchId);

        $this->assertCount(2, $included);
        $this->assertCount(1, $excluded);
        $this->assertSame('Detained', $excluded[0]['reason']);
    }

    public function testAuditLogEntryOnCreate(): void
    {
        $dept = $this->seedDepartment();
        $user = $this->seedUser('audit@test.com', 'pass', 'staff', $dept);
        $bid  = PromotionBatch::create([
            'department_id' => $dept, 'target_academic_year_id' => 1,
            'target_class_id' => 1, 'target_section_id' => 1, 'initiated_by' => $user,
        ]);
        \App\Helpers\MasterAuditLogger::log('create', 'promotion_batch', $bid, ['dept_id' => $dept]);
        $entry = Db::selectOne("SELECT * FROM audit_log WHERE action='create' AND entity='promotion_batch'");
        $this->assertNotNull($entry);
    }
}
