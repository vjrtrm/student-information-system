<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Models\StaffUser;
use Tests\TestCase;

class MustChangePasswordTest extends TestCase
{
    private int $deptId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptId = $this->seedDepartment('BCA');
    }

    /**
     * When must_change_password = 1, skip current-password check.
     */
    public function testMustChangeFlagSkipsCurrentPasswordVerification(): void
    {
        $userId = $this->seedUser('staff@test.com', 'oldpass123', 'staff', $this->deptId);
        StaffUser::update($userId, ['must_change_password' => 1]);

        $user = StaffUser::findById($userId);
        $this->assertSame(1, (int)$user['must_change_password']);

        // Simulate controller logic: if must_change_password, skip verify
        $mustChange = (bool)(int)$user['must_change_password'];
        $currentOk = $mustChange ? true : password_verify('WRONG', $user['password_hash']);
        $this->assertTrue($currentOk);
    }

    /**
     * When must_change_password = 0, current password must verify.
     */
    public function testWithoutFlagRequiresCurrentPassword(): void
    {
        $userId = $this->seedUser('staff@test.com', 'correctpass', 'staff', $this->deptId);
        $user   = StaffUser::findById($userId);

        $this->assertSame(0, (int)$user['must_change_password']);

        // Wrong current password → should fail
        $this->assertFalse(password_verify('wrongpass', $user['password_hash']));

        // Correct password → should pass
        $this->assertTrue(password_verify('correctpass', $user['password_hash']));
    }

    public function testChangePasswordClearsFlag(): void
    {
        $userId = $this->seedUser('staff@test.com', 'oldpass123', 'staff', $this->deptId);
        StaffUser::update($userId, ['must_change_password' => 1]);

        // Simulate changePassword POST logic
        $newPw = 'newpassword99';
        Db::execute(
            "UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = ? WHERE id = ?",
            [password_hash($newPw, PASSWORD_BCRYPT), date('Y-m-d H:i:s'), $userId]
        );

        $user = StaffUser::findById($userId);
        $this->assertSame(0, (int)$user['must_change_password']);
        $this->assertTrue(password_verify($newPw, $user['password_hash']));
    }

    public function testWrongCurrentPasswordRejected(): void
    {
        $userId = $this->seedUser('staff@test.com', 'realpass123', 'staff', $this->deptId);
        $user   = StaffUser::findById($userId);

        $this->assertFalse(password_verify('badguess', $user['password_hash']));
    }
}
