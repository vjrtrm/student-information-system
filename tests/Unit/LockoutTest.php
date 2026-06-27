<?php
namespace Tests\Unit;

use App\Helpers\Lockout;
use Tests\TestCase;

/** Brute-force lockout (Design §D1; Task M1-T06, M1-T27). */
class LockoutTest extends TestCase
{
    public function testLocksAfterThresholdAndResets(): void
    {
        $id = $this->seedUser('s@b.com', 'Secret#1');

        // 4 failures: not locked yet (threshold = 5)
        for ($i = 0; $i < 4; $i++) {
            $r = Lockout::registerFailure('users', $id);
            $this->assertFalse($r['locked'], "attempt {$r['attempts']} should not lock");
        }
        $this->assertFalse(Lockout::isLocked('users', $id));

        // 5th failure locks
        $r = Lockout::registerFailure('users', $id);
        $this->assertTrue($r['locked']);
        $this->assertTrue(Lockout::isLocked('users', $id));
        $this->assertGreaterThan(0, Lockout::minutesRemaining('users', $id));

        // success resets
        Lockout::reset('users', $id);
        $this->assertFalse(Lockout::isLocked('users', $id));
    }

    public function testWorksForStudentsToo(): void
    {
        $id = $this->seedStudent('9000000000', '2007-01-01');
        for ($i = 0; $i < 5; $i++) Lockout::registerFailure('students', $id);
        $this->assertTrue(Lockout::isLocked('students', $id));
    }

    public function testRejectsUnknownTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Lockout::isLocked('secrets', 1); // table name is whitelisted (no SQLi surface)
    }
}
