<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\DashboardQuery;
use App\Middleware\RoleMiddleware;

class DashboardController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::handle(['student', 'staff', 'dept_admin', 'institution_admin']);

        $role = Auth::role();
        switch ($role) {
            case 'student':
                [$template, $data] = $this->buildStudent();
                break;
            case 'staff':
                [$template, $data] = $this->buildStaff();
                break;
            case 'dept_admin':
                [$template, $data] = $this->buildDeptAdmin();
                break;
            case 'institution_admin':
            default:
                [$template, $data] = $this->buildInstitutionAdmin();
                break;
        }

        $this->render($template, $data);
    }

    private function buildStudent(): array
    {
        $studentId           = (int)Auth::id();
        $summary             = DashboardQuery::studentSummary($studentId);
        $pendingRtc          = DashboardQuery::pendingRtc($studentId);
        $recentNotifications = DashboardQuery::recentNotifications($studentId, 30);

        return ['dashboard/student', array_merge($summary, [
            'title'                => 'My Dashboard',
            'pending_rtc'          => $pendingRtc,
            'recent_notifications' => $recentNotifications,
        ])];
    }

    private function buildStaff(): array
    {
        $deptId      = (int)Auth::departmentId();
        $queueCounts = DashboardQuery::queueCounts($deptId);
        $recentAudit = DashboardQuery::recentAudit($deptId, 10);

        return ['dashboard/staff', [
            'title'        => 'Staff Dashboard',
            'queue_counts' => $queueCounts,
            'recent_audit' => $recentAudit,
        ]];
    }

    private function buildDeptAdmin(): array
    {
        $deptId               = (int)Auth::departmentId();
        $queueCounts          = DashboardQuery::queueCounts($deptId);
        $unsentNotifications  = DashboardQuery::unsentNotifications($deptId);
        $deptSummary          = DashboardQuery::deptSummary($deptId);
        $funnelChart          = DashboardQuery::funnelChartData($deptId);
        $recentAudit          = DashboardQuery::recentAudit($deptId, 10);

        return ['dashboard/dept_admin', [
            'title'                => 'Department Dashboard',
            'queue_counts'         => $queueCounts,
            'unsent_notifications' => $unsentNotifications,
            'dept_summary'         => $deptSummary,
            'funnel_chart'         => $funnelChart,
            'recent_audit'         => $recentAudit,
        ]];
    }

    private function buildInstitutionAdmin(): array
    {
        if (isset($_GET['pref_academic_year'])) {
            $raw = (int)$_GET['pref_academic_year'];
            $_SESSION['pref_academic_year'] = $raw > 0 ? $raw : null;
        }
        if (isset($_GET['pref_department_id'])) {
            $raw = (int)$_GET['pref_department_id'];
            $_SESSION['pref_department_id'] = $raw > 0 ? $raw : null;
        }

        $ayId   = $_SESSION['pref_academic_year'] ?? null;
        $deptId = $_SESSION['pref_department_id'] ?? null;

        $kpis                 = DashboardQuery::institutionKpis($deptId, $ayId);
        $deptBreakdown        = DashboardQuery::deptBreakdown($deptId, $ayId);
        $enrolmentStatusChart = DashboardQuery::enrolmentStatusChartData($deptId, $ayId);
        $deptComparisonChart  = DashboardQuery::deptComparisonChartData($deptId, $ayId);
        $formCompletionChart  = DashboardQuery::formCompletionChartData($deptId, $ayId);

        $ayList = Db::selectAll(
            "SELECT ov.id, ov.value, ov.display
             FROM option_values ov
             JOIN option_lists ol ON ol.id = ov.list_id
             WHERE ol.list_key = 'academic_year' AND ov.status = 'active'
             ORDER BY ov.sort_order ASC"
        );

        $departments = Db::selectAll(
            "SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC"
        );

        return ['dashboard/institution_admin', [
            'title'                  => 'Institution Dashboard',
            'kpis'                   => $kpis,
            'dept_breakdown'         => $deptBreakdown,
            'enrolment_status_chart' => $enrolmentStatusChart,
            'dept_comparison_chart'  => $deptComparisonChart,
            'form_completion_chart'  => $formCompletionChart,
            'ay_list'                => $ayList,
            'departments'            => $departments,
            'prefs'                  => [
                'academic_year' => $ayId,
                'department_id' => $deptId,
            ],
        ]];
    }
}
