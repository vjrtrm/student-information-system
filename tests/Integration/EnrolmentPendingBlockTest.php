<?php
namespace Tests\Integration;

use App\Helpers\EnrolmentNumberGenerator;
use App\Models\Student;
use Tests\TestCase;

/**
 * Verifies that generating a new batch is blocked when an unapproved pending batch exists.
 */
class EnrolmentPendingBlockTest extends TestCase
{
    private int $deptId;
    private int $ayId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo->exec("INSERT INTO departments (name, code, level) VALUES ('BCA Dept', 'BCA', 'UG')");
        $this->deptId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO option_lists (list_key, label) VALUES ('academic_year', 'Academic Year')");
        $listId = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO option_values (list_id, value, display) VALUES ({$listId}, '2024-25', '2024-25')");
        $this->ayId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO users (name, email, password_hash, role, department_id) VALUES ('Staff', 's@s.com', 'x', 'staff', {$this->deptId})");
        $this->userId = (int)$this->pdo->lastInsertId();
    }

    private function seedPendingStudent(int $n): int
    {
        $mobile = '96' . str_pad((string)$n, 8, '0', STR_PAD_LEFT);
        $this->pdo->prepare(
            "INSERT INTO students
             (first_name, last_name, mobile, dob, gender, department_id, academic_year_id,
              programme_level, onboarding_status, login_enabled, created_by, class_id, admission_date, created_at)
             VALUES ('S', ?, ?, '2000-01-01', 'male', ?, ?, 'UG', 'pending_enrolment', 1, 1, 1, '2024-06-01', ?)"
        )->execute(["S{$n}", $mobile, $this->deptId, $this->ayId, date('Y-m-d H:i:s')]);
        return (int)$this->pdo->lastInsertId();
    }

    public function testHasPendingBatchReturnsTrueAfterFirstGeneration(): void
    {
        $this->seedPendingStudent(1);

        // Generate batch but do NOT approve
        EnrolmentNumberGenerator::generate($this->deptId, $this->ayId, $this->userId);

        $this->assertTrue(Student::hasPendingBatch($this->deptId, $this->ayId));
    }

    public function testSecondGenerationThrowsWhenFirstBatchPending(): void
    {
        $this->seedPendingStudent(1);
        EnrolmentNumberGenerator::generate($this->deptId, $this->ayId, $this->userId);

        // Seed another student for the second generation attempt
        $this->seedPendingStudent(2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already pending/i');

        EnrolmentNumberGenerator::generate($this->deptId, $this->ayId, $this->userId);
    }

    public function testGenerationAllowedAfterPendingBatchApproved(): void
    {
        $id1 = $this->seedPendingStudent(1);
        $batch1Id = EnrolmentNumberGenerator::generate($this->deptId, $this->ayId, $this->userId);

        // Approve the batch
        Student::approveNumbers([$id1], $batch1Id, $this->userId);

        // Should no longer be blocked
        $this->assertFalse(Student::hasPendingBatch($this->deptId, $this->ayId));

        // Seed another student and generate
        $id2 = $this->seedPendingStudent(2);
        $batch2Id = EnrolmentNumberGenerator::generate($this->deptId, $this->ayId, $this->userId);
        $this->assertGreaterThan($batch1Id, $batch2Id);
    }
}
