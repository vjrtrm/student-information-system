<?php
namespace Tests\Unit;

use App\Helpers\DashboardQuery;
use Tests\TestCase;

class DashboardQueryAcademicYearTest extends TestCase
{
    private int $deptId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptId = $this->seedDepartment('BCA');
    }

    public function testInstitutionKpisWithAyIdFiltersToThatYear(): void
    {
        // Two students with different academic_year_id
        $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'approved', 'academic_year_id' => 1]);
        $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'approved', 'academic_year_id' => 2]);

        $kpis1 = DashboardQuery::institutionKpis(null, 1);
        $this->assertSame(1, $kpis1['total_students']);

        $kpis2 = DashboardQuery::institutionKpis(null, 2);
        $this->assertSame(1, $kpis2['total_students']);
    }

    public function testInstitutionKpisWithNullAyIdReturnsAll(): void
    {
        $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'approved', 'academic_year_id' => 1]);
        $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'approved', 'academic_year_id' => 2]);

        $kpis = DashboardQuery::institutionKpis(null, null);
        $this->assertSame(2, $kpis['total_students']);
    }
}
