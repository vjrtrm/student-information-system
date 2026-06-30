<?php
namespace App\Helpers;

/**
 * Static registry of all student form fields.
 * No DB access — pure configuration.
 */
class FieldRegistry
{
    /**
     * The 6 form sections, in display order.
     * These match FormFieldRules::sectionLabels() integer-keyed labels.
     */
    public const SECTIONS = [
        'Personal Details',
        'Address Details',
        'Parent / Guardian Details',
        'Academic Background',
        'Entrance & Admission Details',
        'Bank & Scholarship Details',
    ];

    /**
     * All built-in student_profiles fields.
     * key => [label, section, default_mode, locked]
     *
     * locked = true means the field cannot be configured via Field Management.
     * Locked reasons: identity/onboarding fields (set at M3), always-required fields.
     */
    public const ALL_FIELDS = [
        // ── Section 1: Personal Details ──────────────────────────────────
        // Locked — identity/onboarding
        'first_name'            => ['label' => 'First Name',                 'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => true],
        'last_name'             => ['label' => 'Last Name',                  'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => true],
        'dob'                   => ['label' => 'Date of Birth',              'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => true],
        'gender'                => ['label' => 'Gender',                     'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => true],
        'mobile'                => ['label' => 'Mobile',                     'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => true],
        // Locked — always required
        'blood_group'           => ['label' => 'Blood Group',                'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => true],
        'nationality'           => ['label' => 'Nationality',                'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => true],
        'passport_photo_path'   => ['label' => 'Passport Photo',             'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => true],
        // Configurable
        'mother_tongue'         => ['label' => 'Mother Tongue',              'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => false],
        'religion'              => ['label' => 'Religion',                   'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => false],
        'caste'                 => ['label' => 'Caste',                      'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => false],
        'caste_category'        => ['label' => 'Caste Category',             'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => false],
        'sub_caste'             => ['label' => 'Sub-Caste',                  'section' => 'Personal Details',              'default_mode' => 'optional', 'locked' => false],
        'place_of_birth'        => ['label' => 'Place of Birth',             'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => false],
        'aadhaar_number'        => ['label' => 'Aadhaar Number',             'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => false],
        'student_email'         => ['label' => 'Student Email',              'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => false],
        'alternate_mobile'      => ['label' => 'Alternate Mobile',           'section' => 'Personal Details',              'default_mode' => 'optional', 'locked' => false],
        'marital_status'        => ['label' => 'Marital Status',             'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => false],
        'physically_challenged' => ['label' => 'Physically Challenged',      'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => false],
        'disability_nature'     => ['label' => 'Nature of Disability',       'section' => 'Personal Details',              'default_mode' => 'optional', 'locked' => false],
        'first_graduate'        => ['label' => 'First Graduate',             'section' => 'Personal Details',              'default_mode' => 'required', 'locked' => false],
        'annual_family_income'  => ['label' => 'Annual Family Income (₹)',   'section' => 'Parent / Guardian Details',     'default_mode' => 'required', 'locked' => false],

        // ── Section 2: Address Details ────────────────────────────────────
        // Locked — always required
        'perm_address1'         => ['label' => 'Permanent Address Line 1',   'section' => 'Address Details',               'default_mode' => 'required', 'locked' => true],
        'perm_city'             => ['label' => 'Permanent City / Town',      'section' => 'Address Details',               'default_mode' => 'required', 'locked' => true],
        'perm_state_id'         => ['label' => 'Permanent State',            'section' => 'Address Details',               'default_mode' => 'required', 'locked' => true],
        'perm_pincode'          => ['label' => 'Permanent PIN Code',         'section' => 'Address Details',               'default_mode' => 'required', 'locked' => true],
        // Configurable
        'perm_address2'         => ['label' => 'Permanent Address Line 2',   'section' => 'Address Details',               'default_mode' => 'optional', 'locked' => false],
        'perm_taluk_id'         => ['label' => 'Permanent Taluk',            'section' => 'Address Details',               'default_mode' => 'required', 'locked' => false],
        'perm_district_id'      => ['label' => 'Permanent District',         'section' => 'Address Details',               'default_mode' => 'required', 'locked' => false],
        'comm_same_as_perm'     => ['label' => 'Communication Same as Permanent?', 'section' => 'Address Details',        'default_mode' => 'optional', 'locked' => false],
        'comm_address1'         => ['label' => 'Communication Address Line 1','section' => 'Address Details',              'default_mode' => 'optional', 'locked' => false],
        'comm_address2'         => ['label' => 'Communication Address Line 2','section' => 'Address Details',              'default_mode' => 'optional', 'locked' => false],
        'comm_city'             => ['label' => 'Communication City / Town',  'section' => 'Address Details',               'default_mode' => 'optional', 'locked' => false],
        'comm_taluk_id'         => ['label' => 'Communication Taluk',        'section' => 'Address Details',               'default_mode' => 'optional', 'locked' => false],
        'comm_district_id'      => ['label' => 'Communication District',     'section' => 'Address Details',               'default_mode' => 'optional', 'locked' => false],
        'comm_state_id'         => ['label' => 'Communication State',        'section' => 'Address Details',               'default_mode' => 'optional', 'locked' => false],
        'comm_pincode'          => ['label' => 'Communication PIN Code',     'section' => 'Address Details',               'default_mode' => 'optional', 'locked' => false],

        // ── Section 3: Parent / Guardian Details ─────────────────────────
        // Locked — always required
        'father_name'           => ['label' => "Father's Name",              'section' => 'Parent / Guardian Details',     'default_mode' => 'required', 'locked' => true],
        // Configurable
        'family_situation'      => ['label' => 'Family Situation',           'section' => 'Parent / Guardian Details',     'default_mode' => 'required', 'locked' => false],
        'father_occupation'     => ['label' => "Father's Occupation",        'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'father_qualification'  => ['label' => "Father's Qualification",     'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'father_annual_income'  => ['label' => "Father's Annual Income (₹)", 'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'father_mobile'         => ['label' => "Father's Mobile",            'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'father_email'          => ['label' => "Father's Email",             'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'mother_name'           => ['label' => "Mother's Name",              'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'mother_occupation'     => ['label' => "Mother's Occupation",        'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'mother_qualification'  => ['label' => "Mother's Qualification",     'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'mother_annual_income'  => ['label' => "Mother's Annual Income (₹)", 'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'mother_mobile'         => ['label' => "Mother's Mobile",            'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'mother_email'          => ['label' => "Mother's Email",             'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'guardian_name'         => ['label' => 'Guardian Name',              'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'guardian_relationship' => ['label' => 'Guardian Relationship',      'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'guardian_mobile'       => ['label' => 'Guardian Mobile',            'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'guardian_address'      => ['label' => 'Guardian Address',           'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],
        'guardian_email'        => ['label' => 'Guardian Email',             'section' => 'Parent / Guardian Details',     'default_mode' => 'optional', 'locked' => false],

        // ── Section 4: Academic Background ────────────────────────────────
        // Locked — always required
        'qual_sslc'             => ['label' => 'SSLC / 10th Standard',       'section' => 'Academic Background',           'default_mode' => 'required', 'locked' => true],
        // Configurable
        'qual_sslc_doc_path'    => ['label' => 'SSLC Mark Sheet',            'section' => 'Academic Background',           'default_mode' => 'optional', 'locked' => false],
        'qual_hsc'              => ['label' => 'HSC / 12th / Diploma',       'section' => 'Academic Background',           'default_mode' => 'required', 'locked' => false],
        'qual_hsc_doc_path'     => ['label' => 'HSC Mark Sheet',             'section' => 'Academic Background',           'default_mode' => 'optional', 'locked' => false],
        'qual_ug'               => ['label' => 'UG Degree',                  'section' => 'Academic Background',           'default_mode' => 'optional', 'locked' => false],
        'qual_ug_doc_path'      => ['label' => 'UG Degree Mark Sheet',       'section' => 'Academic Background',           'default_mode' => 'optional', 'locked' => false],
        'qual_diploma'          => ['label' => 'Diploma / Lateral Entry',    'section' => 'Academic Background',           'default_mode' => 'optional', 'locked' => false],
        'qual_diploma_doc_path' => ['label' => 'Diploma Mark Sheet',         'section' => 'Academic Background',           'default_mode' => 'optional', 'locked' => false],
        'qual_other_1'          => ['label' => 'Other Qualification 1',      'section' => 'Academic Background',           'default_mode' => 'optional', 'locked' => false],
        'qual_other_2'          => ['label' => 'Other Qualification 2',      'section' => 'Academic Background',           'default_mode' => 'optional', 'locked' => false],

        // ── Section 5: Entrance & Admission Details ───────────────────────
        // Locked — always required
        'admission_type'        => ['label' => 'Admission Type',             'section' => 'Entrance & Admission Details',  'default_mode' => 'required', 'locked' => true],
        // Locked — onboarding
        'academic_year_id'      => ['label' => 'Academic Year',              'section' => 'Entrance & Admission Details',  'default_mode' => 'required', 'locked' => true],
        'class_id'              => ['label' => 'Class',                      'section' => 'Entrance & Admission Details',  'default_mode' => 'required', 'locked' => true],
        'section_id'            => ['label' => 'Section',                    'section' => 'Entrance & Admission Details',  'default_mode' => 'optional', 'locked' => true],
        'admission_date'        => ['label' => 'Admission Date',             'section' => 'Entrance & Admission Details',  'default_mode' => 'required', 'locked' => true],
        'department_id'         => ['label' => 'Department',                 'section' => 'Entrance & Admission Details',  'default_mode' => 'required', 'locked' => true],
        'programme_level'       => ['label' => 'Programme Level',            'section' => 'Entrance & Admission Details',  'default_mode' => 'required', 'locked' => true],
        // Configurable
        'entrance_exam_name'    => ['label' => 'Entrance Exam Name',         'section' => 'Entrance & Admission Details',  'default_mode' => 'optional', 'locked' => false],
        'entrance_hall_ticket'  => ['label' => 'Entrance Hall Ticket No.',   'section' => 'Entrance & Admission Details',  'default_mode' => 'optional', 'locked' => false],
        'entrance_rank_score'   => ['label' => 'Entrance Rank / Score',      'section' => 'Entrance & Admission Details',  'default_mode' => 'optional', 'locked' => false],
        'admission_number'      => ['label' => 'Admission / Application No.','section' => 'Entrance & Admission Details',  'default_mode' => 'required', 'locked' => false],
        'community_cert_number' => ['label' => 'Community Certificate No.',  'section' => 'Entrance & Admission Details',  'default_mode' => 'optional', 'locked' => false],
        'community_cert_path'   => ['label' => 'Community Certificate',      'section' => 'Entrance & Admission Details',  'default_mode' => 'optional', 'locked' => false],
        'transfer_cert_number'  => ['label' => 'Transfer Certificate No.',   'section' => 'Entrance & Admission Details',  'default_mode' => 'optional', 'locked' => false],
        'transfer_cert_path'    => ['label' => 'Transfer Certificate',       'section' => 'Entrance & Admission Details',  'default_mode' => 'optional', 'locked' => false],
        'conduct_cert_path'     => ['label' => 'Conduct Certificate',        'section' => 'Entrance & Admission Details',  'default_mode' => 'optional', 'locked' => false],
        'migration_cert_path'   => ['label' => 'Migration Certificate',      'section' => 'Entrance & Admission Details',  'default_mode' => 'optional', 'locked' => false],
        'income_cert_path'      => ['label' => 'Income Certificate',         'section' => 'Entrance & Admission Details',  'default_mode' => 'optional', 'locked' => false],
        'nativity_cert_path'    => ['label' => 'Nativity Certificate',       'section' => 'Entrance & Admission Details',  'default_mode' => 'optional', 'locked' => false],
        'aadhaar_copy_path'     => ['label' => 'Aadhaar Card Copy',          'section' => 'Entrance & Admission Details',  'default_mode' => 'required', 'locked' => false],

        // ── Section 6: Bank & Scholarship Details ─────────────────────────
        'bank_account_holder'   => ['label' => 'Account Holder Name',        'section' => 'Bank & Scholarship Details',    'default_mode' => 'optional', 'locked' => false],
        'bank_name'             => ['label' => 'Bank Name',                  'section' => 'Bank & Scholarship Details',    'default_mode' => 'optional', 'locked' => false],
        'bank_branch'           => ['label' => 'Branch Name',                'section' => 'Bank & Scholarship Details',    'default_mode' => 'optional', 'locked' => false],
        'bank_account_number'   => ['label' => 'Account Number',             'section' => 'Bank & Scholarship Details',    'default_mode' => 'optional', 'locked' => false],
        'bank_ifsc'             => ['label' => 'IFSC Code',                  'section' => 'Bank & Scholarship Details',    'default_mode' => 'optional', 'locked' => false],
        'bank_passbook_path'    => ['label' => 'Bank Passbook / Statement',  'section' => 'Bank & Scholarship Details',    'default_mode' => 'optional', 'locked' => false],
        'scholarship_applied'   => ['label' => 'Scholarship Applied?',       'section' => 'Bank & Scholarship Details',    'default_mode' => 'optional', 'locked' => false],
        'scholarship_scheme'    => ['label' => 'Scholarship Scheme Name',    'section' => 'Bank & Scholarship Details',    'default_mode' => 'optional', 'locked' => false],
        'scholarship_app_number'=> ['label' => 'Scholarship Application No.','section' => 'Bank & Scholarship Details',    'default_mode' => 'optional', 'locked' => false],
    ];

    /**
     * Set of field keys that are NOT configurable via Field Management.
     */
    public const LOCKED_KEYS = [
        // Identity / onboarding (set at M3, cannot change)
        'first_name', 'last_name', 'dob', 'mobile', 'gender',
        'programme_level', 'academic_year_id', 'class_id', 'section_id',
        'admission_date', 'department_id',
        // Always required — not subject to admin override
        'blood_group', 'nationality', 'passport_photo_path',
        'perm_address1', 'perm_city', 'perm_state_id', 'perm_pincode',
        'father_name', 'qual_sslc', 'admission_type',
    ];

    /**
     * Returns ALL_FIELDS filtered to only the fields that are configurable (locked === false).
     */
    public static function configurableFields(): array
    {
        $result = [];
        foreach (self::ALL_FIELDS as $key => $field) {
            if (!$field['locked']) {
                $result[$key] = $field;
            }
        }
        return $result;
    }

    /**
     * Returns true if the given key is a custom field key (custom_{id}).
     */
    public static function isCustomKey(string $key): bool
    {
        return str_starts_with($key, 'custom_');
    }
}
