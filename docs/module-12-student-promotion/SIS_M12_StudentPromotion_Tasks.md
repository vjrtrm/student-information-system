# SIS — Module 12: Student Promotion
## Stage 3: Tasks (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 12 of 12 — Student Promotion (final module)
**Document stage:** Requirements ✅ → Design ✅ → **Tasks (this document)**
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M12_StudentPromotion_Design.md`

---

## 1. How to read this

Each task: ID, deliverable, estimate (ideal hours), dependencies, priority, "done when". P1 = required for the module to function; P2 = hardening/polish. Build order in §9.

---

## 2. Migrations

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M12-T01 | `028_create_promotion_batches.sql` — `CREATE TABLE promotion_batches (id INT UNSIGNED AUTO_INCREMENT PK, department_id INT UNSIGNED NOT NULL, target_academic_year_id INT UNSIGNED NOT NULL, target_class_id INT UNSIGNED NOT NULL, target_section_id INT UNSIGNED NOT NULL, status ENUM('pending_approval','approved','rejected') NOT NULL DEFAULT 'pending_approval', requires_inst_admin TINYINT(1) NOT NULL DEFAULT 0, initiated_by INT UNSIGNED NOT NULL, rejection_reason TEXT NULL, reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, KEY idx_dept_status(department_id, status)) ENGINE=InnoDB` | 1 | — | P1 | Table created; index on dept+status |
| M12-T02 | `029_create_promotion_batch_students.sql` — `CREATE TABLE promotion_batch_students (id INT UNSIGNED AUTO_INCREMENT PK, batch_id INT UNSIGNED NOT NULL, student_id INT UNSIGNED NOT NULL, UNIQUE KEY uq_batch_student(batch_id,student_id), KEY idx_batch(batch_id), FK batch_id→promotion_batches, FK student_id→students) ENGINE=InnoDB` | 1 | M12-T01 | P1 | Table + FKs created |
| M12-T03 | `030_create_promotion_exclusions.sql` — `CREATE TABLE promotion_exclusions (id INT UNSIGNED AUTO_INCREMENT PK, batch_id INT UNSIGNED NOT NULL, student_id INT UNSIGNED NOT NULL, reason TEXT NOT NULL, UNIQUE KEY uq_excl(batch_id,student_id), FK batch_id→promotion_batches, FK student_id→students) ENGINE=InnoDB` | 1 | M12-T01 | P1 | Table + FKs created |
| M12-T04 | `031_create_settings.sql` — `CREATE TABLE settings (key VARCHAR(100) NOT NULL, value TEXT NOT NULL DEFAULT '', PRIMARY KEY (key)) ENGINE=InnoDB; INSERT INTO settings (key,value) VALUES ('promotion_window_open','0');` | 0.5 | — | P1 | Table created; seed row present |

---

## 3. Model

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M12-T05 | `PromotionBatch` (`app/Models/PromotionBatch.php`) — static methods: `findAll(int $deptId = 0): array` (deptId=0 = no dept filter; JOINs departments + users for display); `findById(int $id): ?array`; `findPendingForDept(int $deptId): ?array` (status='pending_approval'); `create(array $data): int` (INSERT; return lastInsertId); `update(int $id, array $data): void` (UPDATE whitelisted columns: target_academic_year_id, target_class_id, target_section_id, status, requires_inst_admin, rejection_reason, reviewed_by, reviewed_at, updated_at); `getIncluded(int $batchId): array` (SELECT students via promotion_batch_students JOIN students); `getExcluded(int $batchId): array` (SELECT via promotion_exclusions JOIN students); `execute(int $batchId, int $approverId): void` (PDO transaction: update included students + reset profiles + detain excluded + mark batch approved — see §5 flow); `isWindowOpen(): bool` (SELECT value FROM settings WHERE key='promotion_window_open') | 5 | M12-T01–T04 | P1 | All methods return correct types; execute() is atomic; isWindowOpen() returns bool |

---

## 4. Controller

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M12-T06 | `PromotionController` (`app/Controllers/PromotionController.php`) — 9 actions, all enforce `RoleMiddleware` and dept-scope guards as per Design §8. Implement: `index()`, `createForm()`, `store()`, `detail(int $id)`, `editForm(int $id)`, `update(int $id)`, `approve(int $id)`, `reject(int $id)`, `toggleWindow()`. All POST actions call `requireCsrf()`. See §5 for each flow. Private helpers: `loadAndScope(int $id, array $roles): array` (load batch + 403 if wrong dept or role); `loadAllStudentsForDept(int $deptId): array` (all students with eligibility computed); `parseInclusionsAndExclusions(array $post, array $students): array` (returns [included_ids[], exclusions[student_id=>reason]]); `validateTargetValues(array $post): array` (returns error array). | 8 | M12-T05 | P1 | All 9 actions enforce correct roles and dept scope; window guard on create; duplicate-batch guard on store/update; transaction in approve; resubmission flag enforced |
| M12-T07 | Routes — add `use App\Controllers\PromotionController;` to `public/index.php`; register routes (static paths before wildcards): POST /promotion/window/toggle, GET /promotion/create, POST /promotion/create, GET /promotion/{id}/edit, POST /promotion/{id}/edit, POST /promotion/{id}/approve, POST /promotion/{id}/reject, GET /promotion/{id}, GET /promotion | 1 | M12-T06 | P1 | All 9 routes resolve; role violations 403 |

---

## 5. Controller action detail

### `index()` — GET /promotion
```
RoleMiddleware(['staff','dept_admin','institution_admin'])
$deptId = Auth::role() === 'institution_admin' ? 0 : Auth::departmentId()
$batches = PromotionBatch::findAll($deptId)
$windowOpen = PromotionBatch::isWindowOpen()
render promotion/index.php
```

### `createForm()` — GET /promotion/create
```
RoleMiddleware(['staff'])
if !isWindowOpen → flash 'Promotion window is currently closed.' → redirect /promotion
if findPendingForDept(Auth::departmentId()) → flash 'A batch is already pending approval.' → redirect /promotion
$students = loadAllStudentsForDept(Auth::departmentId())   // includes eligibility
load option_values for academic_year, class, section
render promotion/form.php ($mode='create')
```

### `store()` — POST /promotion/create
```
RoleMiddleware(['staff']); requireCsrf()
if !isWindowOpen → 403
if findPendingForDept → flash error → redirect
validateTargetValues($_POST) → errors → flash + redirect if any
parseInclusionsAndExclusions($_POST, $students) → [included_ids, exclusions]
validate exclusion reasons all non-empty → errors
PromotionBatch::create([department_id, target_*, status='pending_approval', requires_inst_admin=0, initiated_by, created_at])
INSERT promotion_batch_students for each included_id
INSERT promotion_exclusions for each exclusion
MasterAuditLogger::log('create', 'promotion_batch', $id, [count, dept])
// Notification: dept_admin of this dept
Db::execute("INSERT INTO notification_events (...) VALUES (...)", [...])
flash 'Promotion batch submitted for approval.' → redirect /promotion/{id}
```

### `detail(int $id)` — GET /promotion/{id}
```
RoleMiddleware(['staff','dept_admin','institution_admin'])
loadAndScope($id, ['staff','dept_admin','institution_admin'])   // 403 if wrong dept for non-inst_admin
$included = PromotionBatch::getIncluded($id)
$excluded = PromotionBatch::getExcluded($id)
load target year/class/section labels from option_values
render promotion/detail.php
```

### `editForm(int $id)` — GET /promotion/{id}/edit
```
RoleMiddleware(['staff'])
loadAndScope($id, ['staff'])
if batch.status !== 'rejected' → 403
$students = loadAllStudentsForDept(Auth::departmentId())
load option_values
render promotion/form.php ($mode='edit', $batch=...)
```

### `update(int $id)` — POST /promotion/{id}/edit (resubmit)
```
RoleMiddleware(['staff']); requireCsrf()
loadAndScope($id, ['staff'])
if batch.status !== 'rejected' → 403
validateTargetValues; parseInclusionsAndExclusions; validate reasons
DELETE promotion_batch_students WHERE batch_id=?
DELETE promotion_exclusions WHERE batch_id=?
PromotionBatch::update($id, [target_*, status='pending_approval', requires_inst_admin=1, rejection_reason=NULL, reviewed_by=NULL, reviewed_at=NULL, updated_at])
re-INSERT batch_students and exclusions
MasterAuditLogger::log('resubmit', 'promotion_batch', $id, [...])
// Notification: institution_admin
flash 'Batch resubmitted for Institution Admin approval.' → redirect /promotion/{id}
```

### `approve(int $id)` — POST /promotion/{id}/approve
```
RoleMiddleware(['dept_admin','institution_admin']); requireCsrf()
loadAndScope($id, ['dept_admin','institution_admin'])
if batch.requires_inst_admin = 1 AND Auth::role() !== 'institution_admin' → 403
if batch.status !== 'pending_approval' → flash 'Batch already processed.' → redirect
PromotionBatch::execute($id, Auth::userId())   // transaction
// count included and excluded from batch
MasterAuditLogger::log('approve', 'promotion_batch', $id, [included, excluded])
// Notification: initiating staff
flash "Promotion approved. {N} students promoted, {M} students detained." → redirect /promotion/{id}
```

### `reject(int $id)` — POST /promotion/{id}/reject
```
RoleMiddleware(['dept_admin','institution_admin']); requireCsrf()
loadAndScope($id, ['dept_admin','institution_admin'])
if batch.status !== 'pending_approval' → flash error → redirect
validate rejection_reason not empty
PromotionBatch::update($id, [status='rejected', rejection_reason, reviewed_by, reviewed_at, updated_at])
MasterAuditLogger::log('reject', 'promotion_batch', $id, ['reason_length'=>strlen($reason)])
// Notification: initiating staff
flash 'Promotion batch rejected.' → redirect /promotion/{id}
```

### `toggleWindow()` — POST /promotion/window/toggle
```
RoleMiddleware(['institution_admin']); requireCsrf()
$current = PromotionBatch::isWindowOpen() ? '1' : '0'
$new = $current === '1' ? '0' : '1'
Db::execute("REPLACE INTO settings (`key`, value) VALUES ('promotion_window_open', ?)", [$new])
MasterAuditLogger::log('toggle_window', 'promotion_window', null, ['new_state' => $new === '1' ? 'open' : 'closed'])
flash $new === '1' ? 'Promotion window is now open.' : 'Promotion window is now closed.'
redirect /promotion
```

### `PromotionBatch::execute()` — transaction body
```php
$pdo = Db::conn();
$pdo->beginTransaction();
try {
    $now = date('Y-m-d H:i:s');
    $batch = self::findById($batchId);
    // Update included students
    $included = self::getIncluded($batchId);
    foreach ($included as $s) {
        Db::execute(
            'UPDATE students SET academic_year_id=?, class_id=?, section_id=?, updated_at=? WHERE id=?',
            [$batch['target_academic_year_id'], $batch['target_class_id'], $batch['target_section_id'], $now, $s['id']]
        );
        Db::execute(
            "UPDATE student_profiles SET form_status='incomplete', form_completion_pct=0,
             form_submitted_at=NULL, last_saved_at=NULL, updated_at=? WHERE student_id=?",
            [$now, $s['id']]
        );
    }
    // Detain excluded students
    $excluded = self::getExcluded($batchId);
    foreach ($excluded as $e) {
        Db::execute(
            "UPDATE students SET onboarding_status='detained', updated_at=? WHERE id=?",
            [$now, $e['id']]
        );
    }
    // Mark batch approved
    Db::execute(
        'UPDATE promotion_batches SET status=?, reviewed_by=?, reviewed_at=?, updated_at=? WHERE id=?',
        ['approved', $approverId, $now, $now, $batchId]
    );
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

---

## 6. Views

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M12-T08 | `promotion/index.php` — Bootstrap 5; window status banner + toggle form (inst_admin only); "Create Batch" button (staff, window open only); table: Batch ID, Department (inst_admin), Target Year, Status badge (warning/success/danger), Included count, Initiated By, Created At, Actions (View; Approve/Reject inline if pending and role has permission) | 3 | M12-T06 | P1 | All roles see correct rows; inst_admin sees all depts; window banner + toggle present for inst_admin |
| M12-T09 | `promotion/form.php` — shared create/edit; target year/class/section selectors at top; student checklist table: Name, Enrolment No., Current Year, Form Status, Eligible (✓/✗), Include checkbox (disabled for ineligible), Exclusion Reason input (shown via JS when checkbox unchecked; required); ineligible rows greyed out with ineligibility reason in the reason column; "Submit for Approval" button | 5 | M12-T06 | P1 | Ineligible students disabled; exclusion reason shown/required on uncheck; form submits correct arrays |
| M12-T10 | `promotion/detail.php` — batch metadata (target year/class/section labels, status badge, initiated by, reviewed by, dates); included students table; excluded students table with reasons; for pending batches: approve button + reject form (reason textarea + submit); for rejected batches + staff: "Edit & Resubmit" link; for approved: read-only | 4 | M12-T06 | P1 | All batch states render correctly; approve/reject controls only for correct roles; resubmit link only for staff on rejected batches |
| M12-T11 | Nav update — add "Promotion" link in `app/Views/layouts/app.php` for staff, dept_admin, institution_admin pointing to /promotion; active when URI starts with /promotion | 0.5 | M12-T07 | P2 | Nav link visible for correct roles |

---

## 7. Bootstrap update

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M12-T12 | Update `tests/bootstrap.php` — add `CREATE TABLE IF NOT EXISTS` for promotion_batches, promotion_batch_students, promotion_exclusions, settings (SQLite-compatible DDL; ENUM→TEXT; FK constraints omitted; include seed row for promotion_window_open) | 1 | — | P1 | All M12 tests run on SQLite without schema errors |

---

## 8. Tests

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M12-T13 | Unit: `PromotionBatchModelTest` (`tests/Unit/PromotionBatchModelTest.php`) — test isWindowOpen() returns false when value='0'; true when value='1'; test findPendingForDept() returns null when none exist; returns row when one exists; test create() + findById() round-trip | 3 | M12-T05, M12-T12 | P1 | Green |
| M12-T14 | Integration: `PromotionBatchCreateTest` (`tests/Integration/PromotionBatchCreateTest.php`) — seed dept + students (mix of eligible and ineligible); simulate store() logic: create batch with 2 included and 1 excluded; assert promotion_batches row created; assert promotion_batch_students count = 2; assert promotion_exclusions count = 1 with correct reason; assert audit_log entry | 4 | M12-T06, M12-T12 | P1 | Green |
| M12-T15 | Integration: `PromotionBatchApproveTest` (`tests/Integration/PromotionBatchApproveTest.php`) — seed batch with 2 included students + 1 excluded; call PromotionBatch::execute(); assert included students have updated academic_year_id, class_id, section_id; assert student_profiles.form_status='incomplete' and form_completion_pct=0 for included; assert excluded student has onboarding_status='detained'; assert batch.status='approved'; assert rollback on simulated failure (set invalid student_id to trigger exception) | 5 | M12-T05, M12-T12 | P1 | Green |
| M12-T16 | Integration: `PromotionResubmitTest` (`tests/Integration/PromotionResubmitTest.php`) — seed rejected batch; simulate update() (resubmit): verify requires_inst_admin=1 set; verify promotion_batch_students re-populated; verify status='pending_approval'; assert dept_admin would get 403 (check flag logic without HTTP); assert inst_admin would proceed (flag check passes) | 3 | M12-T06, M12-T12 | P1 | Green |
| M12-T17 | Integration: `PromotionWindowTest` (`tests/Integration/PromotionWindowTest.php`) — seed settings row; assert isWindowOpen() = false; toggle to '1'; assert isWindowOpen() = true; toggle back; assert false; assert audit_log entries present | 2 | M12-T05, M12-T12 | P1 | Green |

---

## 9. Build order (critical path)

1. **Migrations:** M12-T01, T02, T03, T04 (in order; T02/T03 depend on T01 FK)
2. **Bootstrap:** M12-T12 (alongside migrations)
3. **Model:** M12-T05
4. **Controller + routes:** M12-T06 → M12-T07
5. **Views:** M12-T08 (index) → M12-T09 (form) → M12-T10 (detail) → M12-T11 (nav)
6. **Tests:** M12-T13 (unit) → M12-T14, T15, T16, T17 (integration, after controller)

---

## 10. Estimate summary

| Group | Hours |
|-------|------:|
| Migrations (T01–T04) | 3.5 |
| Model (T05) | 5 |
| Controller & routes (T06–T07) | 9 |
| Views (T08–T11) | 12.5 |
| Bootstrap (T12) | 1 |
| Tests (T13–T17) | 17 |
| **Total** | **~48 ideal hours (~6 dev-days)** |

---

## 11. Definition of Done

- All P1 tasks complete; all unit + integration tests green (`./vendor/bin/phpunit`) on PHP 8 / MySQL 5.7.
- Institution Admin can open and close the promotion window; action is logged.
- Staff can create a promotion batch (window open, no duplicate pending); eligible/ineligible students correctly identified; exclusion with mandatory reason.
- Batch detail visible to all roles with correct dept scoping.
- Dept Admin and Institution Admin can approve (executing atomically) or reject with reason.
- On approval: included students have updated year/class/section + reset form status; excluded students have `onboarding_status = 'detained'`; batch marked `approved`.
- Rejected batch editable and resubmittable by staff; resubmission sets `requires_inst_admin = 1`; dept_admin gets 403 on resubmission approval.
- All mutations produce `audit_log` entries via `MasterAuditLogger`.
- Notification events created for batch submission, resubmission, approval, and rejection.
- `PromotionBatch::execute()` is transactional — any failure rolls back all changes.
- "Promotion" nav link visible for staff/dept_admin/institution_admin.
- No regression on any earlier module (M1–M11).
- Commit via `scripts/commit-module.sh "M12 Student Promotion: implementation complete"`; user pushes from Mac.

---

## 12. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, implement in Claude Code (final module).
