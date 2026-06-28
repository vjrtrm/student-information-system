<?php
// PHPUnit bootstrap: autoload + in-memory SQLite schema for fast, isolated tests.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Helpers\Config;

Config::setPath(dirname(__DIR__) . '/config');

/** SQLite-compatible mirror of the Module 1 schema (MySQL migrations live in database/migrations). */
function sis_test_schema(): array
{
    return [
        "CREATE TABLE departments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL, code TEXT NOT NULL,
            level TEXT NOT NULL DEFAULT 'UG', status TEXT NOT NULL DEFAULT 'active',
            created_at TEXT
        )",
        "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL, email TEXT NOT NULL,
            password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'staff',
            department_id INTEGER, staff_code TEXT, mobile TEXT,
            status TEXT NOT NULL DEFAULT 'active',
            failed_attempts INTEGER NOT NULL DEFAULT 0, locked_until TEXT,
            created_at TEXT
        )",
        "CREATE TABLE students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mobile TEXT NOT NULL, dob TEXT NOT NULL, department_id INTEGER,
            status TEXT NOT NULL DEFAULT 'active',
            failed_attempts INTEGER NOT NULL DEFAULT 0, locked_until TEXT,
            created_at TEXT
        )",
        "CREATE TABLE password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL, token_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL, used_at TEXT, created_at TEXT
        )",
        "CREATE TABLE login_otps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            principal_type TEXT NOT NULL, principal_id INTEGER NOT NULL,
            code_hash TEXT NOT NULL, expires_at TEXT NOT NULL, used_at TEXT, created_at TEXT
        )",
        "CREATE TABLE auth_audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            principal_type TEXT NOT NULL DEFAULT 'unknown', principal_id INTEGER,
            event TEXT NOT NULL, ip TEXT, user_agent TEXT, created_at TEXT
        )",
        // Module 2 — option lists
        "CREATE TABLE IF NOT EXISTS option_lists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            list_key TEXT NOT NULL, label TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'active', created_at TEXT
        )",
        "CREATE TABLE IF NOT EXISTS option_values (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            list_id INTEGER NOT NULL, value TEXT NOT NULL, display TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'active'
        )",
        // Module 2 — audit log (master-data changes)
        "CREATE TABLE IF NOT EXISTS audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            actor_id INTEGER, actor_role TEXT,
            action TEXT NOT NULL, entity TEXT NOT NULL,
            entity_id INTEGER, details TEXT, ip TEXT, created_at TEXT
        )",
        // Module 3 — M3 columns on students (SQLite ALTER ADD one at a time)
        "ALTER TABLE students ADD COLUMN first_name TEXT",
        "ALTER TABLE students ADD COLUMN last_name TEXT",
        "ALTER TABLE students ADD COLUMN gender TEXT",
        "ALTER TABLE students ADD COLUMN programme_level TEXT",
        "ALTER TABLE students ADD COLUMN academic_year_id INTEGER",
        "ALTER TABLE students ADD COLUMN class_id INTEGER",
        "ALTER TABLE students ADD COLUMN section_id INTEGER",
        "ALTER TABLE students ADD COLUMN admission_date TEXT",
        "ALTER TABLE students ADD COLUMN onboarding_status TEXT NOT NULL DEFAULT 'pending_enrolment'",
        "ALTER TABLE students ADD COLUMN login_enabled INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE students ADD COLUMN created_by INTEGER",
        "ALTER TABLE students ADD COLUMN upload_batch_id INTEGER",
        "ALTER TABLE students ADD COLUMN updated_at TEXT",
        // Module 3 — upload_batches
        "CREATE TABLE IF NOT EXISTS upload_batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            department_id INTEGER NOT NULL,
            uploaded_by INTEGER NOT NULL,
            original_filename TEXT NOT NULL,
            total_rows INTEGER NOT NULL DEFAULT 0,
            created_count INTEGER NOT NULL DEFAULT 0,
            duplicate_held_count INTEGER NOT NULL DEFAULT 0,
            failed_count INTEGER NOT NULL DEFAULT 0,
            created_at TEXT
        )",
        // Module 3 — duplicate_override_requests
        "CREATE TABLE IF NOT EXISTS duplicate_override_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            upload_batch_id INTEGER,
            source_row_number INTEGER,
            student_data TEXT NOT NULL,
            flagged_reason TEXT NOT NULL,
            existing_student_id INTEGER NOT NULL,
            requested_by INTEGER NOT NULL,
            reason_note TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'pending',
            reviewed_by INTEGER,
            reviewed_at TEXT,
            created_at TEXT
        )",
        // Module 4 — M4 columns on students
        "ALTER TABLE students ADD COLUMN enrolment_number TEXT",
        "ALTER TABLE students ADD COLUMN enrolment_serial INTEGER",
        "ALTER TABLE students ADD COLUMN enrolment_approval_status TEXT",
        "ALTER TABLE students ADD COLUMN enrolment_batch_id INTEGER",
        "ALTER TABLE students ADD COLUMN enrolment_approved_by INTEGER",
        "ALTER TABLE students ADD COLUMN enrolment_approved_at TEXT",
        // Module 4 — enrolment_batches
        "CREATE TABLE IF NOT EXISTS enrolment_batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            department_id INTEGER NOT NULL,
            academic_year_id INTEGER NOT NULL,
            generated_by INTEGER NOT NULL,
            student_count INTEGER NOT NULL DEFAULT 0,
            created_at TEXT
        )",
        // Module 5 — student_profiles
        "CREATE TABLE IF NOT EXISTS student_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL UNIQUE,
            blood_group TEXT, mother_tongue TEXT, religion TEXT, caste TEXT,
            caste_category TEXT, sub_caste TEXT, nationality TEXT DEFAULT 'Indian',
            place_of_birth TEXT, aadhaar_number TEXT, passport_photo_path TEXT,
            student_email TEXT, alternate_mobile TEXT,
            marital_status TEXT DEFAULT 'Single',
            physically_challenged INTEGER NOT NULL DEFAULT 0,
            disability_nature TEXT, first_graduate INTEGER, annual_family_income INTEGER,
            perm_address1 TEXT, perm_address2 TEXT, perm_city TEXT,
            perm_taluk_id INTEGER, perm_district_id INTEGER, perm_state_id INTEGER, perm_pincode TEXT,
            comm_same_as_perm INTEGER NOT NULL DEFAULT 0,
            comm_address1 TEXT, comm_address2 TEXT, comm_city TEXT,
            comm_taluk_id INTEGER, comm_district_id INTEGER, comm_state_id INTEGER, comm_pincode TEXT,
            family_situation TEXT, father_name TEXT, father_occupation TEXT, father_qualification TEXT,
            father_annual_income INTEGER, father_mobile TEXT, father_email TEXT,
            mother_name TEXT, mother_occupation TEXT, mother_qualification TEXT,
            mother_annual_income INTEGER, mother_mobile TEXT, mother_email TEXT,
            guardian_name TEXT, guardian_relationship TEXT, guardian_mobile TEXT,
            guardian_address TEXT, guardian_email TEXT,
            qual_sslc TEXT, qual_hsc TEXT, qual_ug TEXT, qual_diploma TEXT,
            qual_other_1 TEXT, qual_other_2 TEXT,
            qual_sslc_doc_path TEXT, qual_hsc_doc_path TEXT, qual_ug_doc_path TEXT,
            qual_diploma_doc_path TEXT,
            admission_type TEXT, entrance_exam_name TEXT, entrance_hall_ticket TEXT,
            entrance_rank_score TEXT, admission_number TEXT,
            community_cert_number TEXT, community_cert_path TEXT,
            transfer_cert_number TEXT, transfer_cert_path TEXT,
            conduct_cert_path TEXT, migration_cert_path TEXT, income_cert_path TEXT,
            nativity_cert_path TEXT, aadhaar_copy_path TEXT,
            bank_account_holder TEXT, bank_name TEXT, bank_branch TEXT,
            bank_account_number TEXT, bank_ifsc TEXT, bank_passbook_path TEXT,
            scholarship_applied INTEGER DEFAULT 0, scholarship_scheme TEXT, scholarship_app_number TEXT,
            form_completion_pct INTEGER NOT NULL DEFAULT 0,
            form_status TEXT NOT NULL DEFAULT 'incomplete',
            form_submitted_at TEXT, last_saved_at TEXT, created_at TEXT, updated_at TEXT
        )",
        // Geography tables (used by M2 tests and M5 address joins)
        "CREATE TABLE IF NOT EXISTS states (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'active',
            created_at TEXT
        )",
        "CREATE TABLE IF NOT EXISTS districts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            state_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'active',
            created_at TEXT
        )",
        "CREATE TABLE IF NOT EXISTS taluks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            district_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'active',
            created_at TEXT
        )",
    ];
}
