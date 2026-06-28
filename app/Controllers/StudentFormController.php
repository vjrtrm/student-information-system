<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\FieldConfig;
use App\Helpers\FormFieldRules;
use App\Helpers\DocumentUploadHandler;
use App\Helpers\UploadException;
use App\Helpers\MasterAuditLogger;
use App\Middleware\RoleMiddleware;
use App\Middleware\DepartmentScopeMiddleware;
use App\Models\Student;
use App\Models\StudentProfile;
use App\Models\OptionValue;

class StudentFormController extends Controller
{
    // GET /student/form
    public function show(): void
    {
        RoleMiddleware::handle(['student']);
        $studentId = Auth::id();
        $student   = Student::find($studentId);
        $profile   = StudentProfile::findByStudent($studentId) ?? [];

        if (($profile['form_status'] ?? 'incomplete') === 'submitted') {
            $this->redirect('/student/form/view');
            return;
        }

        $rules     = FormFieldRules::getApplicableFields($profile, $student);
        $summary   = StudentProfile::getCompletionSummary($profile, $student);
        $dropdowns = $this->loadDropdowns();

        $deptId      = (int)($student['department_id'] ?? 0);
        $fieldConfig = FieldConfig::resolve($deptId);
        $customFields = FieldConfig::resolveCustomFields($deptId);
        $customData  = [];
        $cdRows      = Db::selectAll('SELECT custom_field_id, value FROM student_custom_data WHERE student_id = ?', [$studentId]);
        foreach ($cdRows as $row) {
            $customData[(int)$row['custom_field_id']] = $row['value'];
        }

        $this->render('student-form/show', array_merge(compact(
            'student', 'profile', 'rules', 'summary', 'fieldConfig', 'customFields', 'customData'
        ), $dropdowns));
    }

    // POST /student/form/save
    public function save(): void
    {
        RoleMiddleware::handle(['student']);
        $this->requireCsrf();
        $studentId = Auth::id();
        $student   = Student::find($studentId);
        $profile   = StudentProfile::findByStudent($studentId) ?? [];

        if (($profile['form_status'] ?? 'incomplete') === 'submitted') {
            http_response_code(403);
            $_SESSION['flash'] = ['type'=>'info','message'=>'Your form has been submitted. Use Request a Change to make edits.'];
            $this->redirect('/student/form/view');
            return;
        }

        $data         = $this->sanitiseInput($_POST, $student, $profile);
        $uploadErrors = [];

        // Process file uploads
        $rules      = FormFieldRules::getApplicableFields(array_merge($profile, $data), $student);
        $fileFields = array_filter($rules, fn($f) => in_array($f['type'], ['file','photo'], true));

        foreach ($fileFields as $field) {
            $key = $field['key'];
            if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) continue;
            try {
                $photoOnly    = ($field['type'] === 'photo');
                $existingPath = $profile[$key] ?? null;
                $data[$key]   = DocumentUploadHandler::handle($key, $_FILES[$key], $studentId, $photoOnly, $existingPath);
            } catch (UploadException $e) {
                $uploadErrors[] = $field['label'] . ': ' . $e->getMessage();
            }
        }

        // Handle comm_same_as_perm copy
        if (!empty($data['comm_same_as_perm'])) {
            $data['comm_address1']    = $data['perm_address1']    ?? ($profile['perm_address1']    ?? null);
            $data['comm_address2']    = $data['perm_address2']    ?? ($profile['perm_address2']    ?? null);
            $data['comm_city']        = $data['perm_city']        ?? ($profile['perm_city']        ?? null);
            $data['comm_taluk_id']    = $data['perm_taluk_id']    ?? ($profile['perm_taluk_id']    ?? null);
            $data['comm_district_id'] = $data['perm_district_id'] ?? ($profile['perm_district_id'] ?? null);
            $data['comm_state_id']    = $data['perm_state_id']    ?? ($profile['perm_state_id']    ?? null);
            $data['comm_pincode']     = $data['perm_pincode']     ?? ($profile['perm_pincode']     ?? null);
        }

        StudentProfile::upsert($studentId, $data);

        // Save custom field values
        $deptId      = (int)($student['department_id'] ?? 0);
        $fieldConfig = FieldConfig::resolve($deptId);
        $customFields = FieldConfig::resolveCustomFields($deptId);
        $now         = date('Y-m-d H:i:s');
        foreach ($customFields as $cf) {
            $cfKey  = 'custom_' . $cf['id'];
            $cfMode = $fieldConfig[$cfKey] ?? $cf['mode'];
            if ($cfMode === 'hidden') continue;
            $value = trim($_POST[$cfKey] ?? '');
            Db::execute(
                'REPLACE INTO student_custom_data (student_id, custom_field_id, value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                [$studentId, (int)$cf['id'], $value, $now, $now]
            );
        }

