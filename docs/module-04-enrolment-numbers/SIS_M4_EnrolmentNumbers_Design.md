# SIS — Module 4: Enrolment Number Generation & Approval
## Stage 2: Design (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 4 of 12 — Enrolment Number Generation & Approval
**Document stage:** Requirements ✅ → **Design (this document)** → _Tasks_
**Version:** 0.1 (Draft) · June 2026
**Status:** Awaiting review & approval
**Traces:** `SIS_M4_EnrolmentNumbers_Requirements.md` (Epics A–D)

---

## 1. Design goals

Translate the approved Module 4 requirements into a concrete, buildable design on the SIS stack (PHP 8.x MVC, MySQL 5.7, Bootstrap 5, PDO). The design covers: enrolment number generation algorithm, the batch-as-container model, per-number approval (individual and bulk), student visibility rules, RBAC, screen behaviour, and the data model. Reuses M1–M3 conventions (Db, Csrf, RoleMiddleware, DepartmentScopeMiddleware, MasterAuditLogger).

---

## 2. Resolved design decisions (from open questions)

| # | Open question | Design decision |
|---|---------------|-----------------|
| 1 | Batch cap | **Unlimited** — all `pending_enrolment` students in the dept+year are included in one batch. |
| 2 | Serial padding | **3 digits, fixed** (e.g. `001`, `041`, `999`). Not configurable in v1. |
| 3 | Who can generate? | **Any Department Staff** in their own department. |
| 4 | Partial batch status label | **`In Progress`** — shown when ≥ 1 number is approved but others remain pending. |
| 5 | Student dashboard data source | Reads `enrolment_number` directly from `students` table; shown only when `enrolment_approval_status = 'approved'`. |

---

## 3. Enrolment number formula

```
Format : {YY}{Level}{DeptCode}{serial}
Example: 24UBCA041   (2024-25, UG, BCA, serial 041)
         26PMCA100   (2026-27, PG, MCA, serial 100)

YY        = substr(academic_year_value, 2, 2)
            e.g. "2024-25" → "24"   ("20" + "24" → take chars at index 2,3)
Level     = departments.level == 'UG' ? 'U' : 'P'
DeptCode  = departments.code  (stored uppercase, e.g. "BCA", "MCA")
serial    = str_pad($nextSerial, 3, '0', STR_PAD_LEFT)
```

**Serial derivation:**

```
SELECT COALESCE(MAX(enrolment_serial), 0) + 1
FROM   students
WHERE  department_id   = :deptId
  AND  academic_year_id = :ayId
  AND  enrolment_serial IS NOT NULL
```

This query runs inside a transaction with a table-level lock on `students` rows for the dept+year to prevent concurrent generation producing duplicate serials.

---

## 4. Component architecture (MVC)

```
Controllers/
  EnrolmentController.php       // index, generateForm, generate, batchDetail,
                                //   approveAll, approveSelected, summary

Models/
  EnrolmentBatch.php            // create, find, findByDept, countByStatus,
                                //   deriveStatus, summaryByDept
  Student.php (extend M3)       // addEnrolmentFields(), findPendingForBatch(),
                                //   approveNumbers(ids[]), serialExists()

Helpers/
  EnrolmentNumberGenerator.php  // generate(deptId, ayId): assigns serials +
                                //   enrolment numbers, returns batch record

Views/
  enrolment/
    index.php           // dept batch list (staff/admin)
    generate.php        // confirm generation form
    batch.php           // batch detail: full student list + approve controls
    summary.php         // institution admin cross-dept overview
```

Request path: `public/index.php` → router → `AuthMiddleware` → `RoleMiddleware` → `DeptScopeMiddleware` → `EnrolmentController` → view.

---

## 5. Data model

### 5.1 New table: `enrolment_batches`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `department_id` | INT FK→departments.id NOT NULL | |
| `academic_year_id` | INT FK→option_values.id NOT NULL | |
| `generated_by` | INT FK→users.id NOT NULL | staff who triggered generation |
| `student_count` | INT NOT NULL DEFAULT 0 | count at generation time |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP | |

