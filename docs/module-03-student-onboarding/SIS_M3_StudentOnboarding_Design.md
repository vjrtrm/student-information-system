# SIS — Module 3: Student Onboarding
## Stage 2: Design (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 3 of 12 — Student Onboarding
**Document stage:** Requirements ✅ → **Design (this document)** → _Tasks_
**Version:** 0.1 (Draft) · June 2026
**Status:** Awaiting review & approval
**Traces:** `SIS_M3_StudentOnboarding_Requirements.md` (Epics A–F)

---

## 1. Design goals

Translate the approved Module 3 requirements into a concrete, buildable design on the SIS stack (PHP 8.x MVC, MySQL 5.7, Bootstrap 5, PDO, PhpSpreadsheet). The design covers the component map, data model, processing flows, duplicate-resolution lifecycle, RBAC rules, screen behaviour, and validation. No tasks are broken out here — that is Stage 3.

---

## 2. Resolved design decisions (from open questions)

| # | Open question | Design decision |
|---|---------------|-----------------|
| 1 | Single-add by whom? | Any Department Staff or Dept Admin in their own department. |
| 2 | Duplicate override approval | **In-app** — Dept Admin reviews a pending-overrides queue; email notification (Module 7) is out of scope here; a dashboard badge is used instead. |
| 3 | File format | **.xlsx only.** CSV is deferred; .xlsx allows the reference sheet and in-file data hints. |
| 4 | Max rows per upload | **1,000 rows / 5 MB.** |
| 5 | Student email at onboarding | **Not captured here.** Email is a Module 5 field. Module 7 notifications rely on the email collected in Module 5. |
| 6 | Student login until enrolment released | **Blocked.** Students cannot log in until Module 4 releases their enrolment number (status `enrolment_assigned`). The `students.login_enabled` flag (default `0`) is flipped by Module 4. |

---

## 3. Component architecture (MVC)

```
Controllers/
  OnboardingController.php      // template download, upload, result, single-add,
                                //   duplicate-review, override approval/rejection,
                                //   onboarding list, institution summary

Helpers/
  SpreadsheetImport.php         // parse .xlsx via PhpSpreadsheet; return typed row array
  OnboardingValidator.php       // validate each row's fields against rules (§7)
  DuplicateDetector.php         // mobile uniqueness + name+DOB secondary check
  AuditLogger.php               // reused from M1/M2

Models/
  Student.php                   // create, findByMobile, findByNameDob, getList, updateStatus
  UploadBatch.php               // record audit entry per upload
  DuplicateOverrideRequest.php  // CRUD for override workflow

Views/
  onboarding/
    index.php           // department student list with status filters + search
    upload.php          // upload form
    result.php          // upload result summary + error download button
    duplicates.php      // held-row review: Skip / Request Override per row
    override_review.php // Dept Admin: approve / reject pending overrides
    add.php             // single-record form
    summary.php         // Institution Admin cross-dept summary card grid
```

Request path: `public/index.php` → router → `AuthMiddleware` → `RoleMiddleware` → `DeptScopeMiddleware` → `OnboardingController` → view.

---

## 4. Data model

### 4.1 `students` table (Module 3 columns)

Full student record grows in later modules; only Module 3 columns are defined here.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `first_name` | VARCHAR(100) NOT NULL | |
| `last_name` | VARCHAR(100) NOT NULL | |
| `dob` | DATE NOT NULL | login factor 2 |
| `mobile` | VARCHAR(10) NOT NULL UNIQUE | login factor 1 |
| `gender` | ENUM('male','female','other') NOT NULL | |
| `department_id` | INT FK→departments.id NOT NULL | |
| `programme_level` | ENUM('UG','PG') NOT NULL | derived from dept at create time; stored for query convenience |
| `academic_year_id` | INT FK→option_values.id NOT NULL | |
| `class_id` | INT FK→option_values.id NOT NULL | |
| `section_id` | INT FK→option_values.id NULL | |
| `admission_date` | DATE NOT NULL | |
| `status` | ENUM('pending_enrolment','enrolment_assigned','form_submitted','approved') NOT NULL DEFAULT 'pending_enrolment' | |
| `login_enabled` | TINYINT(1) NOT NULL DEFAULT 0 | flipped to 1 by Module 4 on enrolment release |
| `created_by` | INT FK→users.id NOT NULL | staff who created/uploaded |
| `upload_batch_id` | INT FK→upload_batches.id NULL | NULL for single-add records |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | |

Indexes: `UNIQUE(mobile)`, `INDEX(department_id)`, `INDEX(status)`, `INDEX(academic_year_id)`, `INDEX(first_name, last_name, dob)`.

