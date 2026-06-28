<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Helpers\NotificationProcessor;
use Tests\TestCase;

/**
 * Already-sent events must not be reprocessed.
 */
class ProcessorIdempotentTest extends TestCase
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
        Db::execute("INSERT INTO students (mobile,dob,department_id,first_name,last_name,onboarding_status,login_enabled,created_at) VALUES ('9004','2001-01-01',?,'Idem','Potent','form_submitted',1,?)", [$this->deptId,$now]);
        $this->studentId = (int)Db::conn()->lastInsertId();
        Db::execute("INSERT INTO student_profiles (student_id,student_email,form_status,created_at) VALUES (?,'already@test.com','submitted',?)", [$this->studentId,$now]);

        // Already-sent event (sent_at not null)
        Db::execute(
            "INSERT INTO notification_events (event_key,student_id,actor_id,recipient_type,recipient_id,payload,sent_at,created_at)
             VALUES ('submission_approved',?,?,'student',?,'{}',?,?)",
            [$this->studentId,$this->staffId,$this->studentId,$now,$now]
        );

        $callCount = 0;
        NotificationProcessor::setMailerFactory(function() use (&$callCount) {
            $callCount++;
            return new class {
                public string $Subject = '';
                public string $Body    = '';
                public function addAddress(string $a): void {}
                public function send(): bool { return true; }
                public function isHTML(bool $v): void {}
            };
        });
        // stash callCount reference for test assertions
        $this->callCount = &$callCount;
    }

    private int $callCount = 0;

    protected function tearDown(): void
    {
        NotificationProcessor::setMailerFactory(null);
        parent::tearDown();
    }

    public function testAlreadySentEventSkipped(): void
    {
        $result = NotificationProcessor::process();

        // Nothing to send — already sent
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['failed']);
    }

    public function testNoEmailSentForAlreadySentEvent(): void
    {
        NotificationProcessor::process();
        // Mailer factory should never have been called
        $this->assertSame(0, $this->callCount);
    }
}
