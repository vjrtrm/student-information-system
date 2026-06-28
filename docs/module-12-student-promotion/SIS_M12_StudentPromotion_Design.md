# SIS — Module 12: Student Promotion
## Stage 2: Design (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 12 of 12 — Student Promotion
**Document stage:** Requirements ✅ → **Design (this document)** → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M12_StudentPromotion_Requirements.md`

---

## 1. Design goals

- Approve = execute: approval and batch execution are one atomic operation — no separate "Execute" step.
- A single `PromotionController` handles all batch and window actions.
- A `PromotionBatch` model encapsulates all DB operations; the controller stays thin.
- The entire student-update loop runs inside a PDO transaction; any failure rolls back everything.
- Rejected batches are editable and resubmissable by staff, but resubmissions always require **Institution Admin** approval (not dept_admin).
- All students (eligible and ineligible) are shown on the create/edit form; ineligible students have disabled checkboxes and an ineligibility reason tooltip.

---

## 2. Resolved design decisions

| # | Question | Decision |
|---|----------|----------|
| 1 | Excluded students' status | Excluded students get `onboarding_status = 'detained'` on batch approval. |
| 2 | Approve vs separate Execute | **Approve = execute immediately** in one atomic transaction. |
| 3 | Profile data on reset | Existing profile data (personal, family, address, etc.) preserved. Only `form_status → 'incomplete'`, `form_completion_pct → 0`, `form_submitted_at → NULL`, `last_saved_at → NULL` reset. |
| 4 | Eligible-only vs all students | **All students shown**; ineligible students rendered with disabled checkboxes and a greyed-out row with the ineligibility reason. |
| 5 | Resubmit after rejection | Staff may edit a rejected batch (change target values, adjust inclusions/exclusions) and resubmit. **Resubmissions always require Institution Admin approval**, regardless of who rejected the original batch. |

---

## 3. Component architecture (MVC)

### New controller

**`app/Controllers/PromotionController.php`** — 9 actions:

| Method | Route | Roles | Description |
|--------|-------|-------|-------------|
| `index()` | GET /promotion | staff, dept_admin, institution_admin | List all batches (dept-scoped for staff/dept_admin; all for inst_admin) |
| `createForm()` | GET /promotion/create | staff | Create batch form; shows all students with eligibility status |
| `store()` | POST /promotion/create | staff | Validate + create batch + exclusions; redirect to detail |
| `detail(int $id)` | GET /promotion/{id} | staff, dept_admin, institution_admin | Batch detail — included + excluded students, status |
| `editForm(int $id)` | GET /promotion/{id}/edit | staff | Edit a rejected batch |
| `update(int $id)` | POST /promotion/{id}/edit | staff | Save edits; resubmit for inst_admin approval |
| `approve(int $id)` | POST /promotion/{id}/approve | dept_admin, institution_admin | Approve (first submission) or institution_admin (resubmission); execute atomically |
| `reject(int $id)` | POST /promotion/{id}/reject | dept_admin, institution_admin | Reject with reason |
| `toggleWindow()` | POST /promotion/window/toggle | institution_admin | Open/close promotion window |

### New model

**`app/Models/PromotionBatch.php`** — static helpers:

```
findAll(int $deptId = 0): array          — deptId=0 returns all (inst_admin)
findById(int $id): ?array
findPendingForDept(int $deptId): ?array  — returns the one pending batch for a dept (for duplicate-batch guard)
create(array $data): int                 — INSERT promotion_batches; return lastInsertId
update(int $id, array $data): void       — UPDATE whitelisted columns
getStudents(int $batchId): array         — JOIN promotion_batch_students + students
getExclusions(int $batchId): array       — JOIN promotion_exclusions + students
execute(int $batchId, int $approverId): void — transaction: update students + set detained + update batch status
```

### New helpers / setting

**`PromotionWindow`** is not a separate class — window state read via:
```php
Db::selectOne("SELECT value FROM settings WHERE `key` = 'promotion_window_open'")
```
Written by `toggleWindow()`.

### Views

| File | Purpose |
|------|---------|
| `promotion/index.php` | Batch list; window status banner; "Create Batch" button |
| `promotion/form.php` | Shared create + edit form; all-student checklist with eligibility |
| `promotion/detail.php` | Batch detail; included/excluded tables; approve/reject actions |

---

## 4. Data model

### New tables

**`promotion_batches`**
```sql
CREATE TABLE promotion_batches (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_id           INT UNSIGNED NOT NULL,
    target_academic_year_id INT UNSIGNED NOT NULL,
    target_class_id         INT UNSIGNED NOT NULL,
    target_section_id       INT UNSIGNED NOT NULL,
    status                  ENUM('pending_approval','approved','rejected') NOT NULL DEFAULT 'pending_approval',
    requires_inst_admin     TINYINT(1) NOT NULL DEFAULT 0,  -- 1 for resubmissions
    initiated_by            INT UNSIGNED NOT NULL,
    rejection_reason        TEXT NULL,
    reviewed_by             INT UNSIGNED NULL,
    reviewed_at             DATETIME NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_dept_status (department_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**`promotion_batch_students`** — students included in the batch (to be promoted)
```sql
CREATE TABLE promotion_batch_students (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id   INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_batch_student (batch_id, student_id),
    KEY idx_batch (batch_id),
    CONSTRAINT fk_pbs_batch   FOREIGN KEY (batch_id)   REFERENCES promotion_batches(id),
    CONSTRAINT fk_pbs_student FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**`promotion_exclusions`** — students excluded from the batch (to be detained)
```sql
CREATE TABLE promotion_exclusions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id   INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    reason     TEXT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_excl_batch_student (batch_id, student_id),
    CONSTRAINT fk_pe_batch   FOREIGN KEY (batch_id)   REFERENCES promotion_batches(id),
    CONSTRAINT fk_pe_student FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**`settings`** — generic key-value store
```sql
CREATE TABLE settings (
    `key`   VARCHAR(100) NOT NULL,
    value   TEXT NOT NULL DEFAULT '',
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (`key`, value) VALUES ('promotion_window_open', '0');
```

### Migrations

- `028_create_promotion_batches.sql`
- `029_create_promotion_batch_students.sql`
- `030_create_promotion_exclusions.sql`
- `031_create_settings.sql`

---

## 5. Flows

### 5.1 Create batch (first submission)

```
GET /promotion/create
  → staff only; window must be open
  → check no pending batch exists for this dept (if so → flash error + redirect /promotion)
  → load all students for dept:
      SELECT s.*, sp.form_status, sp.form_completion_pct FROM students s
      LEFT JOIN student_profiles sp ON sp.student_id = s.id
      WHERE s.department_id = ?
  → for each student compute:
      eligible = (s.onboarding_status = 'active' AND s.enrolment_approval_status = 'approved')
      ineligible_reason = human-readable string if not eligible
  → load academic_year, class, section option_values
  → render promotion/form.php ($mode='create')

POST /promotion/create
  → requireCsrf()
  → validate target_academic_year_id, target_class_id, target_section_id (required; must be valid option_value ids)
  → validate no pending batch for dept
  → parse included_students[] (array of student_ids from POST)
  → parse excluded[student_id][reason] from POST
  → validate: each excluded student has a non-empty reason
  → INSERT promotion_batches (status='pending_approval', requires_inst_admin=0)
  → for each eligible included student: INSERT promotion_batch_students
  → for each excluded student: INSERT promotion_exclusions
  → MasterAuditLogger::log('create', 'promotion_batch', $batchId, ['dept'=>..., 'count'=>...])
  → create notification_event for dept_admin (event_key='promotion_batch_submitted')
  → flash "Promotion batch submitted for approval."
  → redirect /promotion/{batchId}
```

### 5.2 Approve batch (atomic execution)

```
POST /promotion/{id}/approve
  → RoleMiddleware(['dept_admin', 'institution_admin'])
  → requireCsrf()
  → load batch; 403 if wrong dept (dept_admin)
  → if batch.requires_inst_admin = 1 AND Auth::role() !== 'institution_admin' → 403
  → if batch.status !== 'pending_approval' → flash error + redirect
  → PromotionBatch::execute($batchId, Auth::userId()):
      BEGIN TRANSACTION
        — Update included students:
        foreach promotion_batch_students WHERE batch_id = ?:
          UPDATE students SET academic_year_id=?, class_id=?, section_id=? WHERE id=?
          UPDATE student_profiles SET form_status='incomplete', form_completion_pct=0,
                 form_submitted_at=NULL, last_saved_at=NULL, updated_at=? WHERE student_id=?
        — Update excluded students:
        foreach promotion_exclusions WHERE batch_id = ?:
          UPDATE students SET onboarding_status='detained' WHERE id=?
        — Update batch:
        UPDATE promotion_batches SET status='approved', reviewed_by=?, reviewed_at=?, updated_at=? WHERE id=?
      COMMIT
  → MasterAuditLogger::log('approve', 'promotion_batch', $batchId, ['included'=>N, 'excluded'=>M])
  → create notification_event for initiator (event_key='promotion_batch_approved')
  → flash "Promotion batch approved. N students promoted, M students detained."
  → redirect /promotion/{id}
```

### 5.3 Reject batch

```
POST /promotion/{id}/reject
  → load batch; role/dept guards
  → requireCsrf(); validate rejection_reason not empty
  → UPDATE promotion_batches SET status='rejected', rejection_reason=?, reviewed_by=?, reviewed_at=?
  → MasterAuditLogger::log('reject', 'promotion_batch', $batchId, ['reason'=>...])
  → notification_event for initiator (event_key='promotion_batch_rejected')
  → flash "Batch rejected."
  → redirect /promotion/{id}
```

### 5.4 Edit and resubmit rejected batch

```
GET /promotion/{id}/edit
  → staff only; batch.status must be 'rejected'; dept scope guard
  → render promotion/form.php ($mode='edit', $batch=...) with same student checklist

POST /promotion/{id}/edit
  → requireCsrf(); validate same as create
  → DELETE promotion_batch_students WHERE batch_id=?
  → DELETE promotion_exclusions WHERE batch_id=?
  → UPDATE promotion_batches SET target_academic_year_id=?, target_class_id=?, target_section_id=?,
         status='pending_approval', requires_inst_admin=1, rejection_reason=NULL,
         reviewed_by=NULL, reviewed_at=NULL, updated_at=?
  → re-INSERT batch_students and exclusions
  → MasterAuditLogger::log('resubmit', 'promotion_batch', $batchId, [...])
  → notification_event for institution_admin (event_key='promotion_batch_resubmitted')
  → flash "Batch resubmitted for Institution Admin approval."
  → redirect /promotion/{id}
```

### 5.5 Toggle promotion window

```
POST /promotion/window/toggle
  → institution_admin only; requireCsrf()
  → read current value from settings WHERE key='promotion_window_open'
  → new value = current == '1' ? '0' : '1'
  → UPDATE settings SET value=? WHERE key='promotion_window_open'
    (or INSERT ... ON DUPLICATE KEY UPDATE if row missing)
  → MasterAuditLogger::log('toggle_window', 'promotion_window', null, ['new_state'=>...])
  → flash "Promotion window [opened/closed]."
  → redirect /promotion
```

---

## 6. Eligibility logic (shared)

```php
// applied to every student when building the form checklist
function isEligible(array $student): bool
{
    return $student['onboarding_status'] === 'active'
        && $student['enrolment_approval_status'] === 'approved';
}

function ineligibleReason(array $student): string
{
    if ($student['onboarding_status'] !== 'active') {
        return 'Status: ' . $student['onboarding_status'];
    }
    if ($student['enrolment_approval_status'] !== 'approved') {
        return 'Enrolment not approved';
    }
    return '';
}
```

---

## 7. Duplicate-batch guard

Before creating (or resubmitting editing an already-pending batch), check:
```sql
SELECT id FROM promotion_batches
WHERE department_id = ? AND status = 'pending_approval'
LIMIT 1
```
If a row exists → block with flash "A promotion batch is already pending approval for this department."

For the edit flow (resubmit of a rejected batch), the batch itself is not in `pending_approval` yet, so the guard passes.

---

## 8. RBAC & department scoping

| Action | Staff | Dept Admin | Institution Admin |
|--------|-------|-----------|------------------|
| View batch list (own dept) | ✓ | ✓ | ✓ (all) |
| Create batch | ✓ | ✗ | ✗ |
| Edit rejected batch | ✓ | ✗ | ✗ |
| Approve (first submission) | ✗ | ✓ | ✓ |
| Approve (resubmission) | ✗ | ✗ | ✓ |
| Reject | ✗ | ✓ | ✓ |
| Toggle window | ✗ | ✗ | ✓ |

Dept scoping:
- Staff and dept_admin: `WHERE department_id = Auth::departmentId()` always applied; 403 on cross-dept access.
- Institution admin: no mandatory dept filter; can access any batch.

---

## 9. Notification event keys

| Event | Recipient | Payload keys |
|-------|-----------|--------------|
| `promotion_batch_submitted` | dept_admin of the dept | `batch_id`, `department_id`, `target_academic_year_id` |
| `promotion_batch_resubmitted` | institution_admin (all) | same |
| `promotion_batch_approved` | initiating staff user | `batch_id`, `department_id`, `promoted_count`, `detained_count` |
| `promotion_batch_rejected` | initiating staff user | `batch_id`, `department_id`, `rejection_reason` is NOT in payload (PII-free) |

For `promotion_batch_submitted` and `promotion_batch_resubmitted`, `recipient_type = 'role'` and `recipient_id = NULL` (sent to all users of that role in the dept). M7 NotificationProcessor handles this via the existing event schema.

---

## 10. Session / security

| Rule | Implementation |
|------|----------------|
| CSRF | `requireCsrf()` on all POST |
| Dept scope | Controller guards on every action; 403 on cross-dept |
| Resubmission approval escalation | `requires_inst_admin = 1` on batch; `approve()` checks this flag; dept_admin gets 403 on resubmissions |
| Idempotency | `status !== 'pending_approval'` check before approve/reject |
| Window guard | `createForm()` and `store()` check `settings.promotion_window_open`; flash + redirect if closed |
| Audit | Every mutation logged via `MasterAuditLogger` |
| Transaction | `execute()` uses PDO `beginTransaction()` / `commit()` / `rollBack()` |

---

## 11. Screen behaviour

### Promotion index (`/promotion`)

- **Window banner** (institution_admin only): "Promotion window is currently [OPEN/CLOSED]" + Toggle button.
- Table: Batch ID, Department (inst_admin only), Target Year, Status badge, Students (included count), Initiated By, Date, Actions (View; Approve/Reject if pending and role allows).
- "Create Batch" button — visible to staff only when window is open.

### Create/Edit form (`/promotion/create`, `/promotion/{id}/edit`)

- Target year/class/section selectors at top.
- Student checklist table: columns — Name, Enrolment No., Current Year, Form Status, Eligible (✓/✗), Include checkbox, Exclusion Reason (text input, shown/required when unchecked).
- Ineligible students: row greyed out, checkbox disabled, exclusion reason column shows ineligibility reason.
- "Submit for Approval" button.

### Batch detail (`/promotion/{id}`)

- Batch metadata: target year/class/section, status badge, initiated by, reviewed by (if any), rejection reason (if rejected).
- **Included students** table: Name, Enrolment No., Current Year.
- **Excluded students** table: Name, Enrolment No., Exclusion Reason.
- For `pending_approval` batches: Approve button (dept_admin/inst_admin) + Reject form (with reason textarea).
- For `rejected` batches + staff: "Edit & Resubmit" button.
- Status badge colours: pending_approval = warning, approved = success, rejected = danger.

### Flash messages

| Action | Message |
|--------|---------|
| Create batch | "Promotion batch submitted for approval." |
| Approve | "Promotion approved. N students promoted, M students detained." |
| Reject | "Promotion batch rejected." |
| Resubmit | "Batch resubmitted for Institution Admin approval." |
| Toggle window (open) | "Promotion window is now open." |
| Toggle window (close) | "Promotion window is now closed." |
| Duplicate batch guard | "A promotion batch is already pending approval for this department." |

---

## 12. Traceability (requirement → design)

| Requirement | Design element |
|-------------|---------------|
| A1 — Create batch | `createForm()` + `store()` + window guard + duplicate-batch guard |
| A2 — Exclusions with reason | `promotion_exclusions` table; required reason validation; detained on approval |
| B1 — Admin approves (executes) | `approve()` → `PromotionBatch::execute()` in transaction |
| B2 — Inst admin cross-dept | No forced dept WHERE for inst_admin; dept guard only for dept_admin |
| C1 — Window toggle | `toggleWindow()` + `settings` table |
| NFR — Atomicity | PDO transaction in `execute()` |
| NFR — Resubmission escalation | `requires_inst_admin = 1` flag; guard in `approve()` |
| NFR — Ineligible students visible | All students loaded; eligibility computed; ineligible rows greyed/disabled |
| NFR — Audit | MasterAuditLogger on all mutations |

---

## 13. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, proceed to Tasks.
