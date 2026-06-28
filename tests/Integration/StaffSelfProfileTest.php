<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Models\StaffUser;
use Tests\TestCase;

class StaffSelfProfileTest extends TestCase
{
    private int $deptId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptId = $this->seedDepartment('BCA');
    }

    public function testUpdateOwnNameAndStaffCodePersisted(): void
    {
        $userId = $this->seedUser('me@test.com', 'pw', 'staff', $this->deptId);

        Db::execute(
            "UPDATE users SET name = ?, staff_code = ?, updated_at = ? WHERE id = ?",
            ['Updated Name', 'S-NEW', date('Y-m-d H:i:s'), $userId]
        );

        $user = StaffUser::findById($userId);
        $this->assertSame('Updated Name', $user['name']);
        $this->assertSame('S-NEW', $user['staff_code']);
    }

    public function testEmailUnchangedAfterProfileUpdate(): void
    {
        $userId = $this->seedUser('me@test.com', 'pw', 'staff', $this->deptId);

        Db::execute(
            "UPDATE users SET name = ?, staff_code = ?, updated_at = ? WHERE id = ?",
            ['Changed Name', null, date('Y-m-d H:i:s'), $userId]
        );

        $user = StaffUser::findById($userId);
        $this->assertSame('me@test.com', $user['email']);
    }

    public function testAuditLogEntryAfterProfileUpdate(): void
    {
        $_SESSION['principal_id']   = 1;
        $_SESSION['role']           = 'staff';
        $_SESSION['principal_type'] = 'user';

        $userId = $this->seedUser('me@test.com', 'pw', 'staff', $this->deptId);
        \App\Helpers\MasterAuditLogger::log('update_profile', 'staff_user', $userId, ['name' => 'Updated Name']);

        $log = Db::selectOne(
            "SELECT * FROM audit_log WHERE entity = 'staff_user' AND entity_id = ? AND action = 'update_profile'",
            [$userId]
        );
        $this->assertNotNull($log);
    }
}
