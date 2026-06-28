<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\DuplicateOverrideRequest;
use App\Models\UploadBatch;

class DuplicateOverrideRequestModelTest extends TestCase
{
    private int $deptId;
    private int $userId;
    private int $batchId;
    private int $existingStudentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptId = $this->seedDepartment();
        $this->userId = $this->seedUser('staff@test.com', 'pass', 'staff', $this->deptId);
        $this->batchId = UploadBatch::create($this->deptId, $this->userId, 'test.xlsx', 5);
        $this->existingStudentId = $this->seedFullStudent(['department_id' => $this->deptId, 'mobile' => '9000000001']);
    }

    private function makeOverride(array $overrides = []): array
    {
        return array_merge([
            'upload_batch_id'    => $this->batchId,
            'source_row_number'  => 2,
            'student_data'       => ['first_name' => 'Test', 'mobile' => '9000000002'],
            'flagged_reason'     => 'mobile_exists',
            'existing_student_id'=> $this->existingStudentId,
            'requested_by'       => $this->userId,
        ], $overrides);
    }

    public function testCreate(): void
    {
        $id = DuplicateOverrideRequest::create($this->makeOverride());
        $this->assertGreaterThan(0, $id);
    }

    public function testFindReturnsRow(): void
    {
        $id = DuplicateOverrideRequest::create($this->makeOverride());
        $row = DuplicateOverrideRequest::find($id);
        $this->assertNotNull($row);
        $this->assertSame('pending', $row['status']);
        $this->assertSame('mobile_exists', $row['flagged_reason']);
    }

    public function testFindPendingByBatch(): void
    {
        DuplicateOverrideRequest::create($this->makeOverride());
        $rows = DuplicateOverrideRequest::findPendingByBatch($this->batchId);
        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('ex_first', $rows[0]);
    }

    public function testFindPendingByBatchExcludesNonPending(): void
    {
        $id = DuplicateOverrideRequest::create($this->makeOverride());
        DuplicateOverrideRequest::updateReasonAndStatus($id, 'skipped', 'rejected');
        $rows = DuplicateOverrideRequest::findPendingByBatch($this->batchId);
        $this->assertEmpty($rows);
    }

    public function testApproveChangesStatus(): void
    {
        $adminId = $this->seedUser('admin@test.com', 'pass', 'dept_admin', $this->deptId);
        $id = DuplicateOverrideRequest::create($this->makeOverride());
        DuplicateOverrideRequest::approve($id, $adminId);
        $row = DuplicateOverrideRequest::find($id);
        $this->assertSame('approved', $row['status']);
        $this->assertSame($adminId, (int)$row['reviewed_by']);
        $this->assertNotNull($row['reviewed_at']);
    }

    public function testRejectChangesStatus(): void
    {
        $adminId = $this->seedUser('admin2@test.com', 'pass', 'dept_admin', $this->deptId);
        $id = DuplicateOverrideRequest::create($this->makeOverride());
        DuplicateOverrideRequest::reject($id, $adminId);
        $row = DuplicateOverrideRequest::find($id);
        $this->assertSame('rejected', $row['status']);
    }

    public function testUpdateReasonAndStatus(): void
    {
        $id = DuplicateOverrideRequest::create($this->makeOverride());
        DuplicateOverrideRequest::updateReasonAndStatus($id, 'Different person, verified by staff.', 'pending');
        $row = DuplicateOverrideRequest::find($id);
        $this->assertStringContainsString('Different person', $row['reason_note']);
    }
}