        // Recompute completion
        $updatedProfile = StudentProfile::findByStudent($studentId) ?? [];
        $updatedRules   = FormFieldRules::getApplicableFields($updatedProfile, $student);
        $pct            = FormFieldRules::computeCompletion($updatedProfile, $updatedRules);

        // Factor in required custom fields
        $customTotal  = 0;
        $customFilled = 0;
        $cdRows       = Db::selectAll('SELECT custom_field_id, value FROM student_custom_data WHERE student_id = ?', [$studentId]);
        $customData   = [];
        foreach ($cdRows as $row) { $customData[(int)$row['custom_field_id']] = $row['value']; }
        foreach ($customFields as $cf) {
            $cfKey  = 'custom_' . $cf['id'];
            $cfMode = $fieldConfig[$cfKey] ?? $cf['mode'];
            if ($cfMode !== 'required') continue;
            $customTotal++;
            if (isset($customData[(int)$cf['id']]) && trim($customData[(int)$cf['id']]) !== '') {
                $customFilled++;
            }
        }
        if ($customTotal > 0) {
            // Blend custom required fields into completion pct
            $builtinRequired = count(array_filter(FormFieldRules::getApplicableFields($updatedProfile, $student), fn($f) => $f['required'] && $f['visible']));
            $totalRequired   = $builtinRequired + $customTotal;
            $builtinFilled   = (int)round(($pct / 100) * $builtinRequired);
            $totalFilled     = $builtinFilled + $customFilled;
            $pct = $totalRequired > 0 ? (int)floor(($totalFilled / $totalRequired) * 100) : $pct;
        }

        StudentProfile::updateCompletion($studentId, $pct);

