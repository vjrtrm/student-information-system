<?php
namespace Tests\Integration;

use App\Helpers\DashboardQuery;
use Tests\TestCase;

class DashboardStudentTest extends TestCase
{
    public function testBuildStudentSummaryWithProfile(): void
    {
        $deptId    = $this->seedDepartment();
        $studentId = $this->seedFullStudent(['department_id' => $deptId, 'onboarding_status' => 'form_submitted']);

        // Seed a profile
        $this->pdo->prepare(
            "INSERT INTO student_profiles (student_id, form_status, form_completion_pct, created_at)
             VALUES (?,?,?,?)"
        )->execute([$studentId, 'submitted', 100, date('Y-m-d H:i:s')]);

        $summary = DashboardQuery::studentSummary($studentId);
        $this->assertSame('form_submitted', $summary['onboarding_status']);
        $this->assertSame('submitted', $summary['form_status']);

        $pendingRtc = DashboardQuery::pendingRtc($studentId);
        $this->assertNull($pendingRtc);
    }

    public function testPendingRtcReturnsRowWhenExists(): void
    {
        $deptId    = $this->seedDepartment();
        $studentId = $this->seedFullStudent(['department_id' => $deptId]);

        $this->pdo->prepare(
            "INSERT INTO change_requests (student_id, department_id, initiated_by, initiator_type, reason, proposed_changes, status, created_at)
             VALUES (?,?,?,?,?,?,?,?)"
        )->execute([$studentId, $deptId, $studentId, 'student', 'reason', '[]', 'pending', date('Y-m-d H:i:s')]);

        $pendingRtc = DashboardQuery::pendingRtc($studentId);
        $this->assertNotNull($pendingRtc);
        $this->assertSame('pending', $pendingRtc['status']);
    }

    public function testStudentSummaryWithNoProfileHasNullFormStatus(): void
    {
        $deptId    = $this->seedDepartment();
        $studentId = $this->seedFullStudent(['department_id' => $deptId]);

        $summary = DashboardQuery::studentSummary($studentId);
        $this->assertNull($summary['form_status']);
    }
}
