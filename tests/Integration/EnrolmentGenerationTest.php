<?php
namespace Tests\Integration;

use App\Helpers\EnrolmentNumberGenerator;
use App\Models\EnrolmentBatch;
use Tests\TestCase;

/**
 * Full generation flow: seed dept + ay + 3 students → generate → assert numbers/serials/batch.
 */
class EnrolmentGenerationTest extends TestCase
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
        $mobile = '98000000' . str_pad((string)$n, 2, '0', STR_PAD_LEFT);
        $this->pdo->prepare(
            "INSERT INTO students
             (first_name, last_name, mobile, dob, gender, department_id, academic_year_id,
              programme_level, onboarding_status, login_enabled, created_by, class_id, admission_date, created_at)
             VALUES ('Student', ?, ?, '2000-01-01', 'male', ?, ?, 'UG', 'pending_enrolment', 1, 1, 1, '2024-06-01', ?)"
        )->execute(["S{$n}", $mobile, $this->deptId, $this->ayId, date('Y-m-d H:i:s')]);
        return (int)$this->pdo->lastInsertId();
    }

    public function testGenerateAssignsCorrectNumbersAndSerials(): void
    {
        $id1 = $this->seedPendingStudent(1);
        $id2 = $this->seedPendingStudent(2);
        $id3 = $this->seedPendingStudent(3);

        $batchId = EnrolmentNumberGenerator::generate($this->deptId, $this->ayId, $this->userId);

        $this->assertGreaterThan(0, $batchId);

        $batch = $this->pdo->query("SELECT * FROM enrolment_batches WHERE id = {$batchId}")->fetch();
        $this->assertNotFalse($batch);
        $this->assertSame(3, (int)$batch['student_count']);
        $this->assertSame($this->deptId, (int)$batch['department_id']);
        $this->assertSame($this->ayId, (int)$batch['academic_year_id']);

        $students = $this->pdo->query(
            "SELECT * FROM students WHERE enrolment_batch_id = {$batchId} ORDER BY enrolment_serial"
        )->fetchAll();
        $this->assertCount(3, $students);

        $this->assertSame(1, (int)$students[0]['enrolment_serial']);
        $this->assertSame('24UBCA001', $students[0]['enrolment_number']);
        $this->assertSame('pending', $students[0]['enrolment_approval_status']);

        $this->assertSame(2, (int)$students[1]['enrolment_serial']);
        $this->assertSame('24UBCA002', $students[1]['enrolment_number']);
        $this->assertSame('pending', $students[1]['enrolment_approval_status']);

        $this->assertSame(3, (int)$students[2]['enrolment_serial']);
        $this->assertSame('24UBCA003', $students[2]['enrolment_number']);
        $this->assertSame('pending', $students[2]['enrolment_approval_status']);
    }

    public function testGenerateOnlyTargetsEligibleStudents(): void
    {
        $eligible = $this->seedPendingStudent(1);

        // A student already assigned (should NOT be included)
        $mobile2 = '9700000001';
        $this->pdo->prepare(
            "INSERT INTO students
             (first_name, last_name, mobile, dob, gender, department_id, academic_year_id,
              programme_level, onboarding_status, login_enabled, created_by, class_id, admission_date,
              enrolment_approval_status, created_at)
             VALUES ('X','X',?,'2000-01-01','male',?,?,'UG','enrolment_assigned',1,1,1,'2024-06-01','approved',?)"
        )->execute([$mobile2, $this->deptId, $this->ayId, date('Y-m-d H:i:s')]);

        $batchId = EnrolmentNumberGenerator::generate($this->deptId, $this->ayId, $this->userId);

        $batch = $this->pdo->query("SELECT student_count FROM enrolment_batches WHERE id = {$batchId}")->fetch();
        $this->assertSame(1, (int)$batch['student_count']);
    }

    public function testGenerateThrowsWhenNoEligibleStudents(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No students/i');
        EnrolmentNumberGenerator::generate($this->deptId, $this->ayId, $this->userId);
    }
}
