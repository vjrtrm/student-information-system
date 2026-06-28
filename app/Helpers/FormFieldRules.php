<?php
namespace App\Helpers;

class FormFieldRules
{
    /**
     * Returns the canonical list of all student-profile fields with their rules
     * for a given student context.
     *
     * Each entry: ['key' => string, 'label' => string, 'section' => int,
     *              'required' => bool, 'visible' => bool, 'type' => string]
     *
     * type: 'text'|'email'|'numeric'|'phone'|'pincode'|'aadhaar'|'date'|
     *       'select'|'radio'|'checkbox'|'file'|'photo'|'json_qual'|'textarea'
     *
     * $profile: current student_profiles row (array, may be empty [])
     * $student: students row (must have programme_level, department_id)
     */
    public static function getApplicableFields(array $profile, array $student): array
    {
        $level    = $student['programme_level'] ?? 'UG';  // UG or PG
        $sit      = $profile['family_situation'] ?? 'both_parents';
        $admType  = $profile['admission_type']   ?? null;
        $physCh   = (bool)($profile['physically_challenged'] ?? false);
        $commSame = (bool)($profile['comm_same_as_perm'] ?? false);
        $scholApp = (bool)($profile['scholarship_applied'] ?? false);

        // Helper closures
        $req  = fn(string $key, string $label, int $sec, string $type) =>
            ['key'=>$key,'label'=>$label,'section'=>$sec,'required'=>true,'visible'=>true,'type'=>$type];
        $opt  = fn(string $key, string $label, int $sec, string $type) =>
            ['key'=>$key,'label'=>$label,'section'=>$sec,'required'=>false,'visible'=>true,'type'=>$type];
        $cond = fn(string $key, string $label, int $sec, string $type, bool $vis, bool $reqWhenVis) =>
            ['key'=>$key,'label'=>$label,'section'=>$sec,'required'=>($vis && $reqWhenVis),'visible'=>$vis,'type'=>$type];

        // ── Section 1: Personal ──────────────────────────────────────────
        $fields = [
            // pre-filled read-only from M3 — not validated (not in required count)
            ['key'=>'first_name','label'=>'First Name','section'=>1,'required'=>false,'visible'=>true,'type'=>'readonly'],
            ['key'=>'last_name', 'label'=>'Last Name', 'section'=>1,'required'=>false,'visible'=>true,'type'=>'readonly'],
            ['key'=>'dob',       'label'=>'Date of Birth','section'=>1,'required'=>false,'visible'=>true,'type'=>'readonly'],
            ['key'=>'gender',    'label'=>'Gender',    'section'=>1,'required'=>false,'visible'=>true,'type'=>'readonly'],
            ['key'=>'mobile',    'label'=>'Mobile',    'section'=>1,'required'=>false,'visible'=>true,'type'=>'readonly'],

            $req('blood_group',          'Blood Group',             1, 'select'),
            $req('mother_tongue',        'Mother Tongue',           1, 'text'),
            $req('religion',             'Religion',                1, 'select'),
            $req('caste',                'Caste',                   1, 'text'),
            $req('caste_category',       'Caste Category',          1, 'select'),
            $opt('sub_caste',            'Sub-Caste',               1, 'text'),
            $req('nationality',          'Nationality',             1, 'text'),
            $req('place_of_birth',       'Place of Birth',          1, 'text'),
            $req('aadhaar_number',       'Aadhaar Number',          1, 'aadhaar'),
            $req('passport_photo_path',  'Passport Photo',          1, 'photo'),
            $req('student_email',        'Student Email',           1, 'email'),
            $opt('alternate_mobile',     'Alternate Mobile',        1, 'phone'),
            $req('marital_status',       'Marital Status',          1, 'select'),
            $req('physically_challenged','Physically Challenged',   1, 'radio'),
            $cond('disability_nature',   'Nature of Disability',    1, 'text', $physCh, true),
            $req('first_graduate',       'First Graduate',          1, 'radio'),
            $req('annual_family_income', 'Annual Family Income (₹)',1, 'numeric'),
        ];

        // ── Section 2: Address ───────────────────────────────────────────
        $fields = array_merge($fields, [
            $req('perm_address1',    'Permanent Address Line 1',  2, 'text'),
            $opt('perm_address2',    'Permanent Address Line 2',  2, 'text'),
            $req('perm_city',        'Permanent City / Town',     2, 'text'),
            $req('perm_taluk_id',    'Permanent Taluk',           2, 'select'),
            $req('perm_district_id', 'Permanent District',        2, 'select'),
            $req('perm_state_id',    'Permanent State',           2, 'select'),
            $req('perm_pincode',     'Permanent PIN Code',        2, 'pincode'),
            $opt('comm_same_as_perm','Communication same as Permanent?', 2, 'checkbox'),
            $cond('comm_address1',   'Communication Address Line 1', 2, 'text',   !$commSame, true),
            $cond('comm_address2',   'Communication Address Line 2', 2, 'text',   !$commSame, false),
            $cond('comm_city',       'Communication City / Town',    2, 'text',   !$commSame, true),
            $cond('comm_taluk_id',   'Communication Taluk',          2, 'select', !$commSame, true),
            $cond('comm_district_id','Communication District',        2, 'select', !$commSame, true),
            $cond('comm_state_id',   'Communication State',           2, 'select', !$commSame, true),
            $cond('comm_pincode',    'Communication PIN Code',        2, 'pincode',!$commSame, true),
        ]);

        // ── Section 3: Parent / Guardian ─────────────────────────────────
        // Father's Name: always required
        // Father's other fields: required for both_parents + single_parent_father; optional otherwise
        $fatherReq   = in_array($sit, ['both_parents', 'single_parent_father'], true);
        // Mother fields: required for both_parents + single_parent_mother; hidden for guardian
        $motherVis   = ($sit !== 'guardian');
        $motherReq   = in_array($sit, ['both_parents', 'single_parent_mother'], true);
        // Guardian fields: hidden for both_parents; required for guardian; optional for single parent
        $guardianVis = ($sit !== 'both_parents');
        $guardianReq = ($sit === 'guardian');

        $fields = array_merge($fields, [
            $req('family_situation',    'Family Situation',              3, 'select'),
            $req('father_name',         "Father's Name",                 3, 'text'),
            $cond('father_occupation',  "Father's Occupation",           3, 'text',   true,       $fatherReq),
            $cond('father_qualification',"Father's Qualification",       3, 'text',   true,       $fatherReq),
            $cond('father_annual_income',"Father's Annual Income (₹)",   3, 'numeric',true,       $fatherReq),
            $cond('father_mobile',      "Father's Mobile",               3, 'phone',  true,       $fatherReq),
            $opt('father_email',        "Father's Email",                3, 'email'),
            $cond('mother_name',        "Mother's Name",                 3, 'text',   $motherVis, $motherReq),
            $cond('mother_occupation',  "Mother's Occupation",           3, 'text',   $motherVis, $motherReq),
            $cond('mother_qualification',"Mother's Qualification",       3, 'text',   $motherVis, $motherReq),
            $cond('mother_annual_income',"Mother's Annual Income (₹)",   3, 'numeric',$motherVis, $motherReq),
            $cond('mother_mobile',      "Mother's Mobile",               3, 'phone',  $motherVis, $motherReq),
            $cond('mother_email',       "Mother's Email",                3, 'email',  $motherVis, false),
            $cond('guardian_name',      'Guardian Name',                 3, 'text',   $guardianVis,$guardianReq),
            $cond('guardian_relationship','Guardian Relationship',       3, 'text',   $guardianVis,$guardianReq),
            $cond('guardian_mobile',    'Guardian Mobile',               3, 'phone',  $guardianVis,$guardianReq),
            $cond('guardian_address',   'Guardian Address',              3, 'textarea',$guardianVis,$guardianReq),
            $cond('guardian_email',     'Guardian Email',                3, 'email',  $guardianVis,false),
        ]);

        // ── Section 4: Academic Background ───────────────────────────────
        $ugDiplomaVis = ($admType === 'lateral_entry');
        $ugDegreeVis  = ($level === 'PG');

        $fields = array_merge($fields, [
            $req('qual_sslc',         'SSLC / 10th Standard',          4, 'json_qual'),
            $opt('qual_sslc_doc_path','SSLC Mark Sheet',                4, 'file'),
            $req('qual_hsc',          'HSC / 12th / Diploma',          4, 'json_qual'),
            $opt('qual_hsc_doc_path', 'HSC Mark Sheet',                 4, 'file'),
            $cond('qual_ug',          'UG Degree',                      4, 'json_qual',$ugDegreeVis,  true),
            $cond('qual_ug_doc_path', 'UG Degree Mark Sheet',           4, 'file',     $ugDegreeVis,  false),
            $cond('qual_diploma',     'Diploma / Lateral Entry',        4, 'json_qual',$ugDiplomaVis, true),
            $cond('qual_diploma_doc_path','Diploma Mark Sheet',         4, 'file',     $ugDiplomaVis, false),
            $opt('qual_other_1',      'Other Qualification 1',          4, 'json_qual'),
            $opt('qual_other_2',      'Other Qualification 2',          4, 'json_qual'),
        ]);

        // ── Section 5: Entrance & Admission ──────────────────────────────
        $fields = array_merge($fields, [
            $req('admission_type',      'Admission Type',               5, 'select'),
            $opt('entrance_exam_name',  'Entrance Exam Name',           5, 'text'),
            $opt('entrance_hall_ticket','Entrance Hall Ticket No.',     5, 'text'),
            $opt('entrance_rank_score', 'Entrance Rank / Score',        5, 'text'),
            $req('admission_number',    'Admission / Application No.',  5, 'text'),
            $opt('community_cert_number','Community Certificate No.',   5, 'text'),
            $opt('community_cert_path', 'Community Certificate',        5, 'file'),
            $opt('transfer_cert_number','Transfer Certificate No.',     5, 'text'),
            $opt('transfer_cert_path',  'Transfer Certificate',         5, 'file'),
            $opt('conduct_cert_path',   'Conduct Certificate',          5, 'file'),
            $cond('migration_cert_path','Migration Certificate',        5, 'file', $level === 'PG', false),
            $opt('income_cert_path',    'Income Certificate',           5, 'file'),
            $opt('nativity_cert_path',  'Nativity Certificate',        5, 'file'),
            $req('aadhaar_copy_path',   'Aadhaar Card Copy',            5, 'file'),
        ]);

        // ── Section 6: Bank & Scholarship (all optional) ─────────────────
        $fields = array_merge($fields, [
            $opt('bank_account_holder', 'Account Holder Name',          6, 'text'),
            $opt('bank_name',           'Bank Name',                    6, 'text'),
            $opt('bank_branch',         'Branch Name',                  6, 'text'),
            $opt('bank_account_number', 'Account Number',               6, 'text'),
            $opt('bank_ifsc',           'IFSC Code',                    6, 'text'),
            $opt('bank_passbook_path',  'Bank Passbook / Statement',    6, 'file'),
            $opt('scholarship_applied', 'Scholarship Applied?',         6, 'radio'),
            $cond('scholarship_scheme', 'Scholarship Scheme Name',      6, 'text',  $scholApp, false),
            $cond('scholarship_app_number','Scholarship Application No.',6,'text',  $scholApp, false),
        ]);

        return $fields;
    }

