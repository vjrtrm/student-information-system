<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\FieldConfig;
use App\Helpers\MasterAuditLogger;
use App\Middleware\RoleMiddleware;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class StudentGridController extends Controller
{
    private const SORT_COLUMNS = [
        'enrolment_number' => 's.enrolment_number',
        'name'             => 's.first_name',
        'form_status'      => 'sp.form_status',
        'programme_level'  => 's.programme_level',
    ];
    private const DEFAULT_SORT = 'enrolment_number';
    private const DEFAULT_DIR  = 'ASC';

    // GET /students
    public function index(): void
    {
        RoleMiddleware::handle(['staff', 'dept_admin', 'institution_admin']);

        $filters = $this->parseFilters();
        // Enforce dept scope for non-inst_admin
        if (Auth::role() !== 'institution_admin') {
            $filters['dept_id'] = (int) Auth::departmentId();
        }

        $sort    = $filters['sort'] ?? self::DEFAULT_SORT;
        $dir     = $filters['dir']  ?? self::DEFAULT_DIR;
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = in_array((int)($filters['per_page'] ?? 25), [25, 50, 100], true)
                   ? (int)$filters['per_page'] : 25;

        $total   = $this->fetchCount($filters);
        $rows    = $this->fetchPage($filters, $page, $perPage, $sort, $dir);
        $chips   = $this->fetchStatChips($filters);
        $offset  = ($page - 1) * $perPage;
        $pages   = (int) ceil($total / $perPage);

        // Filter dropdown data
        $academicYears = Db::selectAll(
            "SELECT ov.id, ov.display FROM option_values ov
             JOIN option_lists ol ON ol.id = ov.list_id
             WHERE ol.list_key = 'academic_year' AND ov.status = 'active'
             ORDER BY ov.sort_order, ov.display"
        );
        $departments = [];
        if (Auth::role() === 'institution_admin') {
            $departments = Db::selectAll(
                "SELECT id, name FROM departments WHERE status = 'active' ORDER BY name"
            );
        }

        $title = 'Students';
        ob_start();
        require dirname(__DIR__) . '/Views/students/index.php';
        $content = ob_get_clean();
        require dirname(__DIR__) . '/Views/layouts/app.php';
    }

    // GET /students/export
    public function export(): void
    {
        RoleMiddleware::handle(['staff', 'dept_admin', 'institution_admin']);

        $filters = $this->parseFilters();
        if (Auth::role() !== 'institution_admin') {
            $filters['dept_id'] = (int) Auth::departmentId();
        }

        $sort = self::DEFAULT_SORT;
        $dir  = self::DEFAULT_DIR;

        $rows = $this->fetchAll($filters, $sort, $dir);

        // --- Resolve geography names for all students ---
        $studentIds = array_column($rows, 'id');
        $geoData = [];
        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $geoRows = Db::selectAll(
                "SELECT sp.student_id,
                        ps.name AS perm_state_name, pd.name AS perm_district_name, pt.name AS perm_taluk_name,
                        cs.name AS comm_state_name, cd.name AS comm_district_name, ct.name AS comm_taluk_name
                 FROM student_profiles sp
                 LEFT JOIN states    ps ON ps.id = sp.perm_state_id
                 LEFT JOIN districts pd ON pd.id = sp.perm_district_id
                 LEFT JOIN taluks    pt ON pt.id = sp.perm_taluk_id
                 LEFT JOIN states    cs ON cs.id = sp.comm_state_id
                 LEFT JOIN districts cd ON cd.id = sp.comm_district_id
                 LEFT JOIN taluks    ct ON ct.id = sp.comm_taluk_id
                 WHERE sp.student_id IN ($placeholders)",
                $studentIds
            );
            foreach ($geoRows as $g) {
                $geoData[$g['student_id']] = $g;
            }
        }

        // --- Resolve custom fields ---
        if (Auth::role() === 'institution_admin') {
            $customFields = Db::selectAll(
                "SELECT id, label, section FROM custom_fields WHERE status = 'active' ORDER BY section, sort_order, id"
            );
        } else {
            $customFields = FieldConfig::resolveCustomFields((int) Auth::departmentId());
        }

        // Batch-load custom data
        $customData = [];
        if (!empty($studentIds) && !empty($customFields)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $cdRows = Db::selectAll(
                "SELECT student_id, custom_field_id, value FROM student_custom_data WHERE student_id IN ($placeholders)",
                $studentIds
            );
            foreach ($cdRows as $cd) {
                $customData[$cd['student_id']][$cd['custom_field_id']] = $cd['value'];
            }
        }

        // --- Full profile data fetch ---
        $profileData = [];
        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $profileRows = Db::selectAll(
                "SELECT * FROM student_profiles WHERE student_id IN ($placeholders)",
                $studentIds
            );
            foreach ($profileRows as $p) {
                $profileData[$p['student_id']] = $p;
            }
        }

        // --- Build Spreadsheet ---
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Students');

        // Define column headers in order
        $headers = [
            // Identification
            'Enrolment Number', 'First Name', 'Last Name', 'Gender', 'Date of Birth',
            'Mobile', 'Programme Level', 'Department', 'Academic Year',
            'Admission Date', 'Enrolment Approval Status', 'Form Status', 'Form Completion %',
            // Personal Details
            'Blood Group', 'Mother Tongue', 'Religion', 'Caste', 'Caste Category', 'Sub-Caste',
            'Nationality', 'Place of Birth', 'Aadhaar Number', 'Student Email', 'Alternate Mobile',
            'Marital Status', 'Physically Challenged', 'Disability Nature', 'First Graduate',
            'Annual Family Income',
            // Contact & Address
            'Perm Address 1', 'Perm Address 2', 'Perm City', 'Perm Taluk', 'Perm District',
            'Perm State', 'Perm Pincode', 'Comm Same as Perm', 'Comm Address 1', 'Comm Address 2',
            'Comm City', 'Comm Taluk', 'Comm District', 'Comm State', 'Comm Pincode',
            // Family Details
            'Family Situation', 'Father Name', 'Father Occupation', 'Father Qualification',
            'Father Annual Income', 'Father Mobile', 'Father Email', 'Mother Name',
            'Mother Occupation', 'Mother Qualification', 'Mother Annual Income', 'Mother Mobile',
            'Mother Email', 'Guardian Name', 'Guardian Relationship', 'Guardian Mobile',
            'Guardian Address', 'Guardian Email',
            // Qualification Details (no doc paths)
            'SSLC', 'HSC', 'UG', 'Diploma', 'Other Qual 1', 'Other Qual 2',
            // Admission Details (no doc paths)
            'Admission Type', 'Entrance Exam Name', 'Entrance Hall Ticket', 'Entrance Rank/Score',
            'Admission Number', 'Community Cert Number', 'Transfer Cert Number',
            // Bank & Scholarship
            'Bank Account Holder', 'Bank Name', 'Bank Branch', 'Bank Account Number', 'Bank IFSC',
            'Scholarship Applied', 'Scholarship Scheme', 'Scholarship App Number',
        ];
        // Append custom field labels
        foreach ($customFields as $cf) {
            $headers[] = $cf['label'];
        }

        // Write header row
        $col = 1;
        foreach ($headers as $h) {
            $sheet->getCell([$col++, 1])->setValue($h);
        }
        // Style header row
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = 'A1:' . $lastCol . '1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        ]);
        $sheet->freezePane('A2');

        // Write data rows
        $rowNum = 2;
        foreach ($rows as $s) {
            $sid = $s['id'];
            $p   = $profileData[$sid] ?? [];
            $g   = $geoData[$sid] ?? [];

            $values = [
                // Identification
                $s['enrolment_number'] ?? ($s['enrolment_serial'] ? '#' . $s['enrolment_serial'] : ''),
                $s['first_name'],
                $s['last_name'],
                $s['gender'],
                $s['dob'],
                $s['mobile'],
                $s['programme_level'],
                $s['dept_name'],
                $s['academic_year_label'],
                $s['admission_date'],
                $s['enrolment_approval_status'] ?? 'Not Generated',
                $p['form_status'] ?? '',
                $p['form_completion_pct'] ?? 0,
                // Personal
                $p['blood_group'] ?? '', $p['mother_tongue'] ?? '', $p['religion'] ?? '',
                $p['caste'] ?? '', $p['caste_category'] ?? '', $p['sub_caste'] ?? '',
                $p['nationality'] ?? '', $p['place_of_birth'] ?? '', $p['aadhaar_number'] ?? '',
                $p['student_email'] ?? '', $p['alternate_mobile'] ?? '', $p['marital_status'] ?? '',
                $p['physically_challenged'] ?? 0, $p['disability_nature'] ?? '',
                $p['first_graduate'] ?? 0, $p['annual_family_income'] ?? '',
                // Contact
                $p['perm_address1'] ?? '', $p['perm_address2'] ?? '', $p['perm_city'] ?? '',
                $g['perm_taluk_name'] ?? '', $g['perm_district_name'] ?? '', $g['perm_state_name'] ?? '',
                $p['perm_pincode'] ?? '', $p['comm_same_as_perm'] ?? 0,
                $p['comm_address1'] ?? '', $p['comm_address2'] ?? '', $p['comm_city'] ?? '',
                $g['comm_taluk_name'] ?? '', $g['comm_district_name'] ?? '', $g['comm_state_name'] ?? '',
                $p['comm_pincode'] ?? '',
                // Family
                $p['family_situation'] ?? '', $p['father_name'] ?? '', $p['father_occupation'] ?? '',
                $p['father_qualification'] ?? '', $p['father_annual_income'] ?? '',
                $p['father_mobile'] ?? '', $p['father_email'] ?? '',
                $p['mother_name'] ?? '', $p['mother_occupation'] ?? '', $p['mother_qualification'] ?? '',
                $p['mother_annual_income'] ?? '', $p['mother_mobile'] ?? '', $p['mother_email'] ?? '',
                $p['guardian_name'] ?? '', $p['guardian_relationship'] ?? '',
                $p['guardian_mobile'] ?? '', $p['guardian_address'] ?? '', $p['guardian_email'] ?? '',
                // Qualifications
                $p['qual_sslc'] ?? '', $p['qual_hsc'] ?? '', $p['qual_ug'] ?? '',
                $p['qual_diploma'] ?? '', $p['qual_other_1'] ?? '', $p['qual_other_2'] ?? '',
                // Admission
                $p['admission_type'] ?? '', $p['entrance_exam_name'] ?? '',
                $p['entrance_hall_ticket'] ?? '', $p['entrance_rank_score'] ?? '',
                $p['admission_number'] ?? '', $p['community_cert_number'] ?? '',
                $p['transfer_cert_number'] ?? '',
                // Bank & Scholarship
                $p['bank_account_holder'] ?? '', $p['bank_name'] ?? '', $p['bank_branch'] ?? '',
                $p['bank_account_number'] ?? '', $p['bank_ifsc'] ?? '',
                $p['scholarship_applied'] ?? 0, $p['scholarship_scheme'] ?? '',
                $p['scholarship_app_number'] ?? '',
            ];
            // Append custom field values
            foreach ($customFields as $cf) {
                $values[] = $customData[$sid][$cf['id']] ?? '';
            }

            $col = 1;
            foreach ($values as $v) {
                $sheet->getCell([$col++, $rowNum])->setValue($v);
            }
            $rowNum++;
        }

        // Auto-size columns
        foreach (range(1, count($headers)) as $colIdx) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))->setAutoSize(true);
        }

        MasterAuditLogger::log('export', 'student_grid', null, [
            'filters'   => $filters,
            'row_count' => count($rows),
        ]);

        $filename = 'students_export_' . date('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // ---- Private helpers ----

    private function parseFilters(): array
    {
        $validDirs  = ['ASC', 'DESC'];
        $validSorts = array_keys(self::SORT_COLUMNS);

        $sort = in_array($_GET['sort'] ?? '', $validSorts, true) ? $_GET['sort'] : self::DEFAULT_SORT;
        $dir  = in_array(strtoupper($_GET['dir'] ?? ''), $validDirs, true) ? strtoupper($_GET['dir']) : self::DEFAULT_DIR;

        $formStatuses = $_GET['form_status'] ?? [];
        if (!is_array($formStatuses)) $formStatuses = [];
        $validStatuses = ['incomplete', 'complete', 'submitted', 'approved'];
        $formStatuses = array_values(array_intersect($formStatuses, $validStatuses));

        $enrolStatus = $_GET['enrol_status'] ?? '';
        $validEnrol  = ['pending', 'approved', 'not_generated'];

        return [
            'search'      => trim($_GET['search'] ?? ''),
            'dept_id'     => (int)($_GET['dept_id'] ?? 0) ?: null,
            'year_id'     => (int)($_GET['year_id'] ?? 0) ?: null,
            'prog_level'  => in_array($_GET['prog_level'] ?? '', ['UG', 'PG'], true) ? $_GET['prog_level'] : '',
            'form_status' => $formStatuses,
            'enrol_status'=> in_array($enrolStatus, $validEnrol, true) ? $enrolStatus : '',
            'sort'        => $sort,
            'dir'         => $dir,
            'page'        => max(1, (int)($_GET['page'] ?? 1)),
            'per_page'    => in_array((int)($_GET['per_page'] ?? 25), [25, 50, 100], true) ? (int)$_GET['per_page'] : 25,
        ];
    }

    private function buildWhere(array $filters): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['dept_id'])) {
            $where[]  = 's.department_id = ?';
            $params[] = $filters['dept_id'];
        }
        if (!empty($filters['search'])) {
            $like     = '%' . $filters['search'] . '%';
            $where[]  = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.enrolment_number LIKE ? OR s.mobile LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['year_id'])) {
            $where[]  = 's.academic_year_id = ?';
            $params[] = $filters['year_id'];
        }
        if (!empty($filters['prog_level'])) {
            $where[]  = 's.programme_level = ?';
            $params[] = $filters['prog_level'];
        }
        if (!empty($filters['form_status'])) {
            $ph      = implode(',', array_fill(0, count($filters['form_status']), '?'));
            $where[] = "sp.form_status IN ($ph)";
            foreach ($filters['form_status'] as $fs) $params[] = $fs;
        }
        if (!empty($filters['enrol_status'])) {
            if ($filters['enrol_status'] === 'not_generated') {
                $where[] = 's.enrolment_approval_status IS NULL';
            } else {
                $where[]  = 's.enrolment_approval_status = ?';
                $params[] = $filters['enrol_status'];
            }
        }

        $clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        return [$clause, $params];
    }

    private function baseFrom(): string
    {
        return "FROM students s
                LEFT JOIN student_profiles sp ON sp.student_id = s.id
                LEFT JOIN departments d ON d.id = s.department_id
                LEFT JOIN option_values ov ON ov.id = s.academic_year_id";
    }

    private function fetchCount(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = "SELECT COUNT(*) AS cnt " . $this->baseFrom() . " $where";
        $row = Db::selectOne($sql, $params);
        return (int)($row['cnt'] ?? 0);
    }

    private function fetchPage(array $filters, int $page, int $perPage, string $sort, string $dir): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sortCol  = self::SORT_COLUMNS[$sort] ?? self::SORT_COLUMNS[self::DEFAULT_SORT];
        $dirSafe  = $dir === 'DESC' ? 'DESC' : 'ASC';
        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        $sql = "SELECT s.id, s.first_name, s.last_name, s.mobile, s.dob, s.gender,
                       s.enrolment_number, s.enrolment_serial, s.enrolment_approval_status,
                       s.programme_level, s.onboarding_status, s.admission_date,
                       s.academic_year_id, s.class_id, s.section_id,
                       sp.form_status, sp.form_completion_pct,
                       d.name AS dept_name,
                       ov.display AS academic_year_label
                " . $this->baseFrom() . "
                $where
                ORDER BY $sortCol $dirSafe, s.first_name ASC
                LIMIT ? OFFSET ?";
        return Db::selectAll($sql, $params);
    }

    private function fetchAll(array $filters, string $sort, string $dir): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sortCol = self::SORT_COLUMNS[$sort] ?? self::SORT_COLUMNS[self::DEFAULT_SORT];
        $dirSafe = $dir === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT s.id, s.first_name, s.last_name, s.mobile, s.dob, s.gender,
                       s.enrolment_number, s.enrolment_serial, s.enrolment_approval_status,
                       s.programme_level, s.onboarding_status, s.admission_date,
                       s.academic_year_id, s.class_id, s.section_id,
                       d.name AS dept_name,
                       ov.display AS academic_year_label
                " . $this->baseFrom() . "
                $where
                ORDER BY $sortCol $dirSafe, s.first_name ASC";
        return Db::selectAll($sql, $params);
    }

    private function fetchStatChips(array $filters): array
    {
        // Total for current scope (ignores form_status and enrol_status filters)
        $scopeFilters = array_intersect_key($filters, array_flip(['dept_id', 'search', 'year_id', 'prog_level']));
        [$where, $params] = $this->buildWhere($scopeFilters);
        $total = (int)(Db::selectOne("SELECT COUNT(*) AS cnt " . $this->baseFrom() . " $where", $params)['cnt'] ?? 0);

        $submittedFilters = $scopeFilters + ['form_status' => ['submitted']];
        [$where2, $params2] = $this->buildWhere($submittedFilters);
        $submitted = (int)(Db::selectOne("SELECT COUNT(*) AS cnt " . $this->baseFrom() . " $where2", $params2)['cnt'] ?? 0);

        $approvedFilters = $scopeFilters + ['form_status' => ['approved']];
        [$where3, $params3] = $this->buildWhere($approvedFilters);
        $approved = (int)(Db::selectOne("SELECT COUNT(*) AS cnt " . $this->baseFrom() . " $where3", $params3)['cnt'] ?? 0);

        return compact('total', 'submitted', 'approved');
    }
}
