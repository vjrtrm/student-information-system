<?php
namespace Tests\Unit;

use App\Helpers\AccessControl;
use PHPUnit\Framework\TestCase;

/** RBAC matrix + department scoping (Design §6; Task M1-T28). */
class AccessControlTest extends TestCase
{
    public function testStudentReachesOnlyOwnRecord(): void
    {
        $this->assertTrue(AccessControl::can('student', 'access_own_student_record'));
        $this->assertFalse(AccessControl::can('student', 'access_department_students'));
        $this->assertFalse(AccessControl::can('student', 'reach_admin_routes'));
    }

    public function testStaffCannotReachAdminRoutes(): void
    {
        $this->assertTrue(AccessControl::can('staff', 'access_department_students'));
        $this->assertFalse(AccessControl::can('staff', 'reach_admin_routes'));
        $this->assertFalse(AccessControl::can('staff', 'cross_department_access'));
    }

    public function testDeptAdminManagesOwnDeptButNotCrossDept(): void
    {
        $this->assertTrue(AccessControl::can('dept_admin', 'reach_admin_routes'));
        $this->assertTrue(AccessControl::can('dept_admin', 'manage_department_data'));
        $this->assertFalse(AccessControl::can('dept_admin', 'cross_department_access'));
    }

    public function testInstitutionAdminCrossDepartment(): void
    {
        $this->assertTrue(AccessControl::can('institution_admin', 'cross_department_access'));
        $this->assertTrue(AccessControl::can('institution_admin', 'reach_admin_routes'));
    }

    public function testDepartmentScoping(): void
    {
        // institution_admin can access any department
        $this->assertTrue(AccessControl::canAccessDepartment('institution_admin', null, 5));
        // staff/dept_admin only their own
        $this->assertTrue(AccessControl::canAccessDepartment('staff', 3, 3));
        $this->assertFalse(AccessControl::canAccessDepartment('staff', 3, 4));
        $this->assertFalse(AccessControl::canAccessDepartment('dept_admin', 3, 4));
        // students never via this path
        $this->assertFalse(AccessControl::canAccessDepartment('student', 3, 3));
    }

    public function testUnknownRoleDenied(): void
    {
        $this->assertFalse(AccessControl::can(null, 'reach_admin_routes'));
        $this->assertFalse(AccessControl::can('ghost', 'access_department_students'));
    }
}
