<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Models\NotificationEvent;
use App\Models\Student;
use Tests\TestCase;

class SubmissionApprovalTest extends TestCase
{
    private int $deptId;
    private int $staffId;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $now = date('Y-m-d H:i:s');
        Db::execute("INSERT INTO departments (name,code,level,status,created_at) VALUES ('D','T','UG','active',?)", [$now]);
        $this->deptId  = (int)Db::conn()->lastInsertId();
        Db::execute("INSERT INTO users (name,email,password_hash,role,department_id,status,created_at) VALUES ('S','s@t.com','h','staff',?,?,?)", [$this->deptId,'active',$now]);
        $this->staffId = (int)Db::conn()->lastInsertId();
        Db::execute("INSERT INTO students (mobile,dob,department_id,first_name,last_name,onboarding_status,login_enabled,created_at) VALUES ('9001','2001-01-01',?,'Arun','K','form_submitted',1,?)", [$this->deptId,$now]);
        $this->studentId = (int)Db::conn()->lastInsertId();
    }

    public function testApproveUpdatesStatus(): void
    {
        $now = date('Y-m-d H:i:s');
        Db::execute("UPDATE students SET onboarding_status='approved',approval_by=?,approval_at=? WHERE id=?",
            [$this->staffId, $now, $this->studentId]);

        $student = Student::find($this->studentId);
        $this->assertEquals('approved', $student['onboarding_status']);
        $this->assertEquals($this->staffId, (int)$student['approval_by']);
    }

    public function testNotificationEventsCreated(): void
    {
        $payload = ['student_id' => $this->studentId, 'dept_id' => $this->deptId, 'enrolment_serial' => null];
        NotificationEvent::record('submission_approved', $this->studentId, $this->staffId, 'student',    $this->studentId, null, $payload);
        NotificationEvent::record('submission_approved', $this->studentId, $this->staffId, 'dept_admin', null,             null, $payload);

        $events = Db::selectAll("SELECT * FROM notification_events WHERE student_id=?", [$this->studentId]);
        $this->assertCount(2, $events);
        $types = array_column($events, 'recipient_type');
        $this->assertContains('student',    $types);
        $this->assertContains('dept_admin', $types);
        foreach ($events as $e) {
            $this->assertNull($e['sent_at']);
        }
    }
}
