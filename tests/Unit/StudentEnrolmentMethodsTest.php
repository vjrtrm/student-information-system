<?php
namespace Tests\Unit;

use App\Models\Student;
use Tests\TestCase;

/**
 * Tests the M4 enrolment helper methods on Student model.
 */
class StudentEnrolmentMethodsTest extends TestCase
{
    private int $deptId;
    private int $ayId;
    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo->exec("INSERT INTO departments (name, code, level) VALUES ('Test Dept', 'BCA', 'UG')");
        $this->deptId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO option_lists (list_key, label) VALUES ('academic_year', 'Academic Year')");
        $listId = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO option_values (list_id, value, display) VALUES ({$listId}, '2024-25', '2024-25')");
        $this->ayId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO users (name, email, password_hash, role) VALUES ('Admin', 'a@a.com', 'x', 'dept_admin')");
        $userId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare(
            "INSERT INTO enrolment_batches (department_id, academic_year_id, generated_by, student_count, created_at)
             VALUES (?, ?, ?, 0, ?)"
        )->execute([$this->deptId, $this->ayId, $userId, date('Y-m-d H:i:s')]);
        $this->batchId = (int)$this->pdo->lastInsertId();
    }

    private function insertStudentWithEnrolment(
        string $approvalStatus,
        ?int $serial,
        ?string $number,
        ?int $batchId = null
    ): int {
        $mobile = '9' . str_pad((string)rand(0, 999999999), 9, '0', STR_PAD_LEFT);
        $this->pdo->prepare(
            "INSERT INTO students
             (mobile, dob, department_id, academic_year_id,
              enrolment_number, enrolment_serial, enrolment_approval_status, enrolment_batch_id,
              onboarding_status)
             VALUES (?, '2000-01-01', ?, ?, ?, ?, ?, ?, 'pending_enrolment')"
        )->execute([
            $mobile, $this->deptId, $this->ayId,
            $number, $serial, $approvalStatus, $batchId ?? $this->batchId,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── hasPendingBatch ───────────────────────────────────────────────────

    public function testHasPendingBatchReturnsTrueWhenPendingExists(): void
    {
        $this->insertStudentWithEnrolment('pending', 1, '24UBCA001');
        $this->assertTrue(Student::hasPendingBatch($this->deptId, $this->ayId));
    }

    public function testHasPendingBatchReturnsFalseWhenNone(): void
    {
        $this->assertFalse(Student::hasPendingBatch($this->deptId, $this->ayId));
    }

    public function testHasPendingBatchReturnsFalseWhenAllApproved(): void
    {
        $this->insertStudentWithEnrolment('approved', 1, '24UBCA001');
        $this->insertStudentWithEnrolment('approved', 2, '24UBCA002');
        $this->assertFalse(Student::hasPendingBatch($this->deptId, $this->ayId));
    }

    // ── maxSerial ─────────────────────────────────────────────────────────

    public function testMaxSerialReturns0WhenNone(): void
    {
        $this->assertSame(0, Student::maxSerial($this->deptId, $this->ayId));
    }

    public function testMaxSerialReturnsCorrectMax(): void
    {
        $this->insertStudentWithEnrolment('pending', 3, '24UBCA003');
        $this->insertStudentWithEnrolment('pending', 7, '24UBCA007');
        $this->insertStudentWithEnrolment('approved', 5, '24UBCA005');
        $this->assertSame(7, Student::maxSerial($this->deptId, $this->ayId));
    }

    public function testMaxSerialIgnoresNullSerials(): void
    {
        $mobile = '9800000001';
        $this->pdo->prepare(
            "INSERT INTO students (mobile, dob, department_id, academic_year_id, onboarding_status)
             VALUES (?, '2000-01-01', ?, ?, 'pending_enrolment')"
        )->execute([$mobile, $this->deptId, $this->ayId]);
        $this->assertSame(0, Student::maxSerial($this->deptId, $this->ayId));
    }

    // ── approveNumbers ────────────────────────────────────────────────────

    public function testApproveNumbersUpdatesOnlyPendingRows(): void
    {
        $id1 = $this->insertStudentWithEnrolment('pending', 1, '24UBCA001');
        $id2 = $this->insertStudentWithEnrolment('pending', 2, '24UBCA002');
        $id3 = $this->insertStudentWithEnrolment('approved', 3, '24UBCA003'); // already approved

        $count = Student::approveNumbers([$id1, $id2, $id3], $this->batchId, 1);

        // id3 is already approved so should not count
        $this->assertSame(2, $count);

        $row1 = $this->pdo->query("SELECT enrolment_approval_status, onboarding_status FROM students WHERE id = {$id1}")->fetch();
        $row2 = $this->pdo->query("SELECT enrolment_approval_status, onboarding_status FROM students WHERE id = {$id2}")->fetch();

        $this->assertSame('approved', $row1['enrolment_approval_status']);
        $this->assertSame('enrolment_assigned', $row1['onboarding_status']);
        $this->assertSame('approved', $row2['enrolment_approval_status']);
        $this->assertSame('enrolment_assigned', $row2['onboarding_status']);
    }

    public function testApproveNumbersReturnsZeroForEmptyIds(): void
    {
        $this->assertSame(0, Student::approveNumbers([], $this->batchId, 1));
    }

    public function testApproveNumbersDoesNotApproveWrongBatch(): void
    {
        // Insert student with a different batchId
        $this->pdo->prepare(
            "INSERT INTO enrolment_batches (department_id, academic_year_id, generated_by, student_count, created_at)
             VALUES (?, ?, 1, 0, ?)"
        )->execute([$this->deptId, $this->ayId, date('Y-m-d H:i:s')]);
        $otherBatch = (int)$this->pdo->lastInsertId();

        $id = $this->insertStudentWithEnrolment('pending', 1, '24UBCA001', $otherBatch);

        $count = Student::approveNumbers([$id], $this->batchId, 1); // wrong batchId
        $this->assertSame(0, $count);

        $row = $this->pdo->query("SELECT enrolment_approval_status FROM students WHERE id = {$id}")->fetch();
        $this->assertSame('pending', $row['enrolment_approval_status']);
    }

    // ── getEnrolmentStatus ────────────────────────────────────────────────

    public function testGetEnrolmentStatusReturnsNullNumberWhenPending(): void
    {
        $id = $this->insertStudentWithEnrolment('pending', 1, '24UBCA001');
        $result = Student::getEnrolmentStatus($id);
        $this->assertNull($result['number']);
        $this->assertSame('pending', $result['status']);
    }

    public function testGetEnrolmentStatusReturnsRealNumberWhenApproved(): void
    {
        $id = $this->insertStudentWithEnrolment('approved', 1, '24UBCA001');
        $result = Student::getEnrolmentStatus($id);
        $this->assertSame('24UBCA001', $result['number']);
        $this->assertSame('approved', $result['status']);
    }

    public function testGetEnrolmentStatusReturnsNullWhenNoEnrolment(): void
    {
        $mobile = '9700000001';
        $this->pdo->prepare(
            "INSERT INTO students (mobile, dob, department_id, onboarding_status) VALUES (?, '2000-01-01', ?, 'pending_enrolment')"
        )->execute([$mobile, $this->deptId]);
        $id = (int)$this->pdo->lastInsertId();

        $result = Student::getEnrolmentStatus($id);
        $this->assertNull($result['number']);
        $this->assertNull($result['status']);
    }
}
