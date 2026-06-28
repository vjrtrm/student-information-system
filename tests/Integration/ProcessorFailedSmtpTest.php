<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Helpers\NotificationProcessor;
use Tests\TestCase;

/**
 * PHPMailer throws → event stays pending, error log written.
 */
class ProcessorFailedSmtpTest extends TestCase
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
        Db::execute("INSERT INTO students (mobile,dob,department_id,first_name,last_name,onboarding_status,login_enabled,created_at) VALUES ('9003','2001-01-01',?,'Smtp','Fail','form_submitted',1,?)", [$this->deptId,$now]);
        $this->studentId = (int)Db::conn()->lastInsertId();

        Db::execute("INSERT INTO student_profiles (student_id,student_email,form_status,created_at) VALUES (?,'valid@test.com','submitted',?)", [$this->studentId,$now]);

        Db::execute(
            "INSERT INTO notification_events (event_key,student_id,actor_id,recipient_type,recipient_id,payload,sent_at,created_at)
             VALUES ('submission_approved',?,?,'student',?,?,NULL,?)",
            [$this->studentId,$this->staffId,$this->studentId,'{}', $now]
        );

        // Stub PHPMailer to throw
        NotificationProcessor::setMailerFactory(fn() => new class {
            public string $Subject = '';
            public string $Body    = '';
            public function addAddress(string $a): void {}
            public function send(): bool { throw new \RuntimeException('SMTP connection failed.'); }
            public function isHTML(bool $v): void {}
        });
    }

    protected function tearDown(): void
    {
        NotificationProcessor::setMailerFactory(null);
        parent::tearDown();
    }

    public function testFailedSmtpLeavesEventPending(): void
    {
        $result = NotificationProcessor::process();

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, $result['failed']);

        $event = Db::selectOne("SELECT sent_at FROM notification_events WHERE student_id=?", [$this->studentId]);
        $this->assertNull($event['sent_at']);
    }

    public function testErrorLogWrittenOnFailure(): void
    {
        NotificationProcessor::process();
        $errors = Db::selectAll("SELECT * FROM notification_error_log", []);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('SMTP', $errors[0]['error_message']);
    }
}