        if ($uploadErrors) {
            $errMsg = implode('; ', $uploadErrors) . '. Other changes have been saved.';
            $_SESSION['flash'] = ['type'=>'danger','message'=>$errMsg];
        } else {
            $_SESSION['flash'] = ['type'=>'success','message'=>"Progress saved ({$pct}% complete)."];
        }
        $this->redirect('/student/form');
    }

    // POST /student/form/submit
    public function submit(): void
    {
        RoleMiddleware::handle(['student']);
        $this->requireCsrf();
        $studentId = Auth::id();
        $student   = Student::find($studentId);
        $profile   = StudentProfile::findByStudent($studentId) ?? [];

        if (($profile['form_status'] ?? 'incomplete') === 'submitted') {
            $_SESSION['flash'] = ['type'=>'info','message'=>'Your form has been submitted. Use Request a Change to make edits.'];
            $this->redirect('/student/form/view');
            return;
        }

        $rules   = FormFieldRules::getApplicableFields($profile, $student);
        $summary = StudentProfile::getCompletionSummary($profile, $student);

        // Validate required custom fields
        $deptId       = (int)($student['department_id'] ?? 0);
        $fieldConfig  = FieldConfig::resolve($deptId);
        $customFields = FieldConfig::resolveCustomFields($deptId);
        $cdRows       = Db::selectAll('SELECT custom_field_id, value FROM student_custom_data WHERE student_id = ?', [$studentId]);
        $customData   = [];
        foreach ($cdRows as $row) { $customData[(int)$row['custom_field_id']] = $row['value']; }
        $customMissing = [];
        foreach ($customFields as $cf) {
            $cfKey  = 'custom_' . $cf['id'];
            $cfMode = $fieldConfig[$cfKey] ?? $cf['mode'];
            if ($cfMode === 'required') {
                $val = isset($customData[(int)$cf['id']]) ? trim($customData[(int)$cf['id']]) : '';
                if ($val === '') {
                    $customMissing[] = $cf['label'];
                }
            }
        }

        $missing = array_merge(array_values($summary['missing']), $customMissing);
        if (!empty($missing)) {
            $list = implode(', ', $missing);
            $_SESSION['flash'] = ['type'=>'danger','message'=>"Please complete the following required fields: {$list}."];
            $this->redirect('/student/form');
            return;
        }

        StudentProfile::submit($studentId);

        $_SESSION['flash'] = ['type'=>'success','message'=>'Your form has been submitted successfully.'];
        $this->redirect('/student/form/view');
    }

    // GET /student/form/view  (student read-only)
    public function view(): void
    {
        RoleMiddleware::handle(['student']);
        $studentId    = Auth::id();
        $student      = Student::find($studentId);
        $profile      = StudentProfile::findByStudent($studentId) ?? [];
        $isStaff      = false;
        $deptId       = (int)($student['department_id'] ?? 0);
        $customFields = FieldConfig::resolveCustomFields($deptId);
        $cdRows       = Db::selectAll('SELECT custom_field_id, value FROM student_custom_data WHERE student_id = ?', [$studentId]);
        $customData   = [];
        foreach ($cdRows as $row) { $customData[(int)$row['custom_field_id']] = $row['value']; }
        $this->render('student-form/readonly', compact('student', 'profile', 'isStaff', 'customFields', 'customData'));
    }

    // GET /student/form/{studentId}/view  (staff/admin)
    public function staffView(int $studentId): void
    {
        RoleMiddleware::handle(['staff','dept_admin','institution_admin']);
        $student = Student::find($studentId);
        if (!$student) { $this->render('errors/404', [], 404); return; }

        if (Auth::role() !== 'institution_admin') {
            DepartmentScopeMiddleware::assertDepartment((int)$student['department_id']);
        }

        $profile      = StudentProfile::findByStudent($studentId) ?? [];
        $isStaff      = true;
        $deptId       = (int)($student['department_id'] ?? 0);
        $customFields = FieldConfig::resolveCustomFields($deptId);
        $cdRows       = Db::selectAll('SELECT custom_field_id, value FROM student_custom_data WHERE student_id = ?', [$studentId]);
        $customData   = [];
        foreach ($cdRows as $row) { $customData[(int)$row['custom_field_id']] = $row['value']; }
        $this->render('student-form/readonly', compact('student', 'profile', 'isStaff', 'customFields', 'customData'));
    }

    // ── Private helpers ──────────────────────────────────────────────────

    private function sanitiseInput(array $post, array $student, array $profile): array
    {
        $data  = [];
        $rules = FormFieldRules::getApplicableFields(array_merge($profile, $post), $student);

        foreach ($rules as $field) {
            $key = $field['key'];
            if ($field['type'] === 'readonly') continue;
            if (!array_key_exists($key, $post)) continue;

            $val        = $post[$key];
            $data[$key] = match($field['type']) {
                'numeric'  => $val !== '' ? max(0, (int)$val) : null,
                'phone'    => preg_replace('/\D/', '', (string)$val),
                'pincode'  => preg_replace('/\D/', '', (string)$val),
                'aadhaar'  => preg_replace('/\D/', '', (string)$val),
                'email'    => filter_var(trim($val), FILTER_SANITIZE_EMAIL) ?: null,
                'checkbox' => !empty($val) ? 1 : 0,
                'radio'    => ($val !== '') ? (string)$val : null,
                'select'   => ($val !== '') ? strip_tags(trim($val)) : null,
                'textarea' => strip_tags(trim($val)) ?: null,
                default    => strip_tags(trim($val)) ?: null,
            };
        }

        // Handle JSON qual fields from POST
        $jsonCols = ['qual_sslc','qual_hsc','qual_ug','qual_diploma','qual_other_1','qual_other_2'];
        foreach ($jsonCols as $col) {
            if (!isset($post[$col]) || !is_array($post[$col])) continue;
            $q          = $post[$col];
            $data[$col] = [
                'exam'        => strip_tags(trim($q['exam']        ?? '')),
                'board'       => strip_tags(trim($q['board']       ?? '')),
                'institution' => strip_tags(trim($q['institution'] ?? '')),
                'year'        => strip_tags(trim($q['year']        ?? '')),
                'percentage'  => strip_tags(trim($q['percentage']  ?? '')),
                'stream'      => strip_tags(trim($q['stream']      ?? '')),
                'medium'      => strip_tags(trim($q['medium']      ?? '')),
                'state'       => strip_tags(trim($q['state']       ?? '')),
            ];
        }

        return $data;
    }

    private function loadDropdowns(): array
    {
        return [
            'bloodGroups'     => ['A+','A−','B+','B−','O+','O−','AB+','AB−'],
            'casteCategories' => ['OC','OBC','BC','MBC','SC','ST','Others'],
            'maritalStatuses' => ['Single','Married','Other'],
            'religions'       => OptionValue::byListKey('religion'),
            'states'          => \App\Models\State::all(),
            'admissionTypes'  => ['management'=>'Management Quota','government'=>'Government Quota','nri'=>'NRI','lateral_entry'=>'Lateral Entry'],
            'sectionLabels'   => FormFieldRules::sectionLabels(),
        ];
    }
}
