<?php
namespace Tests\Unit;

use App\Helpers\Db;
use App\Models\ChangeRequest;
use Tests\TestCase;

class HasPendingRtcTest extends TestCase
{
    private function seedDept(): array
    {
        $now = date('Y-m-d H:i:s');
        Db::execute("INSERT INTO departments (name,code,level,status,created_at) VALUES ('Dept','TST','UG','active',?)", [$now]);
        $deptId  = (int)Db::conn()->lastInsertId();
        Db::execute("INSERT INTO users (name,email,password_hash,role,department_id,status,created_at) VALUES ('Staff','s@t.com','h','staff',?,?,?)", [$deptId,'active',$now]);
        $staffId = (int)Db::conn()->lastInsertId();
        return [$deptId, $staffId];
    }

    private function seedRtcStudent(int $deptId): int
    {
        Db::execute("INSERT INTO students (mobile,dob,department_id,first_name,last_name,onboarding_status,login_enabled,created_at) VALUES ('9000000001','2000-01-01',?,'Test','Student','form_submitted',1,?)", [$deptId, date('Y-m-d H:i:s')]);
        return (int)Db::conn()->lastInsertId();
    }

    public function testFalseWhenNoRtc(): void
    {
        $this->assertFalse(ChangeRequest::hasPending(9999));
    }

    public function testTrueWhenPendingExists(): void
    {
        [$deptId, $staffId] = $this->seedDept();
        $studentId = $this->seedRtcStudent($deptId);

        ChangeRequest::create([
            'student_id'       => $studentId,
            'department_id'    => $deptId,
            'initiated_by'     => $staffId,
            'initiator_type'   => 'staff',
            'reason'           => 'Test',
            'proposed_changes' => [],
        ]);

        $this->assertTrue(ChangeRequest::hasPending($studentId));
    }

    public function testFalseAfterApproved(): void
    {
        [$deptId, $staffId] = $this->seedDept();
        $studentId = $this->seedRtcStudent($deptId);

        $rtcId = ChangeRequest::create([
            'student_id'       => $studentId,
            'department_id'    => $deptId,
            'initiated_by'     => $staffId,
            'initiator_type'   => 'staff',
            'reason'           => 'Test',
            'proposed_changes' => [],
        ]);

        Db::execute("UPDATE change_requests SET status='approved' WHERE id=?", [$rtcId]);

        $this->assertFalse(ChangeRequest::hasPending($studentId));
    }
}
