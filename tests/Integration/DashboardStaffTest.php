<?php
namespace Tests\Integration;

use App\Helpers\DashboardQuery;
use Tests\TestCase;

class DashboardStaffTest extends TestCase
{
    public function testBuildStaffQueueCounts(): void
    {
        $deptA = $this->seedDepartment('BCA');
        $deptB = $this->seedDepartment('MCA');

        // deptA: one form_submitted student
        $s1 = $this->seedFullStudent(['department_id' => $deptA, 'onboarding_status' => 'form_submitted']);
        // deptA: one pending enrolment
        $s2 = $this->seedFullStudent(['department_id' => $deptA, 'onboarding_status' => 'pending_enrolment',
                                       'enrolment_approval_status' => 'pending']);
        // deptA: one pending RTC
        $this->pdo->prepare(
            "INSERT INTO change_requests (student_id, department_id, initiated_by, initiator_type, reason, proposed_changes, status, created_at)
             VALUES (?,?,?,?,?,?,?,?)"
        )->execute([$s1, $deptA, 1, 'student', 'reason', '[]', 'pending', date('Y-m-d H:i:s')]);

        $counts = DashboardQuery::queueCounts($deptA);
        $this->assertSame(1, $counts['pending_approvals']);
        $this->assertSame(1, $counts['pending_rtcs']);
        $this->assertSame(1, $counts['pending_enrolments']);

        // Staff in deptA should see 0 for deptB data
        $countsB = DashboardQuery::queueCounts($deptB);
        $this->assertSame(0, $countsB['pending_approvals']);
        $this->assertSame(0, $countsB['pending_rtcs']);
    }
}
