<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Models\ChangeRequest;
use App\Models\StudentProfile;
use App\Models\Student;
use Tests\TestCase;

class RtcApproveTest extends TestCase
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
        Db::execute("INSERT INTO students (mobile,dob,department_id,first_name,last_name,onboarding_status,login_enabled,created_at) VALUES ('9003','2001-01-01',?,'Ravi','M','form_submitted',1,?)", [$this->deptId,$now]);
        $this->studentId = (int)Db::conn()->lastInsertId();
        Db::execute("INSERT INTO student_profiles (student_id,mother_name,form_status,created_at) VALUES (?,'OldMother','submitted',?)", [$this->studentId,$now]);
    }

    public function testApproveWritesChangesToProfile(): void
    {
        $rtcId = ChangeRequest::create([
            'student_id'       => $this->studentId,
            'department_id'    => $this->deptId,
            'initiated_by'     => $this->staffId,
            'initiator_type'   => 'staff',
            'reason'           => 'Correction',
            'proposed_changes' => [
                ['field_key'=>'mother_name','label'=>"Mother's Name",'current_value'=>'OldMother','proposed_value'=>'NewMother','is_file'=>false],
            ],
        ]);

        ChangeRequest::approve($rtcId, $this->staffId);

        $profile = StudentProfile::findByStudent($this->studentId);
        $this->assertEquals('NewMother', $profile['mother_name']);

        $rtc = ChangeRequest::findById($rtcId);
        $this->assertEquals('approved', $rtc['status']);
    }

    public function testApproveCreatesAuditLog(): void
    {
        $rtcId = ChangeRequest::create([
            'student_id'       => $this->studentId,
            'department_id'    => $this->deptId,
            'initiated_by'     => $this->staffId,
            'initiator_type'   => 'staff',
            'reason'           => 'Test',
            'proposed_changes' => [
                ['field_key'=>'father_name','label'=>"Father's Name",'current_value'=>'Old','proposed_value'=>'New','is_file'=>false],
            ],
        ]);
        ChangeRequest::approve($rtcId, $this->staffId);

        $log = Db::selectOne("SELECT * FROM audit_log WHERE action='rtc_approved' AND entity_id=?", [$rtcId]);
        $this->assertNotNull($log);
        $details = json_decode($log['details'], true);
        $this->assertContains('father_name', $details['changed_fields']);
    }

    public function testApproveCreatesNotificationEvents(): void
    {
        $rtcId = ChangeRequest::create([
            'student_id'       => $this->studentId,
            'department_id'    => $this->deptId,
            'initiated_by'     => $this->staffId,
            'initiator_type'   => 'staff',
            'reason'           => 'Test',
            'proposed_changes' => [
                ['field_key'=>'mother_name','label'=>'Mother Name','current_value'=>'A','proposed_value'=>'B','is_file'=>false],
            ],
        ]);
        ChangeRequest::approve($rtcId, $this->staffId);

        $events = Db::selectAll("SELECT * FROM notification_events WHERE change_request_id=?", [$rtcId]);
        $this->assertCount(2, $events);
        $types = array_column($events, 'recipient_type');
        $this->assertContains('student',    $types);
        $this->assertContains('dept_admin', $types);
    }

    public function testFormStatusUnchangedAfterApproval(): void
    {
        $rtcId = ChangeRequest::create([
            'student_id'       => $this->studentId,
            'department_id'    => $this->deptId,
            'initiated_by'     => $this->staffId,
            'initiator_type'   => 'staff',
            'reason'           => 'Test',
            'proposed_changes' => [
                ['field_key'=>'mother_name','label'=>'Name','current_value'=>'A','proposed_value'=>'B','is_file'=>false],
            ],
        ]);
        ChangeRequest::approve($rtcId, $this->staffId);

        $profile = StudentProfile::findByStudent($this->studentId);
        $this->assertEquals('submitted', $profile['form_status']);
        $student = Student::find($this->studentId);
        $this->assertEquals('form_submitted', $student['onboarding_status']);
    }
}