### 4.2 `upload_batches` table

One row per upload attempt; used for audit and linking student rows to their source batch.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `department_id` | INT FK→departments.id NOT NULL | |
| `uploaded_by` | INT FK→users.id NOT NULL | |
| `original_filename` | VARCHAR(255) | for audit display |
| `total_rows` | INT NOT NULL | parsed row count (header excluded) |
| `created_count` | INT NOT NULL DEFAULT 0 | |
| `duplicate_held_count` | INT NOT NULL DEFAULT 0 | |
| `failed_count` | INT NOT NULL DEFAULT 0 | |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

### 4.3 `duplicate_override_requests` table

Persists held rows pending Dept Admin review. One row per held upload-row or single-add duplicate.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `upload_batch_id` | INT FK→upload_batches.id NULL | NULL for single-add duplicates |
| `source_row_number` | INT NULL | spreadsheet row number (for batch) |
| `student_data` | JSON NOT NULL | the full row payload (fields from §4.1) |
| `flagged_reason` | ENUM('mobile_exists','name_dob_exists','both') NOT NULL | |
| `existing_student_id` | INT FK→students.id NOT NULL | the conflicting record |
| `requested_by` | INT FK→users.id NOT NULL | staff who chose Override |
| `reason_note` | TEXT NOT NULL | mandatory justification |
| `status` | ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' |  |
| `reviewed_by` | INT FK→users.id NULL | |
| `reviewed_at` | DATETIME NULL | |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

Index: `INDEX(status)`, `INDEX(upload_batch_id)`.

---

## 5. Processing flows

### 5.1 Template download

```
Staff → GET /onboarding/template
  OnboardingController::downloadTemplate()
    SpreadsheetImport::buildTemplate(department, active option_values)
    → output .xlsx (Content-Disposition: attachment)
```

Template Sheet 1 ("Upload Here"): columns — First Name, Last Name, Date of Birth (DD/MM/YYYY), Mobile, Gender, Academic Year, Class, Section (optional), Admission Date (DD/MM/YYYY).
Template Sheet 2 ("Valid Values"): Department name (pre-filled, read-only), Gender options, Academic Year options, Class options, Section options — pulled live from master data.

### 5.2 Bulk upload

```
Staff → POST /onboarding/upload (multipart .xlsx)
  OnboardingController::upload()
    1. Validate file: MIME = application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,
       extension = .xlsx, size ≤ 5 MB, row count ≤ 1,000. Hard-fail entire upload if any check fails.
    2. SpreadsheetImport::parse($file) → array of raw rows.
    3. UploadBatch::create(...) → $batchId  (department, uploader, filename, total_rows).
    4. For each row:
         a. OnboardingValidator::validate($row) → $errors[]
         b. If $errors: mark row as FAILED; continue.
         c. DuplicateDetector::check($row) → $duplicateType | null
         d. If duplicate: mark row as HELD; insert duplicate_override_request (status=pending) with
            student_data JSON; continue.
         e. Else: INSERT into students (within per-row try/catch; unique constraint violation →
            treated as FAILED, not held).
    5. Update upload_batches counts (created, held, failed).
    6. Redirect → GET /onboarding/result/{batchId}
```

**Transaction scope:** each student INSERT is its own transaction. A single-row DB failure does not roll back the batch.

### 5.3 Result display & error report download

```
Staff → GET /onboarding/result/{batchId}
  OnboardingController::result($batchId)
    Fetch UploadBatch row (department-scoped guard).
    Fetch duplicate_override_requests for this batch (status=pending).
    Fetch failed rows from session (stored during upload; cleared after first view).
    Render result.php: summary banner + three tables (Created / Held / Failed).

Staff → GET /onboarding/result/{batchId}/errors.xlsx
  OnboardingController::downloadErrors($batchId)
    Rebuild failed-row data from session; output .xlsx with original columns + "Error" column.
```

### 5.4 Duplicate resolution (staff side)

```
Staff → GET /onboarding/duplicates/{batchId}
  OnboardingController::reviewDuplicates($batchId)
    Fetch pending duplicate_override_requests for batch (dept-scoped).
    Render duplicates.php: table of held rows with existing-record details.

Staff → POST /onboarding/duplicates/{batchId}/resolve
  Payload: array of { override_request_id, action: 'skip'|'override', reason_note }
  OnboardingController::resolveDuplicates()
    For each item:
      action=skip  → update override_request.status = 'rejected'; audit log (skipped by staff).
      action=override → validate reason_note not empty; update reason_note on record; status stays
                        'pending'; AuditLogger::log('override_requested', ...).
    Redirect → /onboarding/result/{batchId} (refresh counts).
```

