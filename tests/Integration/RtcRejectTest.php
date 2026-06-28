<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Models\ChangeRequest;
use App\Models\StudentProfile;
use Tests\TestCase;

class RtcRejectTest extends TestCase
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
        Db::execute("INSERT INTO students (mobile,dob,department_id,first_name,last_name,onboarding_status,login_enabled,created_at) VALUES ('9004','2001-01-01',?,'Kavya','R','form_submitted',1,?)", [$this->deptId,$now]);
        $this->studentId = (int)Db::conn()->lastInsertId();
        Db::execute("INSERT INTO student_profiles (student_id,mother_name,form_status,created_at) VALUES (?,'Original','submitted',?)", [$this->studentId,$now]);
    }

    public function testRejectStoresReasonAndLeavesProfileUnchanged(): void
    {
        $rtcId = ChangeRequest::create([
            'student_id'       => $this->studentId,
            'department_id'    => $this->deptId,
            'initiated_by'     => $this->staffId,
            'initiator_type'   => 'staff',
            'reason'           => 'Fix name',
            'proposed_changes' => [
                ['field_key'=>'mother_name','label'=>'Name','current_value'=>'Original','proposed_value'=>'Changed','is_file'=>false],
            ],
        ]);

        ChangeRequest::reject($rtcId, $this->staffId, 'Documentation required first.');

        $rtc = ChangeRequest::findById($rtcId);
        $this->assertEquals('rejected', $rtc['status']);
        $this->assertEquals('Documentation required first.', $rtc['rejection_reason']);

        $profile = StudentProfile::findByStudent($this->studentId);
        $this->assertEquals('Original', $profile['mother_name']);
    }

    public function testRejectCreatesAuditAndEvents(): void
    {
        $rtcId = ChangeRequest::create([
            'student_id'       => $this->studentId,
            'department_id'    => $this->deptId,
            'initiated_by'     => $this->staffId,
            'initiator_type'   => 'staff',
            'reason'           => 'Test',
            'proposed_changes' => [],
        ]);

        ChangeRequest::reject($rtcId, $this->staffId, 'Not valid.');

        $log = Db::selectOne("SELECT * FROM audit_log WHERE action='rtc_rejected' AND entity_id=?", [$rtcId]);
        $this->assertNotNull($log);

        $events = Db::selectAll("SELECT * FROM notification_events WHERE change_request_id=?", [$rtcId]);
        $this->assertCount(2, $events);
    }
}
