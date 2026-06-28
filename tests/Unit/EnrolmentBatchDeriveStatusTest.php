<?php
namespace Tests\Unit;

use App\Models\EnrolmentBatch;
use Tests\TestCase;

/**
 * Tests EnrolmentBatch::deriveStatus() using SQLite in-memory.
 */
class EnrolmentBatchDeriveStatusTest extends TestCase
{
    private int $deptId;
    private int $userId;
    private int $ayId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo->exec("INSERT INTO departments (name, code, level) VALUES ('Test Dept', 'BCA', 'UG')");
        $this->deptId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO users (name, email, password_hash, role) VALUES ('Admin', 'a@a.com', 'x', 'dept_admin')");
        $this->userId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO option_lists (list_key, label) VALUES ('academic_year', 'Academic Year')");
        $listId = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO option_values (list_id, value, display) VALUES ({$listId}, '2024-25', '2024-25')");
        $this->ayId = (int)$this->pdo->lastInsertId();
    }

    private function makeBatch(): int
    {
        $this->pdo->prepare(
            "INSERT INTO enrolment_batches (department_id, academic_year_id, generated_by, student_count, created_at)
             VALUES (?, ?, ?, 0, ?)"
        )->execute([$this->deptId, $this->ayId, $this->userId, date('Y-m-d H:i:s')]);
        return (int)$this->pdo->lastInsertId();
    }

    private function insertStudent(int $batchId, string $approvalStatus): int
    {
        $mobile = '9' . str_pad((string)rand(0, 999999999), 9, '0', STR_PAD_LEFT);
        $this->pdo->prepare(
            "INSERT INTO students
             (mobile, dob, department_id, academic_year_id,
              enrolment_number, enrolment_serial, enrolment_approval_status, enrolment_batch_id)
             VALUES (?, '2000-01-01', ?, ?, 'NUM001', 1, ?, ?)"
        )->execute([$mobile, $this->deptId, $this->ayId, $approvalStatus, $batchId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function testAllPendingReturnsPending(): void
    {
        $batchId = $this->makeBatch();
        $this->insertStudent($batchId, 'pending');
        $this->insertStudent($batchId, 'pending');
        $this->insertStudent($batchId, 'pending');
        $this->assertSame('pending', EnrolmentBatch::deriveStatus($batchId));
    }

    public function testMixedReturnsInProgress(): void
    {
        $batchId = $this->makeBatch();
        $this->insertStudent($batchId, 'pending');
        $this->insertStudent($batchId, 'approved');
        $this->insertStudent($batchId, 'pending');
        $this->assertSame('in_progress', EnrolmentBatch::deriveStatus($batchId));
    }

    public function testAllApprovedReturnsApproved(): void
    {
        $batchId = $this->makeBatch();
        $this->insertStudent($batchId, 'approved');
        $this->insertStudent($batchId, 'approved');
        $this->assertSame('approved', EnrolmentBatch::deriveStatus($batchId));
    }

    public function testEmptyBatchReturnsApproved(): void
    {
        $batchId = $this->makeBatch();
        // no students
        $this->assertSame('approved', EnrolmentBatch::deriveStatus($batchId));
    }

    public function testSinglePendingReturnsPending(): void
    {
        $batchId = $this->makeBatch();
        $this->insertStudent($batchId, 'pending');
        $this->assertSame('pending', EnrolmentBatch::deriveStatus($batchId));
    }

    public function testSingleApprovedReturnsApproved(): void
    {
        $batchId = $this->makeBatch();
        $this->insertStudent($batchId, 'approved');
        $this->assertSame('approved', EnrolmentBatch::deriveStatus($batchId));
    }
}
