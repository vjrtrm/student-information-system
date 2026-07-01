<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\MasterAuditLogger;
use App\Helpers\OnboardingValidator;
use App\Helpers\DuplicateDetector;
use App\Helpers\SpreadsheetImport;
use App\Middleware\RoleMiddleware;
use App\Middleware\DepartmentScopeMiddleware;
use App\Models\Student;
use App\Models\UploadBatch;
use App\Models\DuplicateOverrideRequest;
use App\Models\Department;
use App\Models\OptionValue;

class OnboardingController extends Controller
{
    private const STAFF_ROLES = ['staff', 'dept_admin'];
    private const UPLOAD_MAX_ROWS = 1000;
    private const UPLOAD_MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    // GET /onboarding
    public function index(): void
    {
        RoleMiddleware::handle(['staff', 'dept_admin', 'institution_admin']);
        $role = Auth::role();
        $filters = [];
        if ($role !== 'institution_admin') {
            $filters['department_id'] = Auth::departmentId();
        } elseif (!empty($_GET['department_id'])) {
            $filters['department_id'] = (int)$_GET['department_id'];
        }
        if (!empty($_GET['onboarding_status'])) $filters['onboarding_status'] = $_GET['onboarding_status'];
        if (!empty($_GET['academic_year_id']))  $filters['academic_year_id']  = (int)$_GET['academic_year_id'];
        if (!empty($_GET['search']))            $filters['search'] = $_GET['search'];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $students = Student::getList($filters, $page);
        $total = Student::countList($filters);
        $pages = max(1, (int)ceil($total / 50));

        $academicYears = OptionValue::byListKey('academic_year');
        $departments = Department::allActive();
        $pendingOverrides = 0;
        if (in_array($role, ['dept_admin', 'institution_admin'], true) && Auth::departmentId()) {
            $pendingOverrides = DuplicateOverrideRequest::countPendingByDept(Auth::departmentId());
        }

        $this->render('onboarding/index', compact(
            'students','total','page','pages','filters','academicYears','departments','pendingOverrides','role'
        ));
    }

