<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Models\StaffUser;
use Tests\TestCase;

class StaffEditTest extends TestCase
{
    private int $deptId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptId = $this->seedDepartment('BCA');
    }

    public function testEditNameIsPersisted(): void
    {
        $userId = $this->seedUser('staff@test.com', 'pw', 'staff', $this->deptId);

        StaffUser::update($userId, ['name' => 'Updated Name']);

        $user = StaffUser::findById($userId);
        $this->assertSame('Updated Name', $user['name']);
    }

    public function testDeptAdminEditDoesNotAffectEmail(): void
    {
        // dept_admin editing: only name, staff_code, status are passed
        $userId = $this->seedUser('staff@test.com', 'pw', 'staff', $this->deptId);

        // email not in update data → unchanged
        StaffUser::update($userId, ['name' => 'Changed', 'status' => 'active']);

        $user = StaffUser::findById($userId);
        $this->assertSame('staff@test.com', $user['email']);
    }

    public function testInstAdminCanChangeRole(): void
    {
        $userId = $this->seedUser('staff@test.com', 'pw', 'staff', $this->deptId);

        StaffUser::update($userId, ['role' => 'dept_admin']);

        $user = StaffUser::findById($userId);
        $this->assertSame('dept_admin', $user['role']);
    }

    public function testHasDeptAdminBlocksSecondDeptAdmin(): void
    {
        $admin1 = $this->seedUser('admin1@test.com', 'pw', 'dept_admin', $this->deptId);
        $staff  = $this->seedUser('staff@test.com',  'pw', 'staff',      $this->deptId);

        // Trying to make $staff also a dept_admin
        $wouldBlock = StaffUser::hasDeptAdmin($this->deptId, $staff);
        $this->assertTrue($wouldBlock);
    }
}
