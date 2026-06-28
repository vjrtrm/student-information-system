<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\UploadBatch;

class UploadBatchModelTest extends TestCase
{
    private int $deptId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptId = $this->seedDepartment();
        $this->userId = $this->seedUser('staff@test.com', 'pass', 'staff', $this->deptId);
    }

    public function testCreate(): void
    {
        $id = UploadBatch::create($this->deptId, $this->userId, 'students.xlsx', 50);
        $this->assertGreaterThan(0, $id);
    }

    public function testFind(): void
    {
        $id = UploadBatch::create($this->deptId, $this->userId, 'test.xlsx', 10);
        $batch = UploadBatch::find($id);
        $this->assertNotNull($batch);
        $this->assertSame($this->deptId, (int)$batch['department_id']);
        $this->assertSame('test.xlsx', $batch['original_filename']);
        $this->assertSame(10, (int)$batch['total_rows']);
    }

    public function testFindReturnsNullForMissing(): void
    {
        $this->assertNull(UploadBatch::find(99999));
    }

    public function testUpdateCounts(): void
    {
        $id = UploadBatch::create($this->deptId, $this->userId, 'test.xlsx', 20);
        UploadBatch::updateCounts($id, 15, 3, 2);
        $batch = UploadBatch::find($id);
        $this->assertSame(15, (int)$batch['created_count']);
        $this->assertSame(3, (int)$batch['duplicate_held_count']);
        $this->assertSame(2, (int)$batch['failed_count']);
    }

    public function testIncrementCreated(): void
    {
        $id = UploadBatch::create($this->deptId, $this->userId, 'test.xlsx', 5);
        UploadBatch::updateCounts($id, 2, 0, 0);
        UploadBatch::incrementCreated($id);
        $batch = UploadBatch::find($id);
        $this->assertSame(3, (int)$batch['created_count']);
    }
}