### 5.5 Override approval (Dept Admin)

```
Dept Admin → GET /onboarding/overrides
  OnboardingController::pendingOverrides()
    Fetch duplicate_override_requests WHERE status='pending' AND dept matches admin's dept.
    Render override_review.php: table with student data, existing record link, reason note.

Dept Admin → POST /onboarding/overrides/{id}/approve
  OnboardingController::approveOverride($id)
    Begin transaction:
      INSERT student from override_request.student_data.
      UPDATE override_request: status='approved', reviewed_by, reviewed_at.
    Commit.
    AuditLogger::log('override_approved', actor=admin, student=created_id, request=id).
    Update upload_batches.created_count++ (if batch-linked).

Dept Admin → POST /onboarding/overrides/{id}/reject
  OnboardingController::rejectOverride($id)
    UPDATE override_request: status='rejected', reviewed_by, reviewed_at.
    AuditLogger::log('override_rejected', ...).
```

### 5.6 Single-record add

```
Staff → GET /onboarding/add → render add.php (form)
Staff → POST /onboarding/add
  OnboardingController::store()
    1. OnboardingValidator::validate($post) → $errors → re-render form if any.
    2. DuplicateDetector::check($post) → if duplicate:
         Show inline warning with existing-record summary.
         If staff chooses Override: require reason_note; insert duplicate_override_request
         (upload_batch_id=NULL, source_row_number=NULL); redirect to overrides queue.
    3. If clean: INSERT student; AuditLogger::log('student_created', actor, student_id).
    4. Redirect → /onboarding with success flash.
```

### 5.7 Status lifecycle transitions

| Transition | Triggered by | Module |
|------------|-------------|--------|
| (new) → `pending_enrolment` | Student INSERT | M3 |
| `pending_enrolment` → `enrolment_assigned` | Enrolment number release + `login_enabled=1` | M4 |
| `enrolment_assigned` → `form_submitted` | Student submits info form | M5 |
| `form_submitted` → `approved` | Staff/Admin approves submission | M6 |

---

## 6. RBAC & department scoping

| Route | Allowed roles | Dept scope |
|-------|--------------|------------|
| `GET /onboarding` (list) | `staff`, `dept_admin`, `institution_admin` | staff/admin → own dept; inst_admin → all |
| `GET /onboarding/template` | `staff`, `dept_admin` | own dept pre-filled |
| `POST /onboarding/upload` | `staff`, `dept_admin` | file rows validated against own dept |
| `GET /onboarding/result/{id}` | `staff`, `dept_admin` | own dept batches only |
| `GET/POST /onboarding/duplicates/{id}` | `staff`, `dept_admin` | own dept |
| `GET/POST /onboarding/overrides` | `dept_admin` | own dept |
| `POST /onboarding/overrides/{id}/approve|reject` | `dept_admin` | own dept |
| `GET /onboarding/add` + `POST` | `staff`, `dept_admin` | own dept |
| `GET /onboarding/summary` | `institution_admin` | all depts |

`DeptScopeMiddleware` injects `$_SESSION['department_id']` into every query. `institution_admin` bypasses the department filter on list/summary views only.

---

## 7. Validation rules

Applied identically for bulk rows and single-add POST. All checked server-side.

| Field | Rule |
|-------|------|
| First Name | Required; 1–100 chars; letters, spaces, hyphens, apostrophes only (`/^[\p{L}\s\-\']+$/u`) |
| Last Name | Same as First Name |
| Date of Birth | Required; format DD/MM/YYYY; valid calendar date; age ≥ 15 years from admission date |
| Mobile | Required; exactly 10 digits (`/^\d{10}$/`) |
| Gender | Required; value in `['male','female','other']` |
| Department | Required; must be active; must match staff's own `department_id` |
| Academic Year | Required; must be an active option_value in the `academic_year` list |
| Class | Required; must be an active option_value in the `class` list |
| Section | Optional; if provided, must be active option_value in the `section` list |
| Admission Date | Required; format DD/MM/YYYY; valid date; ≤ today |

Duplicate check runs only after field validation passes. A row with validation errors is never tested for duplicates.

**CSRF:** the upload form and all POST actions carry a CSRF token validated via `Csrf::verify()` (reused from M1).

---

## 8. Screen behaviour & messages

### 8.1 Upload form (`upload.php`)
- File input; accepts `.xlsx` only (HTML `accept` attribute + server-side MIME check).
- Client-side size check (JS): warn if > 5 MB before submission.
- Submit button disabled while upload is in progress (JS spinner).

