<?php
namespace Tests\Unit;

use App\Helpers\Otp;
use Tests\TestCase;

/** OTP generate/verify, single-use + expiry (Design §5.3; Task M1-T07). */
class OtpTest extends TestCase
{
    public function testGenerateThenVerifyOnce(): void
    {
        $code = Otp::generate('student', 1);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->assertTrue(Otp::verify('student', 1, $code));   // first use succeeds
        $this->assertFalse(Otp::verify('student', 1, $code));  // single-use: second fails
    }

    public function testWrongCodeFails(): void
    {
        Otp::generate('student', 2);
        $this->assertFalse(Otp::verify('student', 2, '000000'));
    }

    public function testExpiredCodeFails(): void
    {
        // Insert an already-expired OTP directly
        $code = '654321';
        $this->pdo->prepare(
            "INSERT INTO login_otps (principal_type, principal_id, code_hash, expires_at, created_at)
             VALUES (?,?,?,?,?)"
        )->execute(['student', 3, password_hash($code, PASSWORD_BCRYPT),
                    date('Y-m-d H:i:s', time() - 60), date('Y-m-d H:i:s', time() - 120)]);

        $this->assertFalse(Otp::verify('student', 3, $code));
    }
}