> **No status column on this table.** Batch status (`Pending` / `In Progress` / `Approved`) is always derived from the students rows, as per the requirements. `EnrolmentBatch::deriveStatus(int $batchId)` runs the query below.

```sql
-- Derived batch status query
SELECT
  SUM(enrolment_approval_status = 'pending')  AS pending_count,
  SUM(enrolment_approval_status = 'approved') AS approved_count
FROM students
WHERE enrolment_batch_id = :batchId
```
- All pending → `Pending`
- Mix → `In Progress`
- All approved → `Approved`

Indexes: `KEY idx_enrolment_batches_dept_ay (department_id, academic_year_id)`.

### 5.2 New columns on `students` table (migration 017)

| Column | Type | Notes |
|--------|------|-------|
| `enrolment_number` | VARCHAR(20) NULL | full formatted number e.g. `24UBCA041`; set on generation |
| `enrolment_serial` | SMALLINT UNSIGNED NULL | numeric serial (041); used for max() query and display |
| `enrolment_approval_status` | ENUM('pending','approved') NULL | NULL = not yet generated |
| `enrolment_batch_id` | INT FK→enrolment_batches.id NULL | which batch generated this number |
| `enrolment_approved_by` | INT FK→users.id NULL | who approved this individual number |
| `enrolment_approved_at` | DATETIME NULL | |

Indexes:
- `UNIQUE KEY uq_students_enrolment_number (enrolment_number)` — sparse; MySQL 5.7 allows multiple NULLs in a unique index.
- `KEY idx_students_enrolment_batch (enrolment_batch_id)`
- `KEY idx_students_enrolment_approval_status (enrolment_approval_status)`

---

## 6. Processing flows

### 6.1 Generation flow

```
Staff → GET /enrolment/generate
  EnrolmentController::generateForm()
    Load active academic years from option_values.
    Render generate.php: select academic year; show count of eligible students.

Staff → POST /enrolment/generate
  EnrolmentController::generate()
    1. requireCsrf(); RoleMiddleware(['staff','dept_admin']); DeptScopeMiddleware::handle().
    2. $deptId = Auth::departmentId(); $ayId = (int)$_POST['academic_year_id'].
    3. Check no active batch: if any students in dept+year have
       enrolment_approval_status = 'pending' → block with flash error.
    4. Count eligible students: students WHERE dept=deptId, academic_year_id=ayId,
       onboarding_status='pending_enrolment'. If 0 → block with flash error.
    5. EnrolmentNumberGenerator::generate($deptId, $ayId, Auth::id()) →
         a. BEGIN TRANSACTION + lock rows.
         b. Get $nextSerial = MAX(enrolment_serial)+1 for dept+year (or 1).
         c. Create enrolment_batches record → $batchId.
         d. For each eligible student (ordered by id):
              $serial    = $nextSerial++
              $number    = EnrolmentNumberGenerator::format($dept, $ay, $serial)
              UPDATE students SET enrolment_number=?, enrolment_serial=?,
                                  enrolment_approval_status='pending',
                                  enrolment_batch_id=? WHERE id=?
         e. UPDATE enrolment_batches SET student_count = N WHERE id = $batchId.
         f. COMMIT.
         g. MasterAuditLogger::log('enrolment_batch_generated','enrolment_batch',$batchId,[...]).
    6. Redirect → GET /enrolment/batch/{batchId}
```

### 6.2 Batch detail & approval flow

