# SIS — Module 4: Enrolment Number Generation & Approval
## Stage 3: Tasks (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 4 of 12 — Enrolment Number Generation & Approval
**Document stage:** Requirements ✅ → Design ✅ → **Tasks (this document)**
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M4_EnrolmentNumbers_Design.md`

---

## 1. How to read this

Each task: ID, deliverable, estimate (ideal hours), dependencies, "done when". Estimates assume M1–M3 codebase and conventions are in place (Db, Csrf, RoleMiddleware, DeptScopeMiddleware, MasterAuditLogger). P1 = required for the module to function; P2 = hardening/nice-to-have. Build order in §8.

---

## 2. Data layer

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M4-T01 | Migration `017_create_enrolment_batches` — table per design §5.1; index on (department_id, academic_year_id) | 1 | — | P1 | Table + index created on MySQL 5.7 |
| M4-T02 | Migration `018_alter_students_enrolment` — six new columns per design §5.2: `enrolment_number`, `enrolment_serial`, `enrolment_approval_status`, `enrolment_batch_id`, `enrolment_approved_by`, `enrolment_approved_at`; UNIQUE on `enrolment_number`; indexes on batch_id and approval_status | 2 | M4-T01 | P1 | Columns + unique index + FK constraints present; existing rows unaffected (all NULLs) |
| M4-T03 | Config file `config/enrolment.php` — `serial_pad_length = 3` | 0.5 | — | P1 | File exists; `Config::get('enrolment.serial_pad_length')` returns 3 |

---

## 3. Models

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M4-T04 | `EnrolmentBatch` model — `create(deptId, ayId, generatedBy): int`, `find(id): ?array`, `findByDept(deptId, filters): array`, `deriveStatus(batchId): string` (queries students rows; returns 'pending'\|'in_progress'\|'approved'), `summaryByDept(?ayId): array` | 4 | M4-T01 | P1 | `deriveStatus` returns correct string for all three states; unit tested |
| M4-T05 | Extend `Student` model — `findPendingForGeneration(deptId, ayId): array` (status=pending_enrolment, enrolment_approval_status IS NULL), `hasPendingBatch(deptId, ayId): bool` (any student in dept+year has enrolment_approval_status='pending'), `assignEnrolmentNumber(id, number, serial, batchId): void`, `approveNumbers(ids[], approvedBy): int` (bulk UPDATE, returns affected count), `getEnrolmentStatus(id): array` (returns number + approval_status), `findByBatch(batchId, filters, page): array`, `countByBatch(batchId, filters): int` | 6 | M4-T02 | P1 | Each method unit tested; `approveNumbers` uses WHERE enrolment_approval_status='pending' guard |

---

## 4. Helpers

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M4-T06 | `EnrolmentNumberGenerator` helper — `generate(deptId, ayId, generatedById): int` (returns batchId); encapsulates: fetch dept+ay, begin transaction, lock rows, MAX(serial)+1, loop assign via `Student::assignEnrolmentNumber`, create batch record, commit, audit log | 6 | M4-T04, M4-T05 | P1 | Generates correct numbers for UG+PG; serial continuity verified across two calls; concurrent call test shows no duplicate serials |
| M4-T07 | `EnrolmentNumberGenerator::format(array $dept, string $ayValue, int $serial): string` — static pure function; assembles `{YY}{U\|P}{DeptCode}{serial:03d}` | 1 | — | P1 | Unit tested: `format(['code'=>'BCA','level'=>'UG'], '2024-25', 41)` → `'24UBCA041'`; PG variant correct |
| M4-T08 | `GET /enrolment/eligible-count` JSON endpoint — `EnrolmentController::eligibleCount()`; returns `{'count': N}` for a dept+ay; used by generate form JS; requires auth | 1 | M4-T05 | P1 | Returns correct count; 0 when none; 403 for unauthenticated |

---

## 5. Controllers & routes

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M4-T09 | `EnrolmentController::index()` — `GET /enrolment`; dept-scoped batch list with filters (academic year, status); institution_admin sees all depts | 3 | M4-T04 | P1 | Staff sees own dept only; inst_admin sees all; filters work |
| M4-T10 | `EnrolmentController::generateForm()` — `GET /enrolment/generate`; staff/dept_admin only; loads active academic years for select | 2 | M4-T05 | P1 | Blocked with flash if pending batch exists; year select renders |
| M4-T11 | `EnrolmentController::generate()` — `POST /enrolment/generate`; validates CSRF + role + dept scope; calls `EnrolmentNumberGenerator::generate()`; redirects to batch detail | 4 | M4-T06, M4-T10 | P1 | Generates correct numbers; blocks on pending batch; blocks on 0 eligible; redirects to batch/{id} |
| M4-T12 | `EnrolmentController::batchDetail()` — `GET /enrolment/batch/{id}`; all roles (dept-scoped); fetches students in batch paginated + searchable; derives batch status; renders batch.php | 4 | M4-T04, M4-T05 | P1 | Paginated 50/page; search by name/mobile works; status badge correct; 403 for wrong dept |
| M4-T13 | `EnrolmentController::approveAll()` — `POST /enrolment/batch/{id}/approve-all`; dept_admin + inst_admin; calls `Student::approveNumbers` for all pending in batch; audit logs; flash + redirect | 4 | M4-T05, M4-T12 | P1 | All pending rows updated in one transaction; already-approved untouched; flash shows correct count |
| M4-T14 | `EnrolmentController::approveSelected()` — `POST /enrolment/batch/{id}/approve-selected`; validates posted `student_ids[]` belong to batch and are pending; calls `Student::approveNumbers`; audit logs | 4 | M4-T05, M4-T12 | P1 | Only selected+pending rows updated; mismatched IDs skipped; empty selection returns flash danger |
| M4-T15 | `EnrolmentController::summary()` — `GET /enrolment/summary`; institution_admin only; pivot of batch status counts per dept; academic year filter | 2 | M4-T04 | P1 | 403 for non-inst_admin; counts correct |
| M4-T16 | Routes — register all M4 routes in `public/index.php`; apply `AuthMiddleware`; role guards inside controllers | 1 | M4-T09–M4-T15 | P1 | All routes resolve; 403 on role violation; 404 on unknown path |

---

## 6. Views

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M4-T17 | `enrolment/generate.php` — Academic Year select; JS fetch to `/enrolment/eligible-count` on change showing "N students eligible"; submit button disabled until year chosen; CSRF | 3 | M4-T10, M4-T08 | P1 | Count updates on year change; button disabled until selection; CSRF present |
| M4-T18 | `enrolment/batch.php` — header card (dept, ay, generated by, generated at, student count, derived status badge); approval action bar (Approve All + Approve Selected, dept_admin/inst_admin only); select-all checkbox; paginated student table (checkbox, S.No, Name, Mobile, Class, Section, Enrolment Number, Approval Status badge, Approved by, Approved at); search box; Bootstrap confirmation modal for Approve All showing count; already-approved rows have disabled checkbox | 8 | M4-T12, M4-T13, M4-T14 | P1 | Approve All modal shows correct N; checkboxes work; Approve Selected enabled only when ≥1 ticked; staff sees table but no approve buttons; pagination + search work |
| M4-T19 | `enrolment/index.php` — batch list table (Batch ID, Academic Year, Dept [inst_admin only], Count, Approved/Pending counts, Derived Status badge, Generated by, Generated at); filter bar (Academic Year, Status); "Generate New Batch" button (staff/dept_admin) | 3 | M4-T09 | P1 | Filters work; inst_admin sees all depts; link to batch detail works |
| M4-T20 | `enrolment/summary.php` — Bootstrap stat cards per dept (Total / Pending / In Progress / Fully Approved); Academic Year filter select; inst_admin only | 2 | M4-T15 | P1 | Cards reflect live derived counts; filter updates cards |
| M4-T21 | Student dashboard enrolment widget — `Student::getEnrolmentStatus()` called in existing dashboard view; shows full enrolment number when approved, "not assigned yet" otherwise | 2 | M4-T05 | P1 | Approved student sees number; pending/null student sees placeholder text |
| M4-T22 | Update nav layout (`layouts/app.php`) — add "Enrolment Numbers" link for staff/dept_admin/inst_admin | 1 | M4-T16 | P2 | Link appears for correct roles; absent for students |

---

## 7. Tests

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M4-T23 | Unit: `EnrolmentNumberGenerator::format()` — UG + PG; various dept codes; serial padding (1, 41, 999); academic year parsing ("2024-25" → "24", "2026-27" → "26") | 2 | M4-T07 | P1 | All cases green |
| M4-T24 | Unit: `EnrolmentBatch::deriveStatus()` — all pending → 'pending'; mixed → 'in_progress'; all approved → 'approved'; empty batch → 'approved' | 3 | M4-T04 | P1 | Green |
| M4-T25 | Unit: `Student::hasPendingBatch()` — returns true when any student in dept+ay has approval_status='pending'; false otherwise | 2 | M4-T05 | P1 | Green |
| M4-T26 | Unit: `Student::approveNumbers()` — updates only pending rows; skips already-approved; returns correct affected count; sets approved_by + approved_at | 3 | M4-T05 | P1 | Green |
| M4-T27 | Unit: `Student::getEnrolmentStatus()` — returns number when approved; returns null number when pending or not generated | 2 | M4-T05 | P1 | Green |
| M4-T28 | Integration: generation flow — seed dept + ay + 3 pending students; call `EnrolmentNumberGenerator::generate()`; assert 3 student rows have correct enrolment_number, serial, batch_id, approval_status='pending'; assert batch row has student_count=3 | 5 | M4-T06 | P1 | Green |
| M4-T29 | Integration: serial continuity — generate batch 1 (serials 001–003); approve all; generate batch 2; assert serials start at 004 | 3 | M4-T06 | P1 | Green |
| M4-T30 | Integration: pending batch blocks new generation — generate batch 1 (do not approve); call generate again; assert `hasPendingBatch()` returns true and controller blocks | 2 | M4-T06, M4-T11 | P1 | Green |
| M4-T31 | Integration: approve-all — generate batch; call `approveNumbers` for all; assert all rows have status='approved' and onboarding_status='enrolment_assigned'; assert audit_log has entry | 4 | M4-T13 | P1 | Green |
| M4-T32 | Integration: approve-selected — generate batch of 5; approve 2 by ID; assert only those 2 updated; batch deriveStatus = 'in_progress' | 3 | M4-T14 | P1 | Green |
| M4-T33 | Integration: RBAC — staff cannot POST to approve-all or approve-selected (403); inst_admin can approve a batch in another dept | 3 | M4-T13, M4-T14 | P1 | Green |
| M4-T34 | Integration: unique enrolment_number DB constraint — attempt to INSERT duplicate enrolment_number; assert PDO exception caught correctly | 2 | M4-T02 | P2 | Green |
| M4-T35 | Integration: audit log — generation and approval each produce correct audit_log rows | 2 | M4-T06, M4-T13 | P2 | Green |

---

## 8. Build order (critical path)

1. **Data layer:** M4-T01 → M4-T02 → M4-T03
2. **Helpers (pure):** M4-T07 (format function, no DB dep)
3. **Models:** M4-T04, M4-T05
4. **Helpers (DB):** M4-T06 (needs T04, T05) → M4-T08
5. **Controllers:** M4-T09, M4-T10, M4-T11 → M4-T12 → M4-T13, M4-T14 → M4-T15, M4-T16
6. **Views:** M4-T17 → M4-T18 → M4-T19, M4-T20, M4-T21, M4-T22
7. **Tests:** M4-T23–M4-T27 (unit, alongside code); M4-T28–M4-T35 (integration, after controllers)

---

## 9. Estimate summary

| Group | Hours |
|-------|------:|
| Data layer (T01–T03) | 3.5 |
| Models (T04–T05) | 10 |
| Helpers (T06–T08) | 8 |
| Controllers & routes (T09–T16) | 24 |
| Views (T17–T22) | 19 |
| Tests (T23–T35) | 36 |
| **Total** | **~100 ideal hours (~13 dev-days)** |

---

## 10. Definition of Done

- All P1 tasks complete; all unit + integration tests green (`./vendor/bin/phpunit`) on PHP 8 / MySQL 5.7.
- Generation produces correctly formatted numbers; serial continuity verified across batches.
- Approve All and Approve Selected both update only pending rows in a single transaction; already-approved rows are untouched.
- Batch status (Pending / In Progress / Approved) is always correctly derived from student rows.
- Student dashboard shows full enrolment number only when `enrolment_approval_status = 'approved'`; otherwise shows placeholder.
- RBAC: staff cannot approve; inst_admin can approve any dept's batch; dept-scope guard returns 403 for wrong dept.
- Every generation and approval action has an `audit_log` row.
- M3 correction confirmed: `login_enabled = 1` in migration 014 and `Student::create()` (already applied).
- Commit via `scripts/commit-module.sh "M4 Enrolment Numbers: implementation complete"`; user pushes from Mac.

---

## 11. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, Module 4 is fully specified and ready for implementation in Claude Code. After confirming "Module 4 done", the Module 5 spec cycle (Student Information Form) will begin.
