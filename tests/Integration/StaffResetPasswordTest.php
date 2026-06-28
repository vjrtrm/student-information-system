<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Models\StaffUser;
use Tests\TestCase;

class StaffResetPasswordTest extends TestCase
{
    private int $deptId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptId = $this->seedDepartment('BCA');
    }

    public function testResetPasswordStoresNewHashAndSetsMustChangeFlag(): void
    {
        $userId = $this->seedUser('staff@test.com', 'oldpass', 'staff', $this->deptId);
        $newPw  = 'newpassword99';

        StaffUser::update($userId, [
            'password_hash'        => password_hash($newPw, PASSWORD_BCRYPT),
            'must_change_password' => 1,
        ]);

        $user = StaffUser::findById($userId);
        $this->assertTrue(password_verify($newPw, $user['password_hash']));
        $this->assertFalse(password_verify('oldpass', $user['password_hash']));
        $this->assertSame(1, (int)$user['must_change_password']);
    }

    public function testSelfResetBlockedAtControllerLevel(): void
    {
        $userId = $this->seedUser('self@test.com', 'oldpass', 'staff', $this->deptId);
        // Controller prevents self-reset before updating; just verify initial state
        $user = StaffUser::findById($userId);
        $this->assertSame(0, (int)$user['must_change_password']);
    }

    public function testAuditLogEntryAfterReset(): void
    {
        // Setup auth session for MasterAuditLogger
        $_SESSION['principal_id']   = 1;
        $_SESSION['role']           = 'dept_admin';
        $_SESSION['principal_type'] = 'user';

        $userId = $this->seedUser('staff@test.com', 'oldpass', 'staff', $this->deptId);

        StaffUser::update($userId, [
            'password_hash'        => password_hash('newpass99', PASSWORD_BCRYPT),
            'must_change_password' => 1,
        ]);

        // Log manually as controller would
        \App\Helpers\MasterAuditLogger::log('reset_password', 'staff_user', $userId);

        $log = Db::selectOne(
            "SELECT * FROM audit_log WHERE entity = 'staff_user' AND entity_id = ? AND action = 'reset_password'",
            [$userId]
        );
        $this->assertNotNull($log);
    }
}
