<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Helpers\NotificationProcessor;
use Tests\TestCase;

/**
 * When process() is called with a deptId, only that dept's events are processed.
 */
class ProcessorDeptScopeTest extends TestCase
{
    private int $deptA;
    private int $deptB;
    private int $staffA;
    private int $staffB;
    private int $studentA;
    private int $studentB;

    protected function setUp(): void
    {
        parent::setUp();
        $now = date('Y-m-d H:i:s');

        Db::execute("INSERT INTO departments (name,code,level,status,created_at) VALUES ('DeptA','AA','UG','active',?)", [$now]);
        $this->deptA = (int)Db::conn()->lastInsertId();
        Db::execute("INSERT INTO departments (name,code,level,status,created_at) VALUES ('DeptB','BB','UG','active',?)", [$now]);
        $this->deptB = (int)Db::conn()->lastInsertId();

        Db::execute("INSERT INTO users (name,email,password_hash,role,department_id,status,created_at) VALUES ('SA','sa@t.com','h','staff',?,?,?)", [$this->deptA,'active',$now]);
        $this->staffA = (int)Db::conn()->lastInsertId();
        Db::execute("INSERT INTO users (name,email,password_hash,role,department_id,status,created_at) VALUES ('SB','sb@t.com','h','staff',?,?,?)", [$this->deptB,'active',$now]);
        $this->staffB = (int)Db::conn()->lastInsertId();

        Db::execute("INSERT INTO students (mobile,dob,department_id,first_name,last_name,onboarding_status,login_enabled,created_at) VALUES ('9010','2001-01-01',?,'StudentA','Test','form_submitted',1,?)", [$this->deptA,$now]);
        $this->studentA = (int)Db::conn()->lastInsertId();
        Db::execute("INSERT INTO student_profiles (student_id,student_email,form_status,created_at) VALUES (?,'a@test.com','submitted',?)", [$this->studentA,$now]);

        Db::execute("INSERT INTO students (mobile,dob,department_id,first_name,last_name,onboarding_status,login_enabled,created_at) VALUES ('9011','2001-01-01',?,'StudentB','Test','form_submitted',1,?)", [$this->deptB,$now]);
        $this->studentB = (int)Db::conn()->lastInsertId();
        Db::execute("INSERT INTO student_profiles (student_id,student_email,form_status,created_at) VALUES (?,'b@test.com','submitted',?)", [$this->studentB,$now]);

        // One event per dept
        Db::execute(
            "INSERT INTO notification_events (event_key,student_id,actor_id,recipient_type,recipient_id,payload,sent_at,created_at)
             VALUES ('submission_approved',?,?,'student',?,'{}',NULL,?)",
            [$this->studentA,$this->staffA,$this->studentA,$now]
        );
        Db::execute(
            "INSERT INTO notification_events (event_key,student_id,actor_id,recipient_type,recipient_id,payload,sent_at,created_at)
             VALUES ('submission_approved',?,?,'student',?,'{}',NULL,?)",
            [$this->studentB,$this->staffB,$this->studentB,$now]
        );

        NotificationProcessor::setMailerFactory(fn() => new class {
            public string $Subject = '';
            public string $Body    = '';
            public function addAddress(string $a): void {}
            public function send(): bool { return true; }
            public function isHTML(bool $v): void {}
        });
    }

    protected function tearDown(): void
    {
        NotificationProcessor::setMailerFactory(null);
        parent::tearDown();
    }

    public function testOnlyDeptAEventProcessed(): void
    {
        $result = NotificationProcessor::process($this->deptA);

        $this->assertSame(1, $result['sent']);

        // DeptA event should now be sent
        $eventA = Db::selectOne("SELECT sent_at FROM notification_events WHERE student_id=?", [$this->studentA]);
        $this->assertNotNull($eventA['sent_at']);

        // DeptB event must remain pending
        $eventB = Db::selectOne("SELECT sent_at FROM notification_events WHERE student_id=?", [$this->studentB]);
        $this->assertNull($eventB['sent_at']);
    }
}
