<?php
namespace Tests\Unit;

use App\Helpers\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testMobile(): void
    {
        $this->assertTrue(Validator::mobile('9879879870'));
        $this->assertFalse(Validator::mobile('98798'));      // too short
        $this->assertFalse(Validator::mobile('98798798701')); // too long
        $this->assertFalse(Validator::mobile('98798abcd0'));  // non-numeric
    }

    public function testAadhaar(): void
    {
        $this->assertTrue(Validator::aadhaar('123456789012'));
        $this->assertFalse(Validator::aadhaar('12345678901'));  // 11 digits
    }

    public function testEmailAndDate(): void
    {
        $this->assertTrue(Validator::email('a@b.com'));
        $this->assertFalse(Validator::email('not-an-email'));
        $this->assertTrue(Validator::date('2007-10-10'));
        $this->assertFalse(Validator::date('2007-13-40'));
        $this->assertFalse(Validator::date('10/10/2007'));
    }

    public function testPassword(): void
    {
        $this->assertTrue(Validator::password('abc12345'));     // >=8 + digit
        $this->assertFalse(Validator::password('abcdefgh'));    // no digit
        $this->assertFalse(Validator::password('a1b2'));        // too short
    }

    public function testUploads(): void
    {
        $this->assertTrue(Validator::pdfUpload(['type' => 'application/pdf', 'size' => 1000000]));
        $this->assertFalse(Validator::pdfUpload(['type' => 'application/pdf', 'size' => 3000000])); // >2MB
        $this->assertFalse(Validator::pdfUpload(['type' => 'image/png', 'size' => 1000]));          // wrong type
        $this->assertTrue(Validator::imageUpload(['type' => 'image/jpeg', 'size' => 1000]));
        $this->assertFalse(Validator::imageUpload(['type' => 'application/pdf', 'size' => 1000]));   // photo image-only
    }
}
