# SIS — Module 5: Student Information Form
## Stage 2: Design (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 5 of 12 — Student Information Form
**Document stage:** Requirements ✅ → **Design (this document)** → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M5_StudentInfoForm_Requirements.md`

---

## 1. Design goals

Translate the approved M5 requirements into a buildable design on the SIS stack (PHP 8.x MVC, MySQL 5.7, Bootstrap 5, PDO). Key goals:

- One saveable, multi-section form across a single student profile row (linked table).
- Clean state machine: `incomplete` → `submitted` (locked). No edit state after submission without M6.
- Conditional field visibility and mandatory enforcement driven by server-side rules, mirrored in JS for UX.
- File uploads handled per-field with server-side validation; stored locally.
- Completion percentage computed server-side on every save; cached on the profile row.

---

## 2. Resolved design decisions (from open questions)

| # | Open question | Decision |
|---|---------------|----------|
| 1 | Wide `students` table vs separate `student_profiles`? | **Separate `student_profiles` table (1:1 with students).** Keeps `students` lean; avoids ~75 nullable columns on the core auth/enrolment table; simpler to extend in M10 Field Management. |
| 2 | Completion % formula | **`(filled required fields) / (total required fields applicable to this student) × 100`**, rounded down. Applicable required fields vary by programme_level (UG/PG) and Family Situation. Cached as `form_completion_pct TINYINT` on `student_profiles`, updated on every save. |
| 3 | Bank & Scholarship section mandatory? | **Fully optional.** No fields in Section 6 are required. The section is always shown but none of its fields block submission. |
| 4 | Field list final count | Confirmed as specified in Requirements §2.1. Total: 81 student-editable fields + 3 internal tracking fields. |
| 5 | Lateral entry / UG Degree row | **Add a Diploma / Lateral Entry row** (same sub-fields as other academic rows) shown when Admission Type = `Lateral Entry`, in addition to the UG Degree row for PG students. The two are independent conditional rows. |
| 6 | Aadhaar display | **Masked on display** — stored in full, rendered as `XXXX-XXXX-NNNN` (last 4 digits visible) after entry. |
| 7 | Guardian section default | **Resolved in Requirements** — controlled by Family Situation selector. |

---

## 3. Component architecture (MVC)

```
Controllers/
  StudentFormController.php     // show, save, submit, staffView
                                //   (one controller; form is student-owned)

Models/
  StudentProfile.php            // findByStudent, upsert, computeCompletion,
                                //   getRequiredFields, submit
  Student.php (extend M4)       // no new methods; onboarding_status updated here

Helpers/
  FormFieldRules.php            // getApplicableFields(profile): array
                                //   returns field list with required/optional/hidden
                                //   based on programme_level + family_situation + admission_type
  DocumentUploadHandler.php     // validate, store, delete — wraps move_uploaded_file

Views/
  student-form/
    show.php          // multi-section saveable form (student)
    readonly.php      // read-only summary (student post-submit; staff/admin view)
    progress.php      // partial: progress bar + section checklist (included in show.php)
