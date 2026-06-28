<?php
namespace Tests\Integration;

use App\Helpers\DashboardQuery;
use Tests\TestCase;

class DashboardInstitutionAdminTest extends TestCase
{
    public function testInstitutionKpisNullDeptSumsBothDepts(): void
    {
        $deptA = $this->seedDepartment('BCA');
        $deptB = $this->seedDepartment('MCA');

        $this->seedFullStudent(['department_id' => $deptA, 'onboarding_status' => 'approved']);
        $this->seedFullStudent(['department_id' => $deptB, 'onboarding_status' => 'approved']);

        $kpis = DashboardQuery::institutionKpis(null, null);
        $this->assertSame(2, $kpis['total_students']);
        $this->assertSame(2, $kpis['approved']);
    }

    public function testInstitutionKpisFilteredToDeptA(): void
    {
        $deptA = $this->seedDepartment('BCA');
        $deptB = $this->seedDepartment('MCA');

        $this->seedFullStudent(['department_id' => $deptA, 'onboarding_status' => 'approved']);
        $this->seedFullStudent(['department_id' => $deptB, 'onboarding_status' => 'approved']);

        $kpis = DashboardQuery::institutionKpis($deptA, null);
        $this->assertSame(1, $kpis['total_students']);
    }

    public function testDeptBreakdownReturnsOneRowPerDept(): void
    {
        $deptA = $this->seedDepartment('BCA');
        $deptB = $this->seedDepartment('MCA');

        $this->seedFullStudent(['department_id' => $deptA, 'onboarding_status' => 'approved']);
        $this->seedFullStudent(['department_id' => $deptB, 'onboarding_status' => 'pending_enrolment']);

        $breakdown = DashboardQuery::deptBreakdown(null, null);
        $this->assertCount(2, $breakdown);

        $deptNames = array_column($breakdown, 'dept_name');
        $this->assertContains('Dept BCA', $deptNames);
        $this->assertContains('Dept MCA', $deptNames);
    }
}
