<?php
namespace Tests\Integration;

use App\Helpers\EnrolmentNumberGenerator;
use App\Models\Student;
use Tests\TestCase;

/**
 * Verifies that a second generation batch continues serial numbering from where
 * the first batch left off (serials 001-003 → 004-005).
 */
class EnrolmentSerialContinuityTest extends TestCase
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
        $mobile = '98' . str_pad((string)$n, 8, '0', STR_PAD_LEFT);
        $this->pdo->prepare(
            "INSERT INTO students
             (first_name, last_name, mobile, dob, gender, department_id, academic_year_id,
              programme_level, onboarding_status, login_enabled, created_by, class_id, admission_date, created_at)
             VALUES ('Student', ?, ?, '2000-01-01', 'male', ?, ?, 'UG', 'pending_enrolment', 1, 1, 1, '2024-06-01', ?)"
        )->execute(["S{$n}", $mobile, $this->deptId, $this->ayId, date('Y-m-d H:i:s')]);
        return (int)$this->pdo->lastInsertId();
    }

    public function testSecondBatchContinuesSerialFromFirstBatch(): void
    {
        // Seed 3 students → generate batch 1 → approve all → seed 2 more → generate batch 2
        $id1 = $this->seedPendingStudent(1);
        $id2 = $this->seedPendingStudent(2);
        $id3 = $this->seedPendingStudent(3);

        $batch1Id = EnrolmentNumberGenerator::generate($this->deptId, $this->ayId, $this->userId);

        // Approve all 3 (so hasPendingBatch returns false for batch 2 generation)
        Student::approveNumbers([$id1, $id2, $id3], $batch1Id, $this->userId);

        // Reset their onboarding_status to allow the second generation not to re-pick them
        // They have enrolment_approval_status = 'approved' so findPendingForGeneration won't pick them

        // Seed 2 more students
        $id4 = $this->seedPendingStudent(4);
        $id5 = $this->seedPendingStudent(5);

        $batch2Id = EnrolmentNumberGenerator::generate($this->deptId, $this->ayId, $this->userId);

        $batch2Students = $this->pdo->query(
            "SELECT enrolment_serial, enrolment_number FROM students
             WHERE enrolment_batch_id = {$batch2Id}
             ORDER BY enrolment_serial"
        )->fetchAll();

        $this->assertCount(2, $batch2Students);
        $this->assertSame(4, (int)$batch2Students[0]['enrolment_serial']);
        $this->assertSame('24UBCA004', $batch2Students[0]['enrolment_number']);
        $this->assertSame(5, (int)$batch2Students[1]['enrolment_serial']);
        $this->assertSame('24UBCA005', $batch2Students[1]['enrolment_number']);
    }
}
