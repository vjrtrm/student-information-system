<?php
namespace Tests\Integration;

use App\Helpers\ResetToken;
use Tests\TestCase;

/** Reset token create → consume, single-use + expiry (Design §5.4; Task M1-T26). */
class PasswordResetTest extends TestCase
{
    public function testTokenRoundTripSingleUse(): void
    {
        $uid = $this->seedUser('reset@b.com', 'OldPass#1');
        $token = ResetToken::create($uid);

        $this->assertTrue(ResetToken::consume($uid, $token));   // valid once
        $this->assertFalse(ResetToken::consume($uid, $token));  // cannot reuse
    }

    public function testWrongTokenRejected(): void
    {
        $uid = $this->seedUser('reset2@b.com', 'OldPass#1');
        ResetToken::create($uid);
        $this->assertFalse(ResetToken::consume($uid, 'deadbeef'));
    }

    public function testExpiredTokenRejected(): void
    {
        $uid = $this->seedUser('reset3@b.com', 'OldPass#1');
        $raw = 'a1b2c3d4e5f6';
        $this->pdo->prepare(
            "INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?,?,?,?)"
        )->execute([$uid, hash('sha256', $raw), date('Y-m-d H:i:s', time() - 60), date('Y-m-d H:i:s', time() - 120)]);

        $this->assertFalse(ResetToken::consume($uid, $raw));
    }
}
