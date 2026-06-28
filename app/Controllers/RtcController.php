<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\FieldConfig;
use App\Helpers\FormFieldRules;
use App\Helpers\MasterAuditLogger;
use App\Helpers\RtcFieldHelper;
use App\Helpers\RtcUploadHandler;
use App\Helpers\UploadException;
use App\Middleware\RoleMiddleware;
use App\Models\ChangeRequest;
use App\Models\NotificationEvent;
use App\Models\Student;
use App\Models\StudentProfile;

class RtcController extends Controller
{
    private const STAFF_ROLES = ['staff', 'dept_admin', 'institution_admin'];

    // GET /rtc/create?student_id={id}
    public function createForm(): void
    {
        $studentId = (int)($_GET['student_id'] ?? 0);
        $student   = $studentId ? Student::find($studentId) : null;
        if (!$student) {
            http_response_code(404);
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Student not found.'];
            $this->redirect('/approvals');
            return;
        }

        $role = Auth::role();

        if ($role === 'student' && Auth::id() !== $studentId) {
            http_response_code(403);
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Access denied.'];
            $this->redirect('/student/form/view');
            return;
        }

        if ($role !== 'student' && $role !== 'institution_admin') {
            RoleMiddleware::handle(self::STAFF_ROLES);
            if ((int)$student['department_id'] !== Auth::departmentId()) {
                http_response_code(403);
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Access denied.'];
                $this->redirect('/approvals');
                return;
            }
        }

        if (!in_array($student['onboarding_status'], ['form_submitted', 'approved'], true)) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'RTC is only available after form submission.'];
            $this->redirect('/student/form/view');
            return;
        }

        if (ChangeRequest::hasPending($studentId)) {
            $_SESSION['flash'] = ['type' => 'info', 'message' => 'A change request is already pending review.'];
            $back = ($role === 'student') ? '/student/form/view' : "/student/form/{$studentId}/view";
            $this->redirect($back);
            return;
        }

        $profile      = StudentProfile::findByStudent($studentId) ?? [];
        $rules        = FormFieldRules::getApplicableFields($profile, $student);
        $deptId       = (int)($student['department_id'] ?? 0);
        $customFields = FieldConfig::resolveCustomFields($deptId);
        $cdRows       = Db::selectAll('SELECT custom_field_id, value FROM student_custom_data WHERE student_id = ?', [$studentId]);
        $customData   = [];
        foreach ($cdRows as $row) { $customData[(int)$row['custom_field_id']] = $row['value']; }

        $this->render('approvals/rtc_form', compact('student', 'profile', 'rules', 'role', 'customFields', 'customData'));
    }

    // POST /rtc/create
    public function store(): void
    {
        $this->requireCsrf();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $student   = $studentId ? Student::find($studentId) : null;
        if (!$student) {
            http_response_code(404);
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Student not found.'];
            $this->redirect('/approvals');
            return;
        }

        $role = Auth::role();

        if ($role === 'student' && Auth::id() !== $studentId) {
            http_response_code(403);
            $this->redirect('/student/form/view');
            return;
        }
        if ($role !== 'student' && $role !== 'institution_admin') {
            if ((int)$student['department_id'] !== Auth::departmentId()) {
                http_response_code(403);
                $this->redirect('/approvals');
                return;
            }
        }

        if (ChangeRequest::hasPending($studentId)) {
            $_SESSION['flash'] = ['type' => 'info', 'message' => 'A change request is already pending review.'];
            $back = ($role === 'student') ? '/student/form/view' : "/student/form/{$studentId}/view";
            $this->redirect($back);
            return;
        }

        $reason = trim($_POST['reason'] ?? '');
        if (!$reason) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please provide a reason for the change request.'];
            $this->redirect("/rtc/create?student_id={$studentId}");
            return;
        }

        $profile       = StudentProfile::findByStudent($studentId) ?? [];
        $posted        = $_POST['fields'] ?? [];
        $deptId        = (int)($student['department_id'] ?? 0);
        $customFieldsRtc = FieldConfig::resolveCustomFields($deptId);
        $cdRowsRtc     = Db::selectAll('SELECT custom_field_id, value FROM student_custom_data WHERE student_id = ?', [$studentId]);
        $customDataRtc = [];
        foreach ($cdRowsRtc as $row) { $customDataRtc[(int)$row['custom_field_id']] = $row['value']; }
        $activeCustomKeysRtc = array_map(fn($cf) => 'custom_' . $cf['id'], $customFieldsRtc);

        try {
            $changeset = RtcFieldHelper::buildChangeset($posted, $profile, $student, $customDataRtc, $activeCustomKeysRtc);
        } catch (\InvalidArgumentException $e) {
            $changeset = [];
        }

        // Create RTC (may be updated below with file entries)
        $initiatorType = ($role === 'student') ? 'student' : 'staff';
        $rtcId = ChangeRequest::create([
            'student_id'       => $studentId,
            'department_id'    => $student['department_id'],
            'initiated_by'     => Auth::id(),
            'initiator_type'   => $initiatorType,
            'reason'           => $reason,
            'proposed_changes' => $changeset,
        ]);

        // Handle file uploads
        $rules      = FormFieldRules::getApplicableFields($profile, $student);
        $uploadErrors = [];
        foreach ($rules as $f) {
            if (!in_array($f['type'], ['file', 'photo'], true)) continue;
            if (empty($_FILES[$f['key']]) || ($_FILES[$f['key']]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            try {
                $isPhoto  = ($f['type'] === 'photo');
                $tempPath = RtcUploadHandler::storeTemp($f['key'], $_FILES[$f['key']], $rtcId, $isPhoto);
                $changeset[] = [
                    'field_key'      => $f['key'],
                    'label'          => $f['label'],
                    'current_value'  => $profile[$f['key']] ?? null,
                    'proposed_value' => $tempPath,
                    'is_file'        => true,
                ];
            } catch (UploadException $e) {
                $uploadErrors[] = $f['label'] . ': ' . $e->getMessage();
            }
        }

        if (empty($changeset)) {
            // No valid changes — auto-reject the placeholder
            Db::execute(
                "UPDATE change_requests SET status='rejected', rejection_reason='No changes provided', updated_at=? WHERE id=?",
                [date('Y-m-d H:i:s'), $rtcId]
            );
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No changes were specified.'];
            $this->redirect("/rtc/create?student_id={$studentId}");
            return;
        }

        // Update with final changeset
        Db::execute(
            "UPDATE change_requests SET proposed_changes = ?, updated_at = ? WHERE id = ?",
            [json_encode($changeset), date('Y-m-d H:i:s'), $rtcId]
        );

        // Notification events
        $fieldKeys = array_column($changeset, 'field_key');
        $payload   = ['change_request_id' => $rtcId, 'dept_id' => $student['department_id'], 'field_keys' => $fieldKeys];

        if ($initiatorType === 'student') {
            NotificationEvent::record('rtc_created_by_student', $studentId, Auth::id(), 'dept_admin', null, $rtcId, $payload);
        } else {
            NotificationEvent::record('rtc_created_by_staff', $studentId, Auth::id(), 'student',    $studentId, $rtcId, $payload);
            NotificationEvent::record('rtc_created_by_staff', $studentId, Auth::id(), 'dept_admin', null,       $rtcId, $payload);
        }

        MasterAuditLogger::log('rtc_created', 'change_request', $rtcId, [
            'student_id'     => $studentId,
            'initiator_type' => $initiatorType,
            'field_keys'     => $fieldKeys,
        ]);

        $msg = $uploadErrors
            ? 'Change request submitted. Some files failed: ' . implode('; ', $uploadErrors)
            : 'Your change request has been submitted for staff review.';
        $_SESSION['flash'] = ['type' => $uploadErrors ? 'warning' : 'success', 'message' => $msg];
        $this->redirect($role === 'student' ? '/rtc/history' : '/approvals');
    }

    // GET /rtc/{id}
    public function detail(int $rtcId): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $rtc = ChangeRequest::findById($rtcId);
        if (!$rtc) {
            http_response_code(404);
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Change request not found.'];
            $this->redirect('/approvals');
            return;
        }

        $role = Auth::role();
        if ($role !== 'institution_admin' && (int)$rtc['department_id'] !== Auth::departmentId()) {
            http_response_code(403);
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Access denied.'];
            $this->redirect('/approvals');
            return;
        }

        $student      = Student::find((int)$rtc['student_id']);
        $deptId       = (int)($student['department_id'] ?? 0);
        $cfRows       = FieldConfig::resolveCustomFields($deptId);
        $customFieldLabels = [];
        foreach ($cfRows as $cf) { $customFieldLabels['custom_' . $cf['id']] = $cf['label']; }
        $this->render('approvals/rtc_detail', compact('rtc', 'student', 'role', 'customFieldLabels'));
    }

    // POST /rtc/{id}/approve
    public function approve(int $rtcId): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $this->requireCsrf();

        $rtc = ChangeRequest::findById($rtcId);
        if (!$rtc) {
            http_response_code(404);
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Change request not found.'];
            $this->redirect('/approvals');
            return;
        }

        $role = Auth::role();
        if ($role !== 'institution_admin' && (int)$rtc['department_id'] !== Auth::departmentId()) {
            http_response_code(403);
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Access denied.'];
            $this->redirect('/approvals');
            return;
        }

        if ($rtc['status'] !== 'pending') {
            $_SESSION['flash'] = ['type' => 'info', 'message' => 'Change request is no longer pending.'];
            $this->redirect('/approvals');
            return;
        }

        ChangeRequest::approve($rtcId, Auth::id());

        $student = Student::find((int)$rtc['student_id']);
        $name    = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Changes applied for {$name}."];
        $this->redirect('/approvals');
    }

    // POST /rtc/{id}/reject
    public function reject(int $rtcId): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $this->requireCsrf();

        $rtc = ChangeRequest::findById($rtcId);
        if (!$rtc) {
            http_response_code(404);
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Change request not found.'];
            $this->redirect('/approvals');
            return;
        }

        $role = Auth::role();
        if ($role !== 'institution_admin' && (int)$rtc['department_id'] !== Auth::departmentId()) {
            http_response_code(403);
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Access denied.'];
            $this->redirect('/approvals');
            return;
        }

        $reason = trim($_POST['rejection_reason'] ?? '');
        if (!$reason) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'A rejection reason is required.'];
            $this->redirect("/rtc/{$rtcId}");
            return;
        }

        if ($rtc['status'] !== 'pending') {
            $_SESSION['flash'] = ['type' => 'info', 'message' => 'Change request is no longer pending.'];
            $this->redirect('/approvals');
            return;
        }

        ChangeRequest::reject($rtcId, Auth::id(), $reason);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Change request rejected.'];
        $this->redirect('/approvals');
    }

    // GET /rtc/history
    public function studentHistory(): void
    {
        RoleMiddleware::handle(['student']);
        $rtcs = ChangeRequest::findByStudent(Auth::id());
        $this->render('approvals/rtc_history', compact('rtcs'));
    }
}
