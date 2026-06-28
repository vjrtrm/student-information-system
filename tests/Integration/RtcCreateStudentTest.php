<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Models\ChangeRequest;
use Tests\TestCase;

class RtcCreateStudentTest extends TestCase
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
        Db::execute("INSERT INTO students (mobile,dob,department_id,first_name,last_name,onboarding_status,login_enabled,created_at) VALUES ('9002','2001-01-01',?,'Priya','K','form_submitted',1,?)", [$this->deptId,$now]);
        $this->studentId = (int)Db::conn()->lastInsertId();
    }

    public function testCreateRtcAsStudent(): void
    {
        $rtcId = ChangeRequest::create([
            'student_id'       => $this->studentId,
            'department_id'    => $this->deptId,
            'initiated_by'     => $this->studentId,
            'initiator_type'   => 'student',
            'reason'           => 'Correction needed',
            'proposed_changes' => [
                ['field_key'=>'mother_name','label'=>"Mother's Name",'current_value'=>'Old','proposed_value'=>'New','is_file'=>false],
            ],
        ]);

        $this->assertGreaterThan(0, $rtcId);
        $rtc = ChangeRequest::findById($rtcId);
        $this->assertEquals('student', $rtc['initiator_type']);
        $this->assertEquals('pending', $rtc['status']);
        $this->assertIsArray($rtc['proposed_changes']);
    }

    public function testHasPendingBlocksSecondRtc(): void
    {
        ChangeRequest::create([
            'student_id'       => $this->studentId,
            'department_id'    => $this->deptId,
            'initiated_by'     => $this->studentId,
            'initiator_type'   => 'student',
            'reason'           => 'First request',
            'proposed_changes' => [],
        ]);

        $this->assertTrue(ChangeRequest::hasPending($this->studentId));
    }
}
