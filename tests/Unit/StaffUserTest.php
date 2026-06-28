<?php
namespace Tests\Unit;

use App\Models\StaffUser;
use Tests\TestCase;

class StaffUserTest extends TestCase
{
    private int $deptA;
    private int $deptB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptA = $this->seedDepartment('BCA');
        $this->deptB = $this->seedDepartment('MCA');
    }

    public function testFindByDeptReturnsOnlyThatDeptsStaff(): void
    {
        $this->seedUser('staffA@test.com', 'pw', 'staff', $this->deptA);
        $this->seedUser('staffB@test.com', 'pw', 'staff', $this->deptB);
        $this->seedUser('admin@test.com',  'pw', 'dept_admin', $this->deptA);

        $staff = StaffUser::findByDept($this->deptA);
        $this->assertCount(2, $staff); // one staff + one dept_admin
        foreach ($staff as $s) {
            $this->assertSame($this->deptA, (int)$s['department_id']);
        }
    }

    public function testHasDeptAdminFalseWhenNone(): void
    {
        $this->assertFalse(StaffUser::hasDeptAdmin($this->deptA));
    }

    public function testHasDeptAdminTrueWhenOneExists(): void
    {
        $adminId = $this->seedUser('admin@test.com', 'pw', 'dept_admin', $this->deptA);
        $this->assertTrue(StaffUser::hasDeptAdmin($this->deptA));
    }

    public function testHasDeptAdminFalseWhenExcludingOnlyAdmin(): void
    {
        $adminId = $this->seedUser('admin@test.com', 'pw', 'dept_admin', $this->deptA);
        $this->assertFalse(StaffUser::hasDeptAdmin($this->deptA, $adminId));
    }

    public function testPendingItemsCountReturnsCorrectCount(): void
    {
        $studentId = $this->seedFullStudent(['department_id' => $this->deptA]);

        $this->pdo->prepare(
            "INSERT INTO change_requests (student_id, department_id, initiated_by, initiator_type, reason, proposed_changes, status, created_at)
             VALUES (?,?,?,?,?,?,?,?)"
        )->execute([$studentId, $this->deptA, 1, 'student', 'reason', '[]', 'pending', date('Y-m-d H:i:s')]);

        $this->assertSame(1, StaffUser::pendingItemsCount($this->deptA));
        $this->assertSame(0, StaffUser::pendingItemsCount($this->deptB));
    }
}
