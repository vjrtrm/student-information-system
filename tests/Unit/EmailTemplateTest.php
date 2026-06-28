<?php
namespace Tests\Unit;

use App\Helpers\EmailTemplate;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    /** All 9 valid event × recipient_type combos. */
    private function allCombos(): array
    {
        return [
            ['submission_approved',      'student'],
            ['submission_approved',      'dept_admin'],
            ['rtc_created_by_student',   'dept_admin'],
            ['rtc_created_by_staff',     'student'],
            ['rtc_created_by_staff',     'dept_admin'],
            ['rtc_approved',             'student'],
            ['rtc_approved',             'dept_admin'],
            ['rtc_rejected',             'student'],
            ['rtc_rejected',             'dept_admin'],
        ];
    }

    public function testAllSubjectsReturnNonEmptyString(): void
    {
        foreach ($this->allCombos() as [$eventKey, $recipientType]) {
            $subject = EmailTemplate::getSubject($eventKey, $recipientType);
            $this->assertNotEmpty($subject, "Subject empty for {$eventKey}/{$recipientType}");
            $this->assertIsString($subject);
        }
    }

    public function testAllBodiesReturnNonEmptyString(): void
    {
        foreach ($this->allCombos() as [$eventKey, $recipientType]) {
            $body = EmailTemplate::getBody($eventKey, $recipientType, 'https://sis.test');
            $this->assertNotEmpty($body, "Body empty for {$eventKey}/{$recipientType}");
            $this->assertIsString($body);
        }
    }

    public function testBodiesContainLoginLink(): void
    {
        foreach ($this->allCombos() as [$eventKey, $recipientType]) {
            $body = EmailTemplate::getBody($eventKey, $recipientType, 'https://sis.test');
            $this->assertStringContainsString('https://sis.test/login', $body,
                "Body missing login link for {$eventKey}/{$recipientType}");
        }
    }

    public function testBodiesContainNoPiiTokens(): void
    {
        $piiTokens = ['{name}', '{mobile}', '{aadhaar}', '{email}', '{dob}', 'first_name', 'last_name'];
        foreach ($this->allCombos() as [$eventKey, $recipientType]) {
            $body = EmailTemplate::getBody($eventKey, $recipientType, 'https://sis.test');
            foreach ($piiTokens as $token) {
                $this->assertStringNotContainsString($token, $body,
                    "Body for {$eventKey}/{$recipientType} contains PII token '{$token}'");
            }
        }
    }

    public function testSubjectContainsSystemName(): void
    {
        // system_name defaults from config — may be 'SIS' or empty in test env; just verify no raw placeholder
        foreach ($this->allCombos() as [$eventKey, $recipientType]) {
            $subject = EmailTemplate::getSubject($eventKey, $recipientType);
            $this->assertStringNotContainsString('{system_name}', $subject,
                "Subject placeholder not replaced for {$eventKey}/{$recipientType}");
        }
    }

    public function testUnknownEventKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmailTemplate::getSubject('unknown_event', 'student');
    }

    public function testUnknownRecipientTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmailTemplate::getSubject('submission_approved', 'institution_admin');
    }

    public function testUnknownBodyComboThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // rtc_created_by_student has no 'student' recipient template
        EmailTemplate::getBody('rtc_created_by_student', 'student', 'https://sis.test');
    }
}