### 8.2 Result screen (`result.php`)
- **Summary banner:** `X created · Y held for review · Z failed — out of N rows.`
- Three collapsible panels:
  - **Created** (green): row count only (no PII listed for brevity).
  - **Held — pending duplicate review** (amber): row number, name, mobile, flag reason, link to existing record.
  - **Failed** (red): row number, field name, error message for each failure.
- "Download Error Report (.xlsx)" button (shown only if Z > 0).
- "Review Held Rows" button (shown only if Y > 0) → `/onboarding/duplicates/{batchId}`.

### 8.3 Duplicate review (`duplicates.php`)
- Table: Row #, Uploaded Name, Uploaded Mobile, Flag Reason, Existing Record (name + serial/mobile), Action.
- Action column: radio buttons — **Skip** | **Override** (shows text area for Reason when selected).
- Inline validation: Override requires non-empty reason (JS + server-side).
- Submit sends all rows in one POST.

### 8.4 Override review (`override_review.php`) — Dept Admin
- Table: Student Name, Mobile, DOB, Flag Reason, Existing Record, Staff Reason Note, Requested by, Date.
- Per-row buttons: **Approve** (green) | **Reject** (red), each opens a confirmation modal.
- Badge count shown on department admin dashboard nav item (count of `pending` overrides for their dept).

### 8.5 Single-add form (`add.php`)
- Standard Bootstrap form; Academic Year / Class / Section are `<select>` populated from master data.
- On POST, if duplicate detected: inline amber warning panel above form with existing-record summary; "Override" checkbox + reason textarea revealed; form retains entered values.

### 8.6 Onboarding list (`index.php`)
- Table: Name, Mobile, Dept (inst_admin only), Programme, Academic Year, Class, Status, Added by, Date.
- Filters: Status (multi-select), Academic Year, Department (inst_admin only).
- Search: by name or mobile (LIKE query, server-side).
- Pagination: 50 rows per page.

### 8.7 Institution summary (`summary.php`)
- Bootstrap stat cards per department: Total · Pending Enrolment · Enrolment Assigned · Form Submitted · Approved.
- Filter: Academic Year (select).

---

## 9. Configuration parameters

| Key | Default | Notes |
|-----|---------|-------|
| `onboarding.max_rows` | 1000 | Max rows per upload |
| `onboarding.max_file_mb` | 5 | Max upload file size (MB) |
| `onboarding.min_age_years` | 15 | Minimum student age at admission |

Stored in `config/onboarding.php`, loaded via the existing config helper.

---

## 10. Edge cases

| Scenario | Handling |
|----------|----------|
| Staff uploads file for wrong department | Each row's department field is validated against staff's own `department_id`; mismatched rows fail with "Department does not match your department." |
| File has header row only (no data) | Processed as 0 rows; flash: "The file contained no data rows." |
| Mobile passes field validation but hits DB unique constraint on INSERT | Caught in try/catch; treated as a FAILED row (not held), error: "Mobile number already exists (concurrent upload conflict)." |
| Dept Admin reviews override for a batch from a different dept | `DeptScopeMiddleware` returns 403. |
| Staff resolves duplicates after Dept Admin already approved one | Override record checked on resolve; if already `approved`, skip silently with a notice. |
| Re-upload of an error-report file | Processed identically to any other upload; previously created rows will be caught as mobile-duplicates; staff can Skip them. |
| Section option not in master data | Row fails validation: "Section: value not found in active options." |
| Concurrent uploads causing duplicate mobile | Per-row try/catch on INSERT; unique constraint violation → FAILED, not HELD. |

---

## 11. Traceability

| Requirement | Design element |
|-------------|---------------|
| A1 — Download template | §5.1, `OnboardingController::downloadTemplate`, `SpreadsheetImport::buildTemplate` |
| B1 — Bulk upload + row result | §5.2, §5.3, `upload_batches`, result.php |
| B2 — Duplicate detection & resolution | §5.4, §5.5, `duplicate_override_requests`, `DuplicateDetector`, duplicates.php, override_review.php |
| B3 — Re-upload error report | §5.3 error download, session-cached failed rows |
| C1 — Single-record add | §5.6, add.php |
| D1 — Onboarding field set | §4.1 students table columns, §7 validation rules |
| E1 — Status lifecycle & list | §5.7 status table, §8.6 index.php |
| E2 — Institution summary | §8.7 summary.php |
| F1 — Audit logging | `AuditLogger::log` calls in all write flows (§5.2–5.6) |
| F2 — Data integrity | Per-row transactions, DB unique index on mobile, no permanent file storage |

---

## 12. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval of this Design document, the next step is **Stage 3: Tasks** for Module 3 — a task-by-task breakdown with estimates, dependencies, and done-when criteria, submitted for your review before any implementation begins.
