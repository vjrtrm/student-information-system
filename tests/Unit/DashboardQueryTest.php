<?php
namespace Tests\Unit;

use App\Helpers\DashboardQuery;
use Tests\TestCase;

class DashboardQueryTest extends TestCase
{
    private int $deptId;
    private int $deptId2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptId  = $this->seedDepartment('BCA');
        $this->deptId2 = $this->seedDepartment('MCA');
    }

    private function seedStaff(int $deptId = 0): int
    {
        return $this->seedUser('staff@test.com', 'password', 'staff', $deptId ?: $this->deptId);
    }

    public function testQueueCountsAreZeroWhenNoData(): void
    {
        $counts = DashboardQuery::queueCounts($this->deptId);
        $this->assertSame(0, $counts['pending_approvals']);
        $this->assertSame(0, $counts['pending_rtcs']);
        $this->assertSame(0, $counts['pending_enrolments']);
    }

    public function testQueueCountsCorrect(): void
    {
        // form_submitted → pending_approvals
        $s1 = $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'form_submitted']);
        // pending enrolment
        $s2 = $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'pending_enrolment',
                                       'enrolment_approval_status' => 'pending']);
        // pending RTC
        $this->pdo->prepare(
            "INSERT INTO change_requests (student_id, department_id, initiated_by, initiator_type, reason, proposed_changes, status, created_at)
             VALUES (?,?,?,?,?,?,?,?)"
        )->execute([$s1, $this->deptId, 1, 'student', 'test', '[]', 'pending', date('Y-m-d H:i:s')]);

        $counts = DashboardQuery::queueCounts($this->deptId);
        $this->assertSame(1, $counts['pending_approvals']);
        $this->assertSame(1, $counts['pending_rtcs']);
        $this->assertSame(1, $counts['pending_enrolments']);
    }

    public function testQueueCountsScopedToDept(): void
    {
        // Put data in deptId2 only
        $s = $this->seedFullStudent(['department_id' => $this->deptId2, 'onboarding_status' => 'form_submitted']);
        $counts = DashboardQuery::queueCounts($this->deptId);
        $this->assertSame(0, $counts['pending_approvals']);
    }

    public function testDeptSummarySumsCorrectly(): void
    {
        $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'approved']);
        $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'form_submitted']);
        $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'pending_enrolment',
                                 'enrolment_approval_status' => 'pending']);

        $summary = DashboardQuery::deptSummary($this->deptId);
        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['approved']);
    }

    public function testInstitutionKpisNullDeptSumsAll(): void
    {
        $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'approved']);
        $this->seedFullStudent(['department_id' => $this->deptId2, 'onboarding_status' => 'approved']);

        $kpis = DashboardQuery::institutionKpis(null, null);
        $this->assertSame(2, $kpis['total_students']);
        $this->assertSame(2, $kpis['approved']);
    }

    public function testInstitutionKpisWithDeptFiltersToDept(): void
    {
        $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'approved']);
        $this->seedFullStudent(['department_id' => $this->deptId2, 'onboarding_status' => 'approved']);

        $kpis = DashboardQuery::institutionKpis($this->deptId, null);
        $this->assertSame(1, $kpis['total_students']);
        $this->assertSame(1, $kpis['approved']);
    }

    public function testEnrolmentStatusChartDataReturnsArrays(): void
    {
        $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'approved']);
        $this->seedFullStudent(['department_id' => $this->deptId, 'onboarding_status' => 'form_submitted']);

        $chart = DashboardQuery::enrolmentStatusChartData($this->deptId);
        $this->assertIsArray($chart['labels']);
        $this->assertIsArray($chart['counts']);
        $this->assertCount(count($chart['labels']), $chart['counts']);
        $this->assertContains('approved', $chart['labels']);
    }

    public function testFunnelChartDataReturnsExactlyFourLabels(): void
    {
        $funnel = DashboardQuery::funnelChartData($this->deptId);
        $this->assertCount(4, $funnel['labels']);
        $this->assertCount(4, $funnel['counts']);
    }
}