```

Request path: `public/index.php` → router → `AuthMiddleware` → `RoleMiddleware` → `StudentFormController` → view.

---

## 4. Data model

### 4.1 New table: `student_profiles`

One row per student, created on first save (upsert pattern). All columns nullable at creation; populated as the student fills the form.

**Section 1 — Personal**

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `student_id` | INT UNIQUE FK→students.id | 1:1 link |
| `blood_group` | VARCHAR(5) NULL | A+, A−, … |
| `mother_tongue` | VARCHAR(100) NULL | |
| `religion` | VARCHAR(100) NULL | option list value |
| `caste` | VARCHAR(100) NULL | |
| `caste_category` | ENUM('OC','OBC','BC','MBC','SC','ST','Others') NULL | |
| `sub_caste` | VARCHAR(100) NULL | |
| `nationality` | VARCHAR(100) NULL DEFAULT 'Indian' | |
| `place_of_birth` | VARCHAR(150) NULL | |
| `aadhaar_number` | VARCHAR(12) NULL | stored plain; masked on display |
| `passport_photo_path` | VARCHAR(255) NULL | relative path under storage/uploads/ |
| `student_email` | VARCHAR(255) NULL | |
| `alternate_mobile` | VARCHAR(15) NULL | |
| `marital_status` | ENUM('Single','Married','Other') NULL DEFAULT 'Single' | |
| `physically_challenged` | TINYINT(1) NOT NULL DEFAULT 0 | |
| `disability_nature` | VARCHAR(255) NULL | conditional on physically_challenged=1 |
| `first_graduate` | TINYINT(1) NULL | |
| `annual_family_income` | INT UNSIGNED NULL | in ₹ |

**Section 2 — Address**

| Column | Type |
|--------|------|
| `perm_address1` | VARCHAR(255) NULL |
| `perm_address2` | VARCHAR(255) NULL |
| `perm_city` | VARCHAR(100) NULL |
| `perm_taluk_id` | INT NULL FK→taluks.id |
| `perm_district_id` | INT NULL FK→districts.id |
| `perm_state_id` | INT NULL FK→states.id |
| `perm_pincode` | CHAR(6) NULL |
| `comm_same_as_perm` | TINYINT(1) NOT NULL DEFAULT 0 | |
| `comm_address1` | VARCHAR(255) NULL |
| `comm_address2` | VARCHAR(255) NULL |
| `comm_city` | VARCHAR(100) NULL |
| `comm_taluk_id` | INT NULL FK→taluks.id |
| `comm_district_id` | INT NULL FK→districts.id |
| `comm_state_id` | INT NULL FK→states.id |
| `comm_pincode` | CHAR(6) NULL |

**Section 3 — Parent / Guardian**

| Column | Type | Notes |
|--------|------|-------|
| `family_situation` | ENUM('both_parents','single_parent_father','single_parent_mother','guardian') NULL | |
| `father_name` | VARCHAR(150) NULL | required in all situations |
| `father_occupation` | VARCHAR(150) NULL | |
| `father_qualification` | VARCHAR(150) NULL | |
| `father_annual_income` | INT UNSIGNED NULL | |
| `father_mobile` | VARCHAR(15) NULL | |
| `father_email` | VARCHAR(255) NULL | |
| `mother_name` | VARCHAR(150) NULL | |
| `mother_occupation` | VARCHAR(150) NULL | |
| `mother_qualification` | VARCHAR(150) NULL | |
| `mother_annual_income` | INT UNSIGNED NULL | |
| `mother_mobile` | VARCHAR(15) NULL | |
| `mother_email` | VARCHAR(255) NULL | |
| `guardian_name` | VARCHAR(150) NULL | |
| `guardian_relationship` | VARCHAR(100) NULL | |
| `guardian_mobile` | VARCHAR(15) NULL | |
| `guardian_address` | TEXT NULL | |
| `guardian_email` | VARCHAR(255) NULL | |

**Section 4 — Academic Background**

Each qualification row is stored as a JSON blob for flexibility (avoids 8 columns × 4 rows = 32 columns). The JSON schema is fixed and validated server-side.

| Column | Type | Notes |
|--------|------|-------|
| `qual_sslc` | JSON NULL | `{exam, board, institution, year, percentage, stream, medium, state}` |
| `qual_hsc` | JSON NULL | same schema |
| `qual_ug` | JSON NULL | PG students only |
| `qual_diploma` | JSON NULL | Lateral Entry students only |
| `qual_other_1` | JSON NULL | optional |
| `qual_other_2` | JSON NULL | optional |
| `qual_sslc_doc_path` | VARCHAR(255) NULL | |
| `qual_hsc_doc_path` | VARCHAR(255) NULL | |
| `qual_ug_doc_path` | VARCHAR(255) NULL | |
| `qual_diploma_doc_path` | VARCHAR(255) NULL | |

**Section 5 — Entrance & Admission**

| Column | Type |
|--------|------|
| `admission_type` | ENUM('management','government','nri','lateral_entry') NULL |
| `entrance_exam_name` | VARCHAR(150) NULL |
| `entrance_hall_ticket` | VARCHAR(100) NULL |
| `entrance_rank_score` | VARCHAR(50) NULL |
| `admission_number` | VARCHAR(100) NULL |
| `community_cert_number` | VARCHAR(100) NULL |
| `community_cert_path` | VARCHAR(255) NULL |
| `transfer_cert_number` | VARCHAR(100) NULL |
| `transfer_cert_path` | VARCHAR(255) NULL |
| `conduct_cert_path` | VARCHAR(255) NULL |
| `migration_cert_path` | VARCHAR(255) NULL |
| `income_cert_path` | VARCHAR(255) NULL |
| `nativity_cert_path` | VARCHAR(255) NULL |
| `aadhaar_copy_path` | VARCHAR(255) NULL |

**Section 6 — Bank & Scholarship**

| Column | Type |
|--------|------|
| `bank_account_holder` | VARCHAR(150) NULL |
| `bank_name` | VARCHAR(150) NULL |
| `bank_branch` | VARCHAR(150) NULL |
| `bank_account_number` | VARCHAR(30) NULL |
| `bank_ifsc` | CHAR(11) NULL |
| `bank_passbook_path` | VARCHAR(255) NULL |
| `scholarship_applied` | TINYINT(1) NULL DEFAULT 0 |
| `scholarship_scheme` | VARCHAR(200) NULL |
| `scholarship_app_number` | VARCHAR(100) NULL |

**Tracking columns**

| Column | Type | Notes |
|--------|------|-------|
| `form_completion_pct` | TINYINT UNSIGNED NOT NULL DEFAULT 0 | recomputed on each save |
| `form_status` | ENUM('incomplete','submitted') NOT NULL DEFAULT 'incomplete' | |
| `form_submitted_at` | DATETIME NULL | |
| `last_saved_at` | DATETIME NULL | |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP | |

**Indexes:**
```sql
UNIQUE KEY uq_student_profiles_student (student_id)
KEY idx_student_profiles_status (form_status)
CONSTRAINT fk_sp_student   FOREIGN KEY (student_id)       REFERENCES students(id)
CONSTRAINT fk_sp_perm_taluk   FOREIGN KEY (perm_taluk_id)   REFERENCES taluks(id)
CONSTRAINT fk_sp_perm_dist    FOREIGN KEY (perm_district_id) REFERENCES districts(id)
CONSTRAINT fk_sp_perm_state   FOREIGN KEY (perm_state_id)    REFERENCES states(id)
CONSTRAINT fk_sp_comm_taluk   FOREIGN KEY (comm_taluk_id)   REFERENCES taluks(id)
CONSTRAINT fk_sp_comm_dist    FOREIGN KEY (comm_district_id) REFERENCES districts(id)
CONSTRAINT fk_sp_comm_state   FOREIGN KEY (comm_state_id)    REFERENCES states(id)
```

### 4.2 No changes to `students` table in this migration

`onboarding_status` is updated (to `form_submitted`) by `StudentProfile::submit()` using the existing `Student::updateStatus()` method from M3.

---

## 5. Processing flows

### 5.1 Form load (GET /student/form)

```
Student → GET /student/form
  StudentFormController::show()
    1. AuthMiddleware; RoleMiddleware(['student']).
    2. $studentId = Auth::id() → Student::find($studentId).
    3. StudentProfile::findByStudent($studentId) → $profile (null if never saved).
    4. FormFieldRules::getApplicableFields($profile, $student) → $rules
       (array of field metadata: key, label, required, visible, section).
    5. Load dropdown data: option lists (blood group, religion, etc.),
       states/districts/taluks for address selects.
    6. If $profile->form_status === 'submitted' → redirect to readonly view.
    7. render('student-form/show', [profile, rules, student, dropdowns])