```
Admin → GET /enrolment/batch/{id}
  EnrolmentController::batchDetail($batchId)
    1. RoleMiddleware(['staff','dept_admin','institution_admin']).
    2. Fetch batch; DeptScopeMiddleware::assertDepartment($batch['department_id']).
    3. Fetch all students in batch (with name, class, section, enrolment_number,
       enrolment_approval_status) — paginated 50/page, searchable.
    4. Derive batch status via EnrolmentBatch::deriveStatus($batchId).
    5. Render batch.php.

Admin → POST /enrolment/batch/{id}/approve-all
  EnrolmentController::approveAll($batchId)
    1. requireCsrf(); RoleMiddleware(['dept_admin','institution_admin']).
    2. Fetch batch; assertDepartment.
    3. BEGIN TRANSACTION:
         UPDATE students
         SET    enrolment_approval_status = 'approved',
                onboarding_status         = 'enrolment_assigned',
                enrolment_approved_by     = ?,
                enrolment_approved_at     = NOW()
         WHERE  enrolment_batch_id = ?
           AND  enrolment_approval_status = 'pending'
    4. COMMIT.
    5. $count = affected rows.
    6. MasterAuditLogger::log('enrolment_bulk_approved','enrolment_batch',$batchId,
           ['count'=>$count,'actor'=>Auth::id()]).
    7. Flash success: "{$count} enrolment numbers approved."
    8. Redirect → GET /enrolment/batch/{batchId}

Admin → POST /enrolment/batch/{id}/approve-selected
  EnrolmentController::approveSelected($batchId)
    1. requireCsrf(); RoleMiddleware(['dept_admin','institution_admin']).
    2. Fetch batch; assertDepartment.
    3. $ids = array_map('intval', $_POST['student_ids'] ?? [])
       Validate each id belongs to this batch (IN query).
    4. BEGIN TRANSACTION:
         UPDATE students SET enrolment_approval_status='approved',
                             onboarding_status='enrolment_assigned',
                             enrolment_approved_by=?, enrolment_approved_at=NOW()
         WHERE id IN (?,?,…) AND enrolment_batch_id=? AND enrolment_approval_status='pending'
    5. COMMIT.
    6. MasterAuditLogger::log per approved number (or one bulk log entry with ids[]).
    7. Flash + redirect → batch detail.
```

### 6.3 Student dashboard display

In the student's dashboard view (M8 will build this properly; M4 adds data):

```php
// Student::getEnrolmentStatus(int $studentId): array
// Returns: ['number' => '24UBCA041'|null, 'status' => 'approved'|'pending'|null]
$row = Db::selectOne(
    "SELECT enrolment_number, enrolment_approval_status FROM students WHERE id = ?",
    [$studentId]
);
// View shows enrolment_number only when enrolment_approval_status = 'approved'.
// When null or pending: "Your enrolment number has not been assigned yet."
```

---

## 7. RBAC & department scoping

| Route | Allowed roles | Dept scope |
|-------|--------------|------------|
| `GET /enrolment` (batch list) | `staff`, `dept_admin`, `institution_admin` | staff/admin → own dept; inst_admin → all |
| `GET /enrolment/generate` | `staff`, `dept_admin` | own dept |
| `POST /enrolment/generate` | `staff`, `dept_admin` | own dept only |
| `GET /enrolment/batch/{id}` | `staff`, `dept_admin`, `institution_admin` | dept-scoped guard on batch |
| `POST /enrolment/batch/{id}/approve-all` | `dept_admin`, `institution_admin` | dept-scoped guard |
| `POST /enrolment/batch/{id}/approve-selected` | `dept_admin`, `institution_admin` | dept-scoped guard |
| `GET /enrolment/summary` | `institution_admin` | all depts |

Staff can view the batch detail but cannot approve — the approve buttons are hidden for `staff` role in the view and blocked server-side by `RoleMiddleware`.

---

## 8. Screen behaviour & messages

### 8.1 Generate form (`generate.php`)
- Academic Year `<select>` (active values from `option_values`).
- On year selection: live count of eligible students via JS fetch to `/enrolment/eligible-count?dept_id=N&ay_id=N` → renders "42 students eligible for generation."
- "Generate Enrolment Numbers" submit button disabled until year is chosen.
- If blocked (pending batch exists or no eligible students): flash danger banner; no form shown.

