<?php
namespace Tests\Integration;

use App\Models\StudentProfile;
use App\Helpers\Db;
use Tests\TestCase;

class SubmitLockTest extends TestCase
{
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->studentId = $this->seedFullStudent([
            'programme_level'   => 'UG',
            'onboarding_status' => 'pending_form',
        ]);

        // Create a student_profiles row so submit() can UPDATE it
        $this->pdo->prepare(
            "INSERT INTO student_profiles (student_id, form_status, form_completion_pct)
             VALUES (?, 'incomplete', 80)"
        )->execute([$this->studentId]);
    }

    public function test_submit_sets_form_status_submitted(): void
    {
        StudentProfile::submit($this->studentId);

        $row = $this->pdo->query(
            "SELECT form_status FROM student_profiles WHERE student_id = {$this->studentId}"
        )->fetch();

        $this->assertSame('submitted', $row['form_status']);
    }

    public function test_submit_sets_completion_100(): void
    {
        StudentProfile::submit($this->studentId);

        $row = $this->pdo->query(
            "SELECT form_completion_pct FROM student_profiles WHERE student_id = {$this->studentId}"
        )->fetch();

        $this->assertSame(100, (int)$row['form_completion_pct']);
    }

    public function test_submit_updates_student_onboarding_status(): void
    {
        StudentProfile::submit($this->studentId);

        $row = $this->pdo->query(
            "SELECT onboarding_status FROM students WHERE id = {$this->studentId}"
        )->fetch();

        $this->assertSame('form_submitted', $row['onboarding_status']);
    }

    public function test_submit_creates_audit_log_row(): void
    {
        StudentProfile::submit($this->studentId);

        $row = $this->pdo->query(
            "SELECT * FROM audit_log WHERE action = 'student_form_submitted' LIMIT 1"
        )->fetch();

        $this->assertNotFalse($row, 'Expected audit_log row for student_form_submitted');
        $this->assertSame('student_profile', $row['entity']);
    }
}