    // GET /onboarding/template
    public function downloadTemplate(): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $masterData = [
            'academic_years' => OptionValue::byListKey('academic_year'),
            'classes'        => OptionValue::byListKey('class'),
            'sections'       => OptionValue::byListKey('section'),
        ];
        $tmpFile = SpreadsheetImport::buildOnboardingTemplate($masterData);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="student_upload_template.xlsx"');
        header('Content-Length: ' . filesize($tmpFile));
        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    // GET /onboarding/upload
    public function showUpload(): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $this->render('onboarding/upload', ['title' => 'Upload Students']);
    }

    // POST /onboarding/upload
    public function upload(): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $this->requireCsrf();

        $file = $_FILES['students_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $message = 'Please select a file to upload.';
            if ($file) {
                switch ($file['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $message = sprintf(
                            'Selected file is too large. Server upload limits are upload_max_filesize=%s and post_max_size=%s.',
                            ini_get('upload_max_filesize'),
                            ini_get('post_max_size')
                        );
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $message = 'The file upload was interrupted. Please try again.';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $message = 'Please select a file to upload.';
                        break;
                    default:
                        $message = 'File upload failed. Please try again.';
                        break;
                }
            }
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $message];
            $this->redirect('/onboarding/upload');
            return;
        }

        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($file['tmp_name']);
        if ($ext !== 'xlsx' || !in_array($mime, [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
        ], true)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Only .xlsx files are accepted.'];
            $this->redirect('/onboarding/upload');
            return;
        }
        if ($file['size'] > self::UPLOAD_MAX_BYTES) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'File must be 5 MB or smaller.'];
            $this->redirect('/onboarding/upload');
            return;
        }

        $parsed = SpreadsheetImport::parseOnboarding($file['tmp_name']);
        $allRows = $parsed['rows'];
        $parseErrors = $parsed['errors'];

        if (!empty($parseErrors)) {
            $firstError = $parseErrors[0];
            $msg = 'Could not parse uploaded file. Please use the template and verify headers.';
            if (!empty($firstError['error'])) {
                $msg .= ' ' . $firstError['error'];
            }
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $msg];
            $this->redirect('/onboarding/upload');
            return;
        }

        if (empty($allRows)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'The file contained no data rows.'];
            $this->redirect('/onboarding/upload');
            return;
        }
        if (count($allRows) > self::UPLOAD_MAX_ROWS) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'File exceeds the 1,000-row limit. Please split it.'];
            $this->redirect('/onboarding/upload');
            return;
        }

        $staffDeptId = Auth::departmentId();
        $deptInfo = Department::find($staffDeptId);

        $batchId = UploadBatch::create(
            $staffDeptId,
            Auth::id(),
            basename($file['name']),
            count($allRows)
        );

        $created = 0;
        $held    = 0;
        $failed  = 0;
        $failedRows = [];

        foreach ($allRows as $row) {
            $rowNum = $row['_row_number'] ?? '?';
            unset($row['_row_number']);

            $row['department_id'] = $staffDeptId;
            $row['programme_level'] = $deptInfo['level'] ?? 'UG';

            $row = $this->resolveOptionIds($row);

            $errors = OnboardingValidator::validate($row, $staffDeptId);
            if ($errors) {
                $failed++;
                $failedRows[] = ['row' => $rowNum, 'data' => $row, 'errors' => $errors];
                continue;
            }

            $dup = DuplicateDetector::check($row);
            if ($dup) {
                $held++;
                DuplicateOverrideRequest::create([
                    'upload_batch_id'    => $batchId,
                    'source_row_number'  => $rowNum,
                    'student_data'       => $row,
                    'flagged_reason'     => $dup['type'],
                    'existing_student_id'=> $dup['existing_student_id'],
                    'requested_by'       => Auth::id(),
                ]);
                continue;
            }

            try {
                $row['created_by']       = Auth::id();
                $row['upload_batch_id']  = $batchId;
                $row['dob'] = OnboardingValidator::toDbDate($row['dob']) ?? $row['dob'];
                $row['admission_date'] = OnboardingValidator::toDbDate($row['admission_date']) ?? $row['admission_date'];
                Student::create($row);
                $created++;
                MasterAuditLogger::log('student_created_bulk', 'student', null, ['batch' => $batchId]);
            } catch (\Throwable $e) {
                $failed++;
                $failedRows[] = ['row' => $rowNum, 'data' => $row, 'errors' => ['mobile' => 'Mobile number already exists (concurrent conflict).']];
            }
        }

        UploadBatch::updateCounts($batchId, $created, $held, $failed);
        $_SESSION['upload_failed_rows_' . $batchId] = $failedRows;

        $this->redirect('/onboarding/result/' . $batchId);
    }

    // GET /onboarding/result/{id}
    public function result(int $batchId): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $batch = UploadBatch::find($batchId);
        if (!$batch) { $this->render('errors/404', [], 404); return; }
        DepartmentScopeMiddleware::assertDepartment((int)$batch['department_id']);

        $pendingDuplicates = DuplicateOverrideRequest::findPendingByBatch($batchId);
        $failedRows = $_SESSION['upload_failed_rows_' . $batchId] ?? [];

        $this->render('onboarding/result', compact('batch','pendingDuplicates','failedRows'));
    }

    // GET /onboarding/result/{id}/errors.xlsx
    public function downloadErrors(int $batchId): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $batch = UploadBatch::find($batchId);
        if (!$batch) { $this->render('errors/404', [], 404); return; }
        DepartmentScopeMiddleware::assertDepartment((int)$batch['department_id']);

        $failedRows = $_SESSION['upload_failed_rows_' . $batchId] ?? [];
        $tmpFile = SpreadsheetImport::buildErrorReport($failedRows);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="upload_errors_batch_' . $batchId . '.xlsx"');
        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    // GET /onboarding/duplicates/{id}
    public function reviewDuplicates(int $batchId): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $batch = UploadBatch::find($batchId);
        if (!$batch) { $this->render('errors/404', [], 404); return; }
        DepartmentScopeMiddleware::assertDepartment((int)$batch['department_id']);

        $duplicates = DuplicateOverrideRequest::findPendingByBatch($batchId);
        $this->render('onboarding/duplicates', compact('batch', 'duplicates'));
    }

    // POST /onboarding/duplicates/{id}
    public function resolveDuplicates(int $batchId): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $this->requireCsrf();
        $batch = UploadBatch::find($batchId);
        if (!$batch) { $this->render('errors/404', [], 404); return; }
        DepartmentScopeMiddleware::assertDepartment((int)$batch['department_id']);

        $resolutions = $_POST['resolution'] ?? [];
        foreach ($resolutions as $overrideId => $action) {
            $overrideId = (int)$overrideId;
            $req = DuplicateOverrideRequest::find($overrideId);
            if (!$req || $req['status'] !== 'pending') continue;

            if ($action === 'skip') {
                DuplicateOverrideRequest::updateReasonAndStatus($overrideId, 'Skipped by staff.', 'rejected');
                MasterAuditLogger::log('override_skipped', 'duplicate_override', $overrideId, []);
            } elseif ($action === 'override') {
                $reason = trim($_POST['reason'][$overrideId] ?? '');
                if ($reason === '') continue;
                DuplicateOverrideRequest::updateReasonAndStatus($overrideId, $reason, 'pending');
                MasterAuditLogger::log('override_requested', 'duplicate_override', $overrideId, ['reason' => $reason]);
            }
        }
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Duplicate decisions saved.'];
        $this->redirect('/onboarding/result/' . $batchId);
    }

    // GET /onboarding/overrides
    public function pendingOverrides(): void
    {
        RoleMiddleware::handle(['dept_admin']);
        $deptId = Auth::departmentId();
        $overrides = DuplicateOverrideRequest::findPendingByDept($deptId);
        $this->render('onboarding/override_review', compact('overrides'));
    }

    // POST /onboarding/overrides/{id}/approve
    public function approveOverride(int $id): void
    {
        RoleMiddleware::handle(['dept_admin']);
        $this->requireCsrf();
        $req = DuplicateOverrideRequest::find($id);
        if (!$req || $req['status'] !== 'pending') {
            $this->redirect('/onboarding/overrides');
            return;
        }

        $studentData = json_decode($req['student_data'], true);
        $studentData['created_by'] = Auth::id();
        $studentData['upload_batch_id'] = $req['upload_batch_id'];
        $studentData['dob'] = OnboardingValidator::toDbDate($studentData['dob'] ?? '') ?? $studentData['dob'];
        $studentData['admission_date'] = OnboardingValidator::toDbDate($studentData['admission_date'] ?? '') ?? $studentData['admission_date'];

        try {
            $newId = Student::create($studentData);
            DuplicateOverrideRequest::approve($id, Auth::id());
            if ($req['upload_batch_id']) {
                UploadBatch::incrementCreated((int)$req['upload_batch_id']);
            }
            MasterAuditLogger::log('override_approved', 'student', $newId, ['override_request_id' => $id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Student record created.'];
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Could not create student: ' . $e->getMessage()];
        }
        $this->redirect('/onboarding/overrides');
    }

    // POST /onboarding/overrides/{id}/reject
    public function rejectOverride(int $id): void
    {
        RoleMiddleware::handle(['dept_admin']);
        $this->requireCsrf();
        $req = DuplicateOverrideRequest::find($id);
        if ($req && $req['status'] === 'pending') {
            DuplicateOverrideRequest::reject($id, Auth::id());
            MasterAuditLogger::log('override_rejected', 'duplicate_override', $id, []);
        }
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Override request rejected.'];
        $this->redirect('/onboarding/overrides');
    }

    // GET /onboarding/add
    public function showAdd(): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $this->render('onboarding/add', $this->getFormData());
    }

    // POST /onboarding/add
    public function store(): void
    {
        RoleMiddleware::handle(self::STAFF_ROLES);
        $this->requireCsrf();

        $data = [
            'first_name'      => $this->input('first_name'),
            'last_name'       => $this->input('last_name'),
            'dob'             => $this->input('dob'),
            'mobile'          => $this->input('mobile'),
            'gender'          => $this->input('gender'),
            'department_id'   => Auth::departmentId(),
            'programme_level' => Department::find(Auth::departmentId())['level'] ?? 'UG',
            'academic_year_id'=> (int)$this->input('academic_year_id', 0),
            'class_id'        => (int)$this->input('class_id', 0),
            'section_id'      => (int)$this->input('section_id', 0) ?: null,
            'admission_date'  => $this->input('admission_date'),
        ];

        $staffDeptId = Auth::departmentId();
        $errors = OnboardingValidator::validate($data, $staffDeptId);
        if ($errors) {
            $this->render('onboarding/add', array_merge($this->getFormData(), ['errors' => $errors, 'old' => $data]));
            return;
        }

        $dup = DuplicateDetector::check($data);
        if ($dup) {
            $overrideChosen = $this->input('override') === '1';
            $reasonNote = trim($this->input('reason_note', ''));
            if ($overrideChosen && $reasonNote !== '') {
                $newReqId = DuplicateOverrideRequest::create([
                    'upload_batch_id'     => null,
                    'source_row_number'   => null,
                    'student_data'        => $data,
                    'flagged_reason'      => $dup['type'],
                    'existing_student_id' => $dup['existing_student_id'],
                    'requested_by'        => Auth::id(),
                ]);
                DuplicateOverrideRequest::updateReasonAndStatus($newReqId, $reasonNote, 'pending');
                MasterAuditLogger::log('override_requested_single_add', 'duplicate_override', $newReqId, ['reason' => $reasonNote]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Override request submitted for Department Admin review.'];
                $this->redirect('/onboarding');
                return;
            }
            $formData = $this->getFormData();
            $formData['dupWarning'] = $dup;
            $dobForLookup = OnboardingValidator::toDbDate($data['dob'] ?? '') ?? ($data['dob'] ?? '');
            $formData['dupExisting'] = Student::findAnyByMobile($data['mobile'] ?? '')
                ?? Student::findByNameDob($data['first_name'] ?? '', $data['last_name'] ?? '', $dobForLookup);
            $formData['old'] = $data;
            $this->render('onboarding/add', $formData);
            return;
        }

        try {
            $data['dob'] = OnboardingValidator::toDbDate($data['dob']) ?? $data['dob'];
            $data['admission_date'] = OnboardingValidator::toDbDate($data['admission_date']) ?? $data['admission_date'];
            $data['created_by'] = Auth::id();
            $data['upload_batch_id'] = null;
            $newId = Student::create($data);
            MasterAuditLogger::log('student_created', 'student', $newId, []);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Student record created successfully.'];
            $this->redirect('/onboarding');
        } catch (\Throwable $e) {
            $errors = ['mobile' => 'Mobile number already exists.'];
            $this->render('onboarding/add', array_merge($this->getFormData(), ['errors' => $errors, 'old' => $data]));
        }
    }

    // GET /onboarding/summary (institution_admin only)
    public function summary(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $academicYearId = (int)($_GET['academic_year_id'] ?? 0) ?: null;
        $raw = Student::summaryByDept($academicYearId);
        $academicYears = OptionValue::byListKey('academic_year');

        $byDept = [];
        foreach ($raw as $row) {
            $deptId = $row['dept_id'];
            if (!isset($byDept[$deptId])) {
                $byDept[$deptId] = ['dept_name' => $row['dept_name'], 'statuses' => []];
            }
            $byDept[$deptId]['statuses'][$row['onboarding_status']] = (int)$row['cnt'];
        }
        $this->render('onboarding/summary', compact('byDept','academicYears','academicYearId'));
    }

    // ── Private helpers ──────────────────────────────────────────────────

    private function getFormData(): array
    {
        return [
            'title'        => 'Add Student',
            'academicYears'=> OptionValue::byListKey('academic_year'),
            'classes'      => OptionValue::byListKey('class'),
            'sections'     => OptionValue::byListKey('section'),
            'errors'       => [],
            'old'          => [],
        ];
    }

    private function resolveOptionIds(array $row): array
    {
        $maps = [
            'academic_year' => 'academic_year_id',
            'class'         => 'class_id',
            'section'       => 'section_id',
        ];
        foreach ($maps as $textKey => $idKey) {
            if (isset($row[$textKey]) && !isset($row[$idKey])) {
                $values = OptionValue::byListKey($textKey);
                foreach ($values as $v) {
                    if (strcasecmp($v['value'], $row[$textKey]) === 0 || strcasecmp($v['display'], $row[$textKey]) === 0) {
                        $row[$idKey] = (int)$v['id'];
                        break;
                    }
                }
                unset($row[$textKey]);
            }
        }
        return $row;
    }
}
