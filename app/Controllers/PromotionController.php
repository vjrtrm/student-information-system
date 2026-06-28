<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\MasterAuditLogger;
use App\Helpers\View;
use App\Middleware\RoleMiddleware;
use App\Models\PromotionBatch;

class PromotionController extends Controller
{
    // GET /promotion
    public function index(): void
    {
        RoleMiddleware::handle(['staff', 'dept_admin', 'institution_admin']);
        $deptId  = Auth::role() === 'institution_admin' ? 0 : (int) Auth::departmentId();
        $batches = PromotionBatch::findAll($deptId);
        $windowOpen = PromotionBatch::isWindowOpen();
        $title = 'Student Promotion';
        ob_start();
        require dirname(__DIR__) . '/Views/promotion/index.php';
        $content = ob_get_clean();
        require dirname(__DIR__) . '/Views/layouts/app.php';
    }

    // GET /promotion/create
    public function createForm(): void
    {
        RoleMiddleware::handle(['staff']);
        if (!PromotionBatch::isWindowOpen()) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Promotion window is currently closed.'];
            View::redirect('/promotion');
        }
        if (PromotionBatch::findPendingForDept((int) Auth::departmentId())) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'A promotion batch is already pending approval for this department.'];
            View::redirect('/promotion');
        }
        $students      = $this->loadAllStudentsForDept((int) Auth::departmentId());
        $academicYears = $this->loadOptionValues('academic_year');
        $classes       = $this->loadOptionValues('class');
        $sections      = $this->loadOptionValues('section');
        $mode          = 'create';
        $batch         = null;
        $included      = [];
        $exclusions    = [];
        $title = 'Create Promotion Batch';
        ob_start();
        require dirname(__DIR__) . '/Views/promotion/form.php';
        $content = ob_get_clean();
        require dirname(__DIR__) . '/Views/layouts/app.php';
    }

    // POST /promotion/create
    public function store(): void
    {
        RoleMiddleware::handle(['staff']);
        $this->requireCsrf();
        if (!PromotionBatch::isWindowOpen()) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Promotion window is currently closed.'];
            View::redirect('/promotion');
        }
        if (PromotionBatch::findPendingForDept((int) Auth::departmentId())) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'A promotion batch is already pending approval for this department.'];
            View::redirect('/promotion');
        }
        $errors = $this->validateTargetValues($_POST);
        if ($errors) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
            View::redirect('/promotion/create');
        }
        $students   = $this->loadAllStudentsForDept((int) Auth::departmentId());
        [$includedIds, $exclusions, $exErrors] = $this->parseInclusionsAndExclusions($_POST, $students);
        if ($exErrors) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => implode(' ', $exErrors)];
            View::redirect('/promotion/create');
        }
        $batchId = PromotionBatch::create([
            'department_id'           => (int) Auth::departmentId(),
            'target_academic_year_id' => (int) $_POST['target_academic_year_id'],
            'target_class_id'         => (int) $_POST['target_class_id'],
            'target_section_id'       => (int) $_POST['target_section_id'],
            'status'                  => 'pending_approval',
            'requires_inst_admin'     => 0,
            'initiated_by'            => (int) Auth::userId(),
        ]);
        foreach ($includedIds as $sid) {
            Db::execute(
                'INSERT INTO promotion_batch_students (batch_id, student_id) VALUES (?, ?)',
                [$batchId, $sid]
            );
        }
        foreach ($exclusions as $sid => $reason) {
            Db::execute(
                'INSERT INTO promotion_exclusions (batch_id, student_id, reason) VALUES (?, ?, ?)',
                [$batchId, $sid, $reason]
            );
        }
        MasterAuditLogger::log('create', 'promotion_batch', $batchId, [
            'dept_id'  => Auth::departmentId(),
            'included' => count($includedIds),
            'excluded' => count($exclusions),
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Promotion batch submitted for approval.'];
        View::redirect('/promotion/' . $batchId);
    }

    // GET /promotion/{id}
    public function detail(int $id): void
    {
        RoleMiddleware::handle(['staff', 'dept_admin', 'institution_admin']);
        $batch = $this->loadAndScope($id);
        $included   = PromotionBatch::getIncluded($id);
        $excluded   = PromotionBatch::getExcluded($id);
        $title = 'Promotion Batch #' . $id;
        ob_start();
        require dirname(__DIR__) . '/Views/promotion/detail.php';
        $content = ob_get_clean();
        require dirname(__DIR__) . '/Views/layouts/app.php';
    }

    // GET /promotion/{id}/edit
    public function editForm(int $id): void
    {
        RoleMiddleware::handle(['staff']);
        $batch = $this->loadAndScope($id, ['staff']);
        if ($batch['status'] !== 'rejected') {
            http_response_code(403); echo 'Forbidden — only rejected batches can be edited.'; exit;
        }
        $students      = $this->loadAllStudentsForDept((int) $batch['department_id']);
        $academicYears = $this->loadOptionValues('academic_year');
        $classes       = $this->loadOptionValues('class');
        $sections      = $this->loadOptionValues('section');
        // Pre-populate inclusions/exclusions from existing batch
        $includedRows = PromotionBatch::getIncluded($id);
        $included     = array_column($includedRows, 'id');
        $excludedRows = PromotionBatch::getExcluded($id);
        $exclusions   = array_column($excludedRows, 'reason', 'id');
        $mode         = 'edit';
        $title = 'Edit Promotion Batch #' . $id;
        ob_start();
        require dirname(__DIR__) . '/Views/promotion/form.php';
        $content = ob_get_clean();
        require dirname(__DIR__) . '/Views/layouts/app.php';
    }

    // POST /promotion/{id}/edit  (resubmit)
    public function update(int $id): void
    {
        RoleMiddleware::handle(['staff']);
        $this->requireCsrf();
        $batch = $this->loadAndScope($id, ['staff']);
        if ($batch['status'] !== 'rejected') {
            http_response_code(403); echo 'Forbidden'; exit;
        }
        $errors = $this->validateTargetValues($_POST);
        if ($errors) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
            View::redirect('/promotion/' . $id . '/edit');
        }
        $students   = $this->loadAllStudentsForDept((int) $batch['department_id']);
        [$includedIds, $exclusions, $exErrors] = $this->parseInclusionsAndExclusions($_POST, $students);
        if ($exErrors) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => implode(' ', $exErrors)];
            View::redirect('/promotion/' . $id . '/edit');
        }
        // Clear and re-insert
        Db::execute('DELETE FROM promotion_batch_students WHERE batch_id = ?', [$id]);
        Db::execute('DELETE FROM promotion_exclusions WHERE batch_id = ?', [$id]);
        PromotionBatch::update($id, [
            'target_academic_year_id' => (int) $_POST['target_academic_year_id'],
            'target_class_id'         => (int) $_POST['target_class_id'],
            'target_section_id'       => (int) $_POST['target_section_id'],
            'status'                  => 'pending_approval',
            'requires_inst_admin'     => 1,
            'rejection_reason'        => null,
            'reviewed_by'             => null,
            'reviewed_at'             => null,
        ]);
        foreach ($includedIds as $sid) {
            Db::execute('INSERT INTO promotion_batch_students (batch_id, student_id) VALUES (?, ?)', [$id, $sid]);
        }
        foreach ($exclusions as $sid => $reason) {
            Db::execute('INSERT INTO promotion_exclusions (batch_id, student_id, reason) VALUES (?, ?, ?)', [$id, $sid, $reason]);
        }
        MasterAuditLogger::log('resubmit', 'promotion_batch', $id, [
            'included' => count($includedIds),
            'excluded' => count($exclusions),
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Batch resubmitted for Institution Admin approval.'];
        View::redirect('/promotion/' . $id);
    }

    // POST /promotion/{id}/approve
    public function approve(int $id): void
    {
        RoleMiddleware::handle(['dept_admin', 'institution_admin']);
        $this->requireCsrf();
        $batch = $this->loadAndScope($id, ['dept_admin', 'institution_admin']);
        // Resubmissions require institution_admin
        if ((int) $batch['requires_inst_admin'] === 1 && Auth::role() !== 'institution_admin') {
            http_response_code(403); echo 'Forbidden — resubmissions require Institution Admin approval.'; exit;
        }
        if ($batch['status'] !== 'pending_approval') {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'This batch has already been processed.'];
            View::redirect('/promotion/' . $id);
        }
        $includedCount = count(PromotionBatch::getIncluded($id));
        $excludedCount = count(PromotionBatch::getExcluded($id));
        PromotionBatch::execute($id, (int) Auth::userId());
        MasterAuditLogger::log('approve', 'promotion_batch', $id, [
            'included' => $includedCount,
            'excluded' => $excludedCount,
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Promotion approved. {$includedCount} student(s) promoted, {$excludedCount} student(s) detained."];
        View::redirect('/promotion/' . $id);
    }

    // POST /promotion/{id}/reject
    public function reject(int $id): void
    {
        RoleMiddleware::handle(['dept_admin', 'institution_admin']);
        $this->requireCsrf();
        $batch = $this->loadAndScope($id, ['dept_admin', 'institution_admin']);
        if ($batch['status'] !== 'pending_approval') {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'This batch has already been processed.'];
            View::redirect('/promotion/' . $id);
        }
        $reason = trim($_POST['rejection_reason'] ?? '');
        if ($reason === '') {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Rejection reason is required.'];
            View::redirect('/promotion/' . $id);
        }
        $now = date('Y-m-d H:i:s');
        PromotionBatch::update($id, [
            'target_academic_year_id' => $batch['target_academic_year_id'],
            'target_class_id'         => $batch['target_class_id'],
            'target_section_id'       => $batch['target_section_id'],
            'status'                  => 'rejected',
            'requires_inst_admin'     => $batch['requires_inst_admin'],
            'rejection_reason'        => $reason,
            'reviewed_by'             => Auth::userId(),
            'reviewed_at'             => $now,
        ]);
        MasterAuditLogger::log('reject', 'promotion_batch', $id, ['reason_length' => strlen($reason)]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Promotion batch rejected.'];
        View::redirect('/promotion/' . $id);
    }

    // POST /promotion/window/toggle
    public function toggleWindow(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();
        $current = PromotionBatch::isWindowOpen() ? '1' : '0';
        $new     = $current === '1' ? '0' : '1';
        Db::execute("REPLACE INTO settings (`key`, value) VALUES ('promotion_window_open', ?)", [$new]);
        MasterAuditLogger::log('toggle_window', 'promotion_window', null, [
            'new_state' => $new === '1' ? 'open' : 'closed',
        ]);
        $msg = $new === '1' ? 'Promotion window is now open.' : 'Promotion window is now closed.';
        $_SESSION['flash'] = ['type' => 'success', 'message' => $msg];
        View::redirect('/promotion');
    }

    // ---- Private helpers ----

    private function loadAndScope(int $id, array $roles = ['staff', 'dept_admin', 'institution_admin']): array
    {
        $batch = PromotionBatch::findById($id);
        if (!$batch) {
            http_response_code(404); echo 'Not found'; exit;
        }
        if (Auth::role() !== 'institution_admin' && (int) $batch['department_id'] !== (int) Auth::departmentId()) {
            http_response_code(403); echo 'Forbidden'; exit;
        }
        return $batch;
    }

    private function loadAllStudentsForDept(int $deptId): array
    {
        $rows = Db::selectAll(
            "SELECT s.id, s.first_name, s.last_name, s.enrolment_number, s.enrolment_serial,
                    s.onboarding_status, s.enrolment_approval_status,
                    s.academic_year_id,
                    ov.display AS academic_year_label,
                    sp.form_status
             FROM students s
             LEFT JOIN student_profiles sp ON sp.student_id = s.id
             LEFT JOIN option_values ov ON ov.id = s.academic_year_id
             WHERE s.department_id = ?
             ORDER BY s.first_name, s.last_name",
            [$deptId]
        );
        foreach ($rows as &$row) {
            $row['eligible'] = $row['onboarding_status'] === 'active'
                && $row['enrolment_approval_status'] === 'approved';
            $row['ineligible_reason'] = '';
            if ($row['onboarding_status'] !== 'active') {
                $row['ineligible_reason'] = 'Status: ' . $row['onboarding_status'];
            } elseif ($row['enrolment_approval_status'] !== 'approved') {
                $row['ineligible_reason'] = 'Enrolment not approved';
            }
        }
        unset($row);
        return $rows;
    }

    private function loadOptionValues(string $listKey): array
    {
        return Db::selectAll(
            "SELECT ov.id, ov.display, ov.value FROM option_values ov
             JOIN option_lists ol ON ol.id = ov.list_id
             WHERE ol.list_key = ? AND ov.status = 'active'
             ORDER BY ov.sort_order, ov.display",
            [$listKey]
        );
    }

    private function validateTargetValues(array $post): array
    {
        $errors = [];
        if (empty($post['target_academic_year_id'])) {
            $errors[] = 'Target academic year is required.';
        }
        if (empty($post['target_class_id'])) {
            $errors[] = 'Target class is required.';
        }
        if (empty($post['target_section_id'])) {
            $errors[] = 'Target section is required.';
        }
        return $errors;
    }

    private function parseInclusionsAndExclusions(array $post, array $students): array
    {
        $includedPost = $post['included'] ?? [];   // array of student_ids checked
        $reasonsPost  = $post['exclusion_reason'] ?? [];  // [student_id => reason]

        $includedIds = [];
        $exclusions  = [];
        $errors      = [];

        foreach ($students as $s) {
            if (!$s['eligible']) continue;  // ineligible students never included
            $sid = $s['id'];
            if (in_array((string)$sid, array_map('strval', (array)$includedPost), true)) {
                $includedIds[] = $sid;
            } else {
                // excluded — reason required
                $reason = trim($reasonsPost[$sid] ?? '');
                if ($reason === '') {
                    $errors[] = "Exclusion reason required for {$s['first_name']} {$s['last_name']}.";
                } else {
                    $exclusions[$sid] = $reason;
                }
            }
        }
        return [$includedIds, $exclusions, $errors];
    }
}