    /**
     * Compute completion percentage.
     * Only counts required+visible fields. File fields: non-null path = filled.
     * JSON qual fields: filled if has all of exam|board|institution|year|percentage non-empty.
     * Returns 0-100 (int).
     */
    public static function computeCompletion(array $profile, array $rules): int
    {
        $required = array_filter($rules, fn($f) => $f['required'] && $f['visible']);
        $total    = count($required);
        if ($total === 0) return 100;

        $filled = 0;
        foreach ($required as $field) {
            $key = $field['key'];
            $val = $profile[$key] ?? null;

            if ($field['type'] === 'json_qual') {
                $data = is_string($val) ? json_decode($val, true) : (is_array($val) ? $val : null);
                if (is_array($data)) {
                    $keys = ['exam', 'board', 'institution', 'year', 'percentage'];
                    if (count(array_filter($keys, fn($k) => !empty($data[$k]))) === count($keys)) {
                        $filled++;
                    }
                }
            } elseif (in_array($field['type'], ['file', 'photo'], true)) {
                if (!empty($val)) $filled++;
            } elseif ($field['type'] === 'radio' || $field['type'] === 'checkbox') {
                if ($val !== null && $val !== '') $filled++;
            } else {
                if ($val !== null && trim((string)$val) !== '') $filled++;
            }
        }

        return (int)floor(($filled / $total) * 100);
    }

    /** Returns section labels keyed by section number. */
    public static function sectionLabels(): array
    {
        return [
            1 => 'Personal Details',
            2 => 'Address Details',
            3 => 'Parent / Guardian Details',
            4 => 'Academic Background',
            5 => 'Entrance & Admission Details',
            6 => 'Bank & Scholarship Details',
        ];
    }
}
