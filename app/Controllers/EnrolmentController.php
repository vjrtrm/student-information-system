<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\MasterAuditLogger;
use App\Helpers\EnrolmentNumberGenerator;
use App\Middleware\RoleMiddleware;
use App\Middleware\DepartmentScopeMiddleware;
use App\Models\EnrolmentBatch;
use App\Models\Student;
use App\Models\Department;
use App\Models\OptionValue;

class EnrolmentController extends Controller
{
    // GET /enrolment
    public function index(): void
    {
        RoleMiddleware::handle(['staff', 'dept_admin', 'institution_admin']);
        $role    = Auth::role();
        $filters = [];
        if (!empty($_GET['academic_year_id'])) $filters['academic_year_id'] = (int)$_GET['academic_year_id'];

        if ($role === 'institution_admin') {
            if (!empty($_GET['department_id'])) $filters['department_id'] = (int)$_GET['department_id'];
            $batches = EnrolmentBatch::findAll($filters);
        } else {
            $batches = EnrolmentBatch::findByDept(Auth::departmentId(), $filters);
        }

        // Annotate each batch with derived status
        foreach ($batches as &$b) {
            $b['derived_status'] = EnrolmentBatch::deriveStatus((int)$b['id']);
        }
        unset($b);

        $academicYears = OptionValue::byListKey('academic_year');
        $departments   = ($role === 'institution_admin') ? Department::allActive() : [];
        $this->render('enrolment/index', compact('batches', 'academicYears', 'departments', 'role'));
    }

    // GET /enrolment/generate
    public function generateForm(): void
    {
        RoleMiddleware::handle(['staff', 'dept_admin']);
        DepartmentScopeMiddleware::handle();
        $academicYears = OptionValue::byListKey('academic_year');
        $this->render('enrolment/generate', compact('academicYears'));
    }

    // POST /enrolment/generate
    public function generate(): void
    {
        RoleMiddleware::handle(['staff', 'dept_admin']);
        $this->requireCsrf();
        DepartmentScopeMiddleware::handle();

        $ayId   = (int)$this->input('academic_year_id', 0);
        $deptId = Auth::departmentId();

        if (!$ayId) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please select an Academic Year.'];
            $this->redirect('/enrolment/generate');
            return;
        }

        try {
            $batchId = EnrolmentNumberGenerator::generate($deptId, $ayId, Auth::id());
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Enrolment numbers generated successfully.'];
            $this->redirect('/enrolment/batch/' . $batchId);
        } catch (\RuntimeException $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
            $this->redirect('/enrolment/generate');
        }
    }

    // GET /enrolment/batch/{id}
    public function batchDetail(int $batchId): void
    {
        RoleMiddleware::handle(['staff', 'dept_admin', 'institution_admin']);
        $batch = EnrolmentBatch::find($batchId);
        if (!$batch) { $this->render('errors/404', [], 404); return; }
        DepartmentScopeMiddleware::assertDepartment((int)$batch['department_id']);

        $filters = [];
        if (!empty($_GET['search']))                    $filters['search'] = $_GET['search'];
        if (!empty($_GET['enrolment_approval_status'])) $filters['enrolment_approval_status'] = $_GET['enrolment_approval_status'];

        $page     = max(1, (int)($_GET['page'] ?? 1));
        $students = Student::findByBatch($batchId, $filters, $page);
        $total    = Student::countByBatch($batchId, $filters);
        $pages    = max(1, (int)ceil($total / 50));
        $status   = EnrolmentBatch::deriveStatus($batchId);
        $role     = Auth::role();

        $this->render('enrolment/batch', compact(
            'batch', 'students', 'total', 'page', 'pages', 'filters', 'status', 'role'
        ));
    }

    // POST /enrolment/batch/{id}/approve-all
    public function approveAll(int $batchId): void
    {
        RoleMiddleware::handle(['dept_admin', 'institution_admin']);
        $this->requireCsrf();
        $batch = EnrolmentBatch::find($batchId);
        if (!$batch) { $this->render('errors/404', [], 404); return; }
        DepartmentScopeMiddleware::assertDepartment((int)$batch['department_id']);

        // Fetch all pending student IDs in this batch
        $pendingStudents = Student::findByBatch($batchId, ['enrolment_approval_status' => 'pending'], 1, 10000);
        $ids = array_map(fn($s) => (int)$s['id'], $pendingStudents);

        if (empty($ids)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No pending enrolment numbers to approve.'];
            $this->redirect('/enrolment/batch/' . $batchId);
            return;
        }

        $pdo = Db::conn();
        $pdo->beginTransaction();
        try {
            $count = Student::approveNumbers($ids, $batchId, Auth::id());
            $pdo->commit();
            MasterAuditLogger::log('enrolment_bulk_approved', 'enrolment_batch', $batchId, [
                'count'    => $count,
                'actor_id' => Auth::id(),
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "{$count} enrolment numbers approved. Students can now view their enrolment numbers."];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Approval failed: ' . $e->getMessage()];
        }
        $this->redirect('/enrolment/batch/' . $batchId);
    }

    // POST /enrolment/batch/{id}/approve-selected
    public function approveSelected(int $batchId): void
    {
        RoleMiddleware::handle(['dept_admin', 'institution_admin']);
        $this->requireCsrf();
        $batch = EnrolmentBatch::find($batchId);
        if (!$batch) { $this->render('errors/404', [], 404); return; }
        DepartmentScopeMiddleware::assertDepartment((int)$batch['department_id']);

        $rawIds = $_POST['student_ids'] ?? [];
        $ids    = array_values(array_filter(
            array_unique(array_map('intval', (array)$rawIds)),
            fn($id) => $id > 0
        ));

        if (empty($ids)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please select at least one student to approve.'];
            $this->redirect('/enrolment/batch/' . $batchId);
            return;
        }

        $pdo = Db::conn();
        $pdo->beginTransaction();
        try {
            $count = Student::approveNumbers($ids, $batchId, Auth::id());
            $pdo->commit();
            MasterAuditLogger::log('enrolment_selected_approved', 'enrolment_batch', $batchId, [
                'count'       => $count,
                'student_ids' => $ids,
                'actor_id'    => Auth::id(),
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "{$count} enrolment number(s) approved."];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Approval failed: ' . $e->getMessage()];
        }
        $this->redirect('/enrolment/batch/' . $batchId);
    }

    // GET /enrolment/eligible-count  (JSON, auth required)
    public function eligibleCount(): void
    {
        RoleMiddleware::handle(['staff', 'dept_admin']);
        $deptId = Auth::departmentId();
        $ayId   = (int)($_GET['ay_id'] ?? 0);
        $count  = $ayId ? count(Student::findPendingForGeneration($deptId, $ayId)) : 0;
        header('Content-Type: application/json');
        echo json_encode(['count' => $count]);
        exit;
    }

    // GET /enrolment/summary  (institution_admin only)
    public function summary(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $ayId          = (int)($_GET['academic_year_id'] ?? 0) ?: null;
        $summary       = EnrolmentBatch::summaryByDept($ayId);
        $academicYears = OptionValue::byListKey('academic_year');
        $this->render('enrolment/summary', compact('summary', 'academicYears', 'ayId'));
    }
}
