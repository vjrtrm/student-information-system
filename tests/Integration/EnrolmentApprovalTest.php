<?php
namespace Tests\Integration;

use App\Helpers\EnrolmentNumberGenerator;
use App\Models\EnrolmentBatch;
use App\Models\Student;
use Tests\TestCase;

/**
 * Tests the partial and full approval flow and deriveStatus transitions.
 */
class EnrolmentApprovalTest extends TestCase
{
    private int $deptId;
    private int $ayId;
    private int $userId;
    private int $batchId;
    private array $studentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo->exec("INSERT INTO departments (name, code, level) VALUES ('BCA Dept', 'BCA', 'UG')");
        $this->deptId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO option_lists (list_key, label) VALUES ('academic_year', 'Academic Year')");
        $listId = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO option_values (list_id, value, display) VALUES ({$listId}, '2024-25', '2024-25')");
        $this->ayId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO users (name, email, password_hash, role, department_id) VALUES ('Admin', 'a@a.com', 'x', 'dept_admin', {$this->deptId})");
        $this->userId = (int)$this->pdo->lastInsertId();

        // Seed 4 students
        for ($n = 1; $n <= 4; $n++) {
            $mobile = '97' . str_pad((string)$n, 8, '0', STR_PAD_LEFT);
            $this->pdo->prepare(
                "INSERT INTO students
                 (first_name, last_name, mobile, dob, gender, department_id, academic_year_id,
                  programme_level, onboarding_status, login_enabled, created_by, class_id, admission_date, created_at)
                 VALUES ('S', ?, ?, '2000-01-01', 'male', ?, ?, 'UG', 'pending_enrolment', 1, 1, 1, '2024-06-01', ?)"
            )->execute(["S{$n}", $mobile, $this->deptId, $this->ayId, date('Y-m-d H:i:s')]);
            $this->studentIds[] = (int)$this->pdo->lastInsertId();
        }

        $this->batchId = EnrolmentNumberGenerator::generate($this->deptId, $this->ayId, $this->userId);
    }

    public function testPartialApprovalGivesInProgressStatus(): void
    {
        [$id1, $id2, $id3, $id4] = $this->studentIds;

        // Approve only first two
        $count = Student::approveNumbers([$id1, $id2], $this->batchId, $this->userId);
        $this->assertSame(2, $count);

        // Verify approved rows
        $row1 = $this->pdo->query("SELECT * FROM students WHERE id = {$id1}")->fetch();
        $row2 = $this->pdo->query("SELECT * FROM students WHERE id = {$id2}")->fetch();
        $this->assertSame('approved', $row1['enrolment_approval_status']);
        $this->assertSame('enrolment_assigned', $row1['onboarding_status']);
        $this->assertSame('approved', $row2['enrolment_approval_status']);
        $this->assertSame('enrolment_assigned', $row2['onboarding_status']);

        // Verify pending rows not touched
        $row3 = $this->pdo->query("SELECT * FROM students WHERE id = {$id3}")->fetch();
        $row4 = $this->pdo->query("SELECT * FROM students WHERE id = {$id4}")->fetch();
        $this->assertSame('pending', $row3['enrolment_approval_status']);
        $this->assertSame('pending', $row4['enrolment_approval_status']);

        // Derived status should be in_progress
        $this->assertSame('in_progress', EnrolmentBatch::deriveStatus($this->batchId));
    }

    public function testFullApprovalGivesApprovedStatus(): void
    {
        // Approve all 4
        $count = Student::approveNumbers($this->studentIds, $this->batchId, $this->userId);
        $this->assertSame(4, $count);

        $this->assertSame('approved', EnrolmentBatch::deriveStatus($this->batchId));

        foreach ($this->studentIds as $id) {
            $row = $this->pdo->query("SELECT enrolment_approval_status FROM students WHERE id = {$id}")->fetch();
            $this->assertSame('approved', $row['enrolment_approval_status']);
        }
    }

    public function testApproveRemainingAfterPartialApproval(): void
    {
        [$id1, $id2, $id3, $id4] = $this->studentIds;

        // Approve first 2
        Student::approveNumbers([$id1, $id2], $this->batchId, $this->userId);
        $this->assertSame('in_progress', EnrolmentBatch::deriveStatus($this->batchId));

        // Approve remaining 2
        $count = Student::approveNumbers([$id3, $id4], $this->batchId, $this->userId);
        $this->assertSame(2, $count);

        $this->assertSame('approved', EnrolmentBatch::deriveStatus($this->batchId));
    }

    public function testApprovedByAndApprovedAtAreSet(): void
    {
        $beforeApproval = date('Y-m-d H:i:s');
        Student::approveNumbers([$this->studentIds[0]], $this->batchId, $this->userId);

        $row = $this->pdo->query("SELECT enrolment_approved_by, enrolment_approved_at FROM students WHERE id = {$this->studentIds[0]}")->fetch();
        $this->assertSame($this->userId, (int)$row['enrolment_approved_by']);
        $this->assertNotNull($row['enrolment_approved_at']);
        $this->assertGreaterThanOrEqual($beforeApproval, $row['enrolment_approved_at']);
    }
}