```

### 5.2 Partial save (POST /student/form/save)

```
Student → POST /student/form/save
  StudentFormController::save()
    1. requireCsrf(); RoleMiddleware(['student']).
    2. $studentId = Auth::id().
    3. Guard: if profile exists and form_status='submitted' → 403 (form locked).
    4. Sanitise + validate each posted field against $rules (type, max length;
       required fields NOT enforced here — partial save allows blanks).
    5. Handle file uploads for any $_FILES entries:
         DocumentUploadHandler::handle($field, $file, $studentId)
           a. Validate MIME type (whitelist) + size ≤ 2 MB server-side.
           b. Store at storage/uploads/students/{studentId}/{field}_{timestamp}.{ext}
           c. Delete old file for this field if one exists.
           d. Return relative path.
    6. StudentProfile::upsert($studentId, $data) — INSERT ... ON DUPLICATE KEY UPDATE.
    7. $pct = StudentProfile::computeCompletion($studentId, $profile, $student).
    8. UPDATE student_profiles SET form_completion_pct=?, last_saved_at=NOW().
    9. Flash success: "Progress saved ({$pct}% complete)."
   10. Redirect → GET /student/form (reload form with updated data).
```

### 5.3 Submit (POST /student/form/submit)

```
Student → POST /student/form/submit
  StudentFormController::submit()
    1. requireCsrf(); RoleMiddleware(['student']).
    2. Guard: profile form_status already 'submitted' → redirect readonly with info flash.
    3. Run full validation: ALL required fields (per $rules) must be non-null/non-empty.
       If any fail → flash danger listing missing fields; redirect back to form.
    4. BEGIN TRANSACTION:
         UPDATE student_profiles SET form_status='submitted', form_submitted_at=NOW(),
                form_completion_pct=100, last_saved_at=NOW() WHERE student_id=?
         UPDATE students SET onboarding_status='form_submitted' WHERE id=?
    5. COMMIT.
    6. MasterAuditLogger::log('student_form_submitted','student_profile',$profile->id,[...]).
    7. Flash success: "Your form has been submitted successfully."
    8. Redirect → GET /student/form/view (readonly summary).
