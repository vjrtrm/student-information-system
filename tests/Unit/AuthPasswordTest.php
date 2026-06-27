<?php
namespace Tests\Unit;

use App\Helpers\Auth;
use PHPUnit\Framework\TestCase;

/** Password hashing uses bcrypt and verifies correctly (Design §B1). */
class AuthPasswordTest extends TestCase
{
    public function testHashIsBcryptAndVerifies(): void
    {
        $hash = Auth::hashPassword('Secret#123');
        $this->assertNotSame('Secret#123', $hash);
        $this->assertStringStartsWith('$2', $hash); // bcrypt prefix
        $this->assertTrue(Auth::verifyPassword('Secret#123', $hash));
        $this->assertFalse(Auth::verifyPassword('wrong', $hash));
    }
}