### 8.2 Batch detail (`batch.php`)
- **Header card:** Dept, Academic Year, Generated by, Generated at, Student count, **Derived status badge** (Pending / In Progress / Approved — colour-coded).
- **Approval action bar** (visible to `dept_admin` and `institution_admin` only; hidden for `staff`):
  - "Approve All" button (green) — opens Bootstrap confirmation modal: "Approve all N enrolment numbers? This will publish them to students." with Confirm and Cancel.
  - "Approve Selected" button (blue) — enabled only when ≥ 1 checkbox is ticked.
  - Select-all checkbox in table header.
- **Student table:** columns — Checkbox (admin only), S.No, Student Name, Mobile, Class, Section, Enrolment Number, Approval Status badge, Approved by, Approved at.
- Search box (name or mobile) — filters table server-side.
- Pagination: 50 rows/page.
- Already-approved rows show a locked icon; checkbox is disabled for them.

### 8.3 Batch list (`index.php`)
- Table: Batch ID, Academic Year, Dept (inst_admin only), Student Count, Approved / Pending counts, Derived Status, Generated by, Generated at.
- Filter by Academic Year and Status.
- "Generate New Batch" button (staff/dept_admin, own dept).

### 8.4 Institution summary (`summary.php`)
- Bootstrap stat cards per department: Total Generated / Pending / In Progress / Fully Approved.
- Filter: Academic Year.

### 8.5 Student dashboard widget (data only — M8 renders the full dashboard)
- `Student::getEnrolmentStatus($id)` returns the number+status.
- View logic: if `approved` → show `<strong>24UBCA041</strong>` with label "Enrolment Number". Otherwise → show "Your enrolment number has not been assigned yet."

---

## 9. Validation & error states

| Scenario | Handling |
|----------|----------|
| Generation attempted when pending batch exists | Flash danger: "A batch is already pending — approve all existing numbers before generating a new one." |
| No eligible students | Flash danger: "No students with Pending Enrolment status found for this academic year." |
| Concurrent generation (race condition) | Transaction + row-lock ensures serial uniqueness; second concurrent request sees the already-assigned rows and produces 0 eligible → blocked. |
| Approve-selected with empty selection | JS prevents submission; server also returns flash danger if `student_ids` is empty. |
| Approve-selected with IDs not in this batch | Server-side IN + batch_id cross-check; mismatched IDs silently skipped. |
| Approve already-approved number | `WHERE enrolment_approval_status = 'pending'` guard in UPDATE; already-approved rows not affected. |
| DB unique constraint on `enrolment_number` | Caught in try/catch; treated as a critical error + transaction rollback + admin alert flash. |

---

## 10. Configuration parameters

| Key | Default | Notes |
|-----|---------|-------|
| `enrolment.serial_pad_length` | `3` | Zero-padding width; not surfaced in UI in v1 |

Stored in `config/enrolment.php`.

---

## 11. Traceability

| Requirement | Design element |
|-------------|---------------|
| A1 — Generate batch | §6.1 generation flow; `EnrolmentNumberGenerator::generate()` |
| A2 — Serial continuity | §3 formula; MAX(enrolment_serial)+1 inside transaction |
| B1 — Review list before approving | §8.2 batch.php full table before action buttons |
| B2 — Approve All | §6.2 `approveAll()`; single UPDATE in one transaction |
| B3 — Approve Selected | §6.2 `approveSelected()`; checkbox + filtered UPDATE |
| B4 — Single-approval rule | §7 RBAC table; `dept_admin` or `institution_admin` |
| C1 — Student sees number only after approval | §6.3 `getEnrolmentStatus()`; `enrolment_approval_status='approved'` guard |
| D1/D2 — Batch list / institution overview | §8.3 index.php; §8.4 summary.php |
| D3 — Audit trail | `MasterAuditLogger::log()` calls in §6.1 and §6.2 |

---

## 12. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, the next step is **Stage 3: Tasks** for Module 4 — a task-by-task breakdown with estimates, dependencies, and done-when criteria, submitted for your review before implementation.
