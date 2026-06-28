<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\MasterAuditLogger;
use App\Middleware\RoleMiddleware;
use App\Models\ChangeRequest;
use App\Models\Department;
use App\Models\NotificationEvent;
use App\Models\Student;

class ApprovalController extends Controller
{
    private const STAFF_ROLES = ['staff', 'dept_admin', 'institution_admin'];

    // GET /approvals
    public function index(): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $role = Auth::role();

        $filterDeptId = null;
        if ($role === 'institution_admin' && !empty($_GET['department_id'])) {
            $filterDeptId = (int)$_GET['department_id'];
        }

        if ($role === 'institution_admin') {
            $pendingStudents = self::fetchPendingStudents(null, $filterDeptId);
            $pendingRtcs     = ChangeRequest::findAllPending($filterDeptId);
            $departments     = Department::allActive();
        } else {
            $deptId          = Auth::departmentId();
            $pendingStudents = self::fetchPendingStudents($deptId);
            $pendingRtcs     = ChangeRequest::findPending($deptId);
            $departments     = [];
        }

        $this->render('approvals/index', compact(
            'pendingStudents', 'pendingRtcs', 'departments', 'role', 'filterDeptId'
        ));
    }

    // POST /approvals/{studentId}/approve
    public function approveSubmission(int $studentId): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $this->requireCsrf();

        $student = Student::find($studentId);
        if (!$student) {
            http_response_code(404);
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Student not found.'];
            $this->redirect('/approvals');
            return;
        }

        $role = Auth::role();
        if ($role !== 'institution_admin' && (int)$student['department_id'] !== Auth::departmentId()) {
            http_response_code(403);
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Access denied.'];
            $this->redirect('/approvals');
            return;
        }

        if ($student['onboarding_status'] === 'approved') {
            $_SESSION['flash'] = ['type' => 'info', 'message' => 'Submission already approved.'];
            $this->redirect('/approvals');
            return;
        }

        if ($student['onboarding_status'] !== 'form_submitted') {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Student form is not submitted yet.'];
            $this->redirect('/approvals');
            return;
        }

        $now        = date('Y-m-d H:i:s');
        $reviewerId = Auth::id();

        Db::execute(
            "UPDATE students SET onboarding_status = 'approved', approval_by = ?, approval_at = ? WHERE id = ?",
            [$reviewerId, $now, $studentId]
        );

        MasterAuditLogger::log('submission_approved', 'student', $studentId, [
            'approved_by' => $reviewerId,
        ]);

        $payload = [
            'student_id'       => $studentId,
            'dept_id'          => $student['department_id'],
            'enrolment_serial' => $student['enrolment_serial'] ?? null,
        ];
        NotificationEvent::record('submission_approved', $studentId, $reviewerId, 'student',    $studentId, null, $payload);
        NotificationEvent::record('submission_approved', $studentId, $reviewerId, 'dept_admin', null,       null, $payload);

        $name = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Submission approved for {$name}."];
        $this->redirect('/approvals');
    }

    // ---------------------------------------------------------------
    private static function fetchPendingStudents(?int $deptId, ?int $filterDeptId = null): array
    {
        if ($deptId !== null) {
            return Db::selectAll(
                "SELECT s.id, s.first_name, s.last_name, s.enrolment_number, s.enrolment_serial,
                        s.programme_level, s.department_id,
                        ov_cls.display AS class_name, sp.form_submitted_at
                 FROM students s
                 LEFT JOIN option_values ov_cls ON ov_cls.id = s.class_id
                 LEFT JOIN student_profiles sp  ON sp.student_id = s.id
                 WHERE s.department_id = ? AND s.onboarding_status = 'form_submitted'
                 ORDER BY sp.form_submitted_at ASC",
                [$deptId]
            );
        }

        $params    = [];
        $deptWhere = '';
        if ($filterDeptId) {
            $deptWhere = 'AND s.department_id = ?';
            $params[]  = $filterDeptId;
        }
        return Db::selectAll(
            "SELECT s.id, s.first_name, s.last_name, s.enrolment_number, s.enrolment_serial,
                    s.programme_level, s.department_id, d.name AS department_name,
                    ov_cls.display AS class_name, sp.form_submitted_at
             FROM students s
             JOIN departments d ON d.id = s.department_id
             LEFT JOIN option_values ov_cls ON ov_cls.id = s.class_id
             LEFT JOIN student_profiles sp  ON sp.student_id = s.id
             WHERE s.onboarding_status = 'form_submitted' {$deptWhere}
             ORDER BY sp.form_submitted_at ASC",
            $params
        );
    }
}