```

### 5.4 Completion percentage computation

`FormFieldRules::getApplicableFields()` returns the list of required fields for this student (accounting for programme_level, family_situation, admission_type). Completion = count of required fields that are non-null and non-empty string in the profile row, divided by total applicable required fields, × 100, floored.

File fields: considered filled if the corresponding `*_path` column is non-null.
JSON fields (academic rows): considered filled if the JSON object has all of `{exam, board, institution, year, percentage}` non-empty.

### 5.5 Staff / admin read-only view (GET /student/form/{studentId}/view)

```
Staff/Admin → GET /student/form/{studentId}/view
  StudentFormController::staffView($studentId)
    1. RoleMiddleware(['staff','dept_admin','institution_admin']).
    2. DepartmentScopeMiddleware::assertDepartment($student['department_id'])
       (inst_admin bypasses dept check).
    3. Fetch student + profile. Render readonly.php with all fields displayed.
    4. Show completion % and form_status badge (Incomplete / Submitted).
```

---

## 6. RBAC & department scoping

| Route | Allowed roles | Dept scope |
|-------|--------------|------------|
| `GET /student/form` | `student` | own record only |
| `POST /student/form/save` | `student` | own record only |
| `POST /student/form/submit` | `student` | own record only |
| `GET /student/form/view` | `student` | own record only (read-only after submit) |
| `GET /student/form/{id}/view` | `staff`, `dept_admin`, `institution_admin` | dept-scoped; inst_admin sees all |

Students can only access their own profile; the controller enforces `Auth::id() === $studentId` on all student-facing routes.

---

## 7. Validation & security

### 7.1 Server-side validation rules

| Field type | Rule |
|------------|------|
| Text fields | `strip_tags`; max length per column definition |
| Mobile numbers | 10-digit numeric |
| PIN code | 6-digit numeric |
| Aadhaar | 12-digit numeric; stored plain |
| Email | `filter_var(FILTER_VALIDATE_EMAIL)` |
| IFSC | `/^[A-Z]{4}0[A-Z0-9]{6}$/` |
| Numeric (income, etc.) | Cast to int; min 0 |
| File uploads | MIME whitelist server-side (not just extension); size ≤ 2,097,152 bytes; passport photo: `image/jpeg`, `image/png` only; documents: + `application/pdf`, `image/webp` |
| JSON qual fields | Each sub-field sanitised individually before encoding |

### 7.2 Partial save vs full validation

- **Partial save** (`/save`): validates format/type of fields that are present but does **not** enforce required fields. Any required field may be blank.
- **Submit** (`/submit`): enforces all required fields. Returns to form with an inline list of missing/invalid fields if validation fails.

### 7.3 File storage

- Base path: `storage/uploads/students/{studentId}/`
- Filename: `{field_key}_{unixtime}.{ext}` — e.g. `passport_photo_1719500000.jpg`
- Old file for a field is deleted on replace (before storing new file).
- Submitted files are immutable: `DocumentUploadHandler` refuses replacements when `form_status = 'submitted'`.

### 7.4 Aadhaar masking

Stored as 12-digit string. Display helper: `maskAadhaar(string $n): string` → `'XXXX-XXXX-' . substr($n, 8, 4)`. Applied in view layer only; raw value never printed.

---

## 8. Screen behaviour & messages

### 8.1 Form page (`show.php`)

- Six Bootstrap accordion sections, each independently saveable via a per-section Save button that submits the whole form (single form tag wrapping all sections; JS scrolls back to active section after reload).
- Progress bar at top: `{pct}%` complete, colour-coded (red < 50, amber 50–79, green ≥ 80).
- Section checklist in a sidebar or top-of-page strip: tick/cross per section based on whether all required fields in that section are filled.
- Required fields marked with `*` in labels.
- Conditional fields (communication address, disability detail, family/guardian sub-fields, UG degree row, Diploma row, scholarship fields) shown/hidden via JS on the client; server enforces the same rules on save/submit.
- "Submit Form" button: disabled when `form_completion_pct < 100`; enabled only when all required fields are complete. Shows tooltip "Complete all required fields to submit" when disabled.
- Clicking Submit opens a Bootstrap modal: "Once submitted, your form cannot be edited without requesting a change. Proceed?" with Confirm and Cancel buttons.

### 8.2 Read-only view (`readonly.php`)

- Displays all sections as styled definition lists (label: value).
- Passport photo shown as thumbnail (100×100 px).
- Document fields show filename as a download link (opens file in new tab).
- Aadhaar displayed masked.
- Status badge: green "Submitted" with submission date, or amber "Incomplete ({pct}%)".
- "Request a Change" button visible (links to M6; placeholder in M5).

### 8.3 Staff view

- Same `readonly.php` template, rendered from `staffView()`.
- Header shows: student name, enrolment number (if assigned), department, academic year, form status badge, completion %.

### 8.4 Flash messages

| Event | Type | Message |
|-------|------|---------|
| Partial save success | success | "Progress saved (72% complete)." |
| Save with upload error | danger | "File for [Field Name] exceeds 2 MB limit. Other changes have been saved." |
| Submit — missing fields | danger | "Please complete the following required fields: [list]." |
| Submit success | success | "Your form has been submitted successfully." |
| Form already submitted | info | "Your form has been submitted. Use Request a Change to make edits." |

---

## 9. Configuration parameters

| Key | Default | Notes |
|-----|---------|-------|
| `form.upload_max_bytes` | `2097152` | 2 MB; stored in `config/form.php` |
| `form.upload_allowed_doc_mimes` | `['application/pdf','image/jpeg','image/png','image/webp']` | |
| `form.upload_allowed_photo_mimes` | `['image/jpeg','image/png']` | passport photo only |
| `form.upload_path` | `storage/uploads/students/` | relative to project root |

---

## 10. Edge cases

| Scenario | Handling |
|----------|----------|
| Student saves Section 3 with `family_situation = guardian` but no guardian name | Partial save accepted; required-field check runs only on Submit. |
| Student changes `family_situation` from `both_parents` to `guardian` after filling mother fields | Mother fields hidden in UI; server ignores posted values for hidden fields; previously saved mother data is retained in DB (not deleted) in case student switches back. On Submit, hidden fields are not validated. |
| Two browser tabs saving simultaneously | Last write wins (upsert by student_id); no data loss beyond the race itself. Acceptable for v1. |
| File upload succeeds but profile save fails (DB error) | Uploaded file is orphaned on disk; profile not updated. Acceptable for v1 (file cleaned up manually). |
| Student submits with exactly 100% but a required file was deleted from disk | Submit validates non-null path in DB, not file existence on disk. Acceptable for v1. |
| Staff views a profile that has never been saved (no `student_profiles` row) | `staffView` renders an empty read-only form with "Not yet started" status; no error. |
| `comm_same_as_perm` is checked, then permanent address changes | Communication address columns are cleared server-side on save when same_as_perm=1, then re-copied from permanent values. |

---

## 11. Traceability

| Requirement | Design element |
|-------------|---------------|
| A1 — Partial save | §5.2 `save()` flow; upsert pattern; no required-field enforcement |
| A2 — Completion progress | §5.4 `computeCompletion()`; `form_completion_pct` cached on profile |
| B1 — Document uploads | §5.2 `DocumentUploadHandler`; §7.3 file storage |
| B1 — Passport photo image-only | §7.1 MIME whitelist `upload_allowed_photo_mimes` |
| C1 — Submit & lock | §5.3 `submit()` flow; `form_status='submitted'`; onboarding_status update |
| C2 — Read-only after submit | `GET /student/form/view` → `readonly.php` |
| D1 — Staff read-only view | `GET /student/form/{id}/view` → `staffView()` → `readonly.php` |
| Aadhaar masking | §7.4 `maskAadhaar()` view helper |
| Family Situation conditional rules | `FormFieldRules::getApplicableFields()` — §5.4; §10 edge case for hidden field data retention |
| Father's Name always required | `FormFieldRules` returns `father_name` as required regardless of `family_situation` |
| Defaults (Marital Status = Single, Physically Challenged = No) | §4.1 column defaults + JS pre-selection on form load |
| Lateral Entry Diploma row | §2 resolved decision #5; `qual_diploma` column; conditional on `admission_type = 'lateral_entry'` |

---

## 12. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, the next step is **Stage 3: Tasks** — a task-by-task breakdown with estimates, dependencies, and done-when criteria, submitted for your review before implementation.
