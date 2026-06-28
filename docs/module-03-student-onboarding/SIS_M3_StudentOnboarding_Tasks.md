# SIS — Module 3: Student Onboarding
## Stage 3: Tasks (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 3 of 12 — Student Onboarding
**Document stage:** Requirements ✅ → Design ✅ → **Tasks (this document)**
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M3_StudentOnboarding_Design.md`

---

## 1. How to read this

Each task: ID, deliverable, estimate (ideal hours), dependencies, "done when". Estimates assume the M1 + M2 codebase and conventions (Db, Validator, Csrf, AuditLogger, RoleMiddleware, SpreadsheetImport) are in place. P1 = required for the module to function; P2 = hardening/nice-to-have. Build order in §8.

---

## 2. Data layer

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M3-T01 | Migration `014_students` — all onboarding columns per design §4.1 (mobile UNIQUE, status ENUM, login_enabled, FK constraints, indexes on department_id / status / academic_year_id / (first_name, last_name, dob)) | 3 | — | P1 | Runs on MySQL 5.7; all indexes + constraints present |
| M3-T02 | Migration `015_upload_batches` — per design §4.2, FK→departments + users | 1 | T01 | P1 | Table + FK created |
| M3-T03 | Migration `016_duplicate_override_requests` — per design §4.3, FK→upload_batches + students + users, index(status), index(upload_batch_id) | 2 | T01,T02 | P1 | Table + FKs + indexes created |

---

## 3. Models

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M3-T04 | `Student` model — `create($data)`, `findByMobile($mobile)`, `findByNameDob($first, $last, $dob)`, `getList($filters, $page)`, `updateStatus($id, $status)`, `enableLogin($id)` | 5 | T01 | P1 | Unit tests for each method pass; mobile uniqueness raises on duplicate |
| M3-T05 | `UploadBatch` model — `create($data)`, `findById($id)`, `updateCounts($id, $created, $held, $failed)` | 2 | T02 | P1 | Returns correct row; counts update correctly |
| M3-T06 | `DuplicateOverrideRequest` model — `create($data)`, `findPendingByBatch($batchId)`, `findPendingByDept($deptId)`, `approve($id, $adminId)`, `reject($id, $adminId)` | 4 | T03 | P1 | Status transitions correct; reviewed_by + reviewed_at set |

---

## 4. Helpers

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M3-T07 | `OnboardingValidator` helper — validates all 11 onboarding fields per design §7 rules; returns field-keyed error array | 4 | — | P1 | All validation branches covered by unit tests (valid, missing, malformed, age < 15, future admission date) |
| M3-T08 | `DuplicateDetector` helper — `check($data, $excludeId=null)` → returns `null` or array `['type'=>'mobile_exists'|'name_dob_exists'|'both', 'existing_student_id'=>N]`; uses `Student::findByMobile` + `Student::findByNameDob` | 3 | T04 | P1 | Returns correct type for each scenario; excludeId allows future edit flows |
| M3-T09 | Extend `SpreadsheetImport` (from M2-T11) — add `buildTemplate($dept, $optionValues)` to generate the two-sheet upload template; add `parseOnboarding($file)` to read rows into typed arrays | 4 | — | P1 | Template contains correct headers + reference sheet; parser returns typed row array with row numbers |

---

## 5. Controllers & routes

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M3-T10 | `OnboardingController::downloadTemplate()` — `GET /onboarding/template`; calls `SpreadsheetImport::buildTemplate` with live master data; streams .xlsx response | 2 | T09 | P1 | Downloaded file has correct headers; reference sheet values match active master data |
| M3-T11 | `OnboardingController::upload()` — `POST /onboarding/upload`; file validation (MIME, ext, size, row count); calls `SpreadsheetImport::parseOnboarding`, `OnboardingValidator`, `DuplicateDetector`; per-row INSERT or hold; creates `UploadBatch`; stores failed rows in session; redirects to result | 10 | T04,T05,T06,T07,T08,T09 | P1 | Creates valid rows, holds duplicates, fails invalid rows; counts in upload_batches match actual outcomes |
| M3-T12 | `OnboardingController::result()` — `GET /onboarding/result/{batchId}`; dept-scope guard; fetches batch + pending override requests + session failed rows; renders result.php | 3 | T05,T06,T11 | P1 | Correct counts rendered; dept guard returns 403 for wrong dept |
| M3-T13 | `OnboardingController::downloadErrors()` — `GET /onboarding/result/{batchId}/errors.xlsx`; rebuilds failed rows from session; streams error report .xlsx | 3 | T09,T12 | P1 | Downloaded file has original columns + "Error" column; only failed rows present |
| M3-T14 | `OnboardingController::reviewDuplicates()` + `resolveDuplicates()` — `GET/POST /onboarding/duplicates/{batchId}`; renders held rows; processes skip/override-request actions; validates reason_note on override | 5 | T06,T08 | P1 | Skip removes request; Override sets reason_note + stays pending; AuditLogger called |
| M3-T15 | `OnboardingController::pendingOverrides()` + `approveOverride()` + `rejectOverride()` — `GET /onboarding/overrides`, `POST /onboarding/overrides/{id}/approve|reject`; dept_admin only; INSERT student on approve; AuditLogger called | 6 | T04,T06,T09 | P1 | Student created on approve; status transitions correct; 403 if wrong dept |
| M3-T16 | `OnboardingController::showAdd()` + `store()` — `GET/POST /onboarding/add`; single-record form; validator + duplicate detector; inline warning + override path if duplicate | 5 | T04,T07,T08 | P1 | Clean record created; duplicate warning shown; override request persisted if chosen |
| M3-T17 | `OnboardingController::index()` — `GET /onboarding`; dept-scoped student list with status/academic_year/dept filters + name/mobile search + pagination (50/page) | 4 | T04 | P1 | Filters narrow results; inst_admin sees all depts; pagination works |
| M3-T18 | `OnboardingController::summary()` — `GET /onboarding/summary`; institution_admin only; per-dept status counts grouped by academic year | 3 | T04 | P1 | Counts match students table; 403 for non-inst_admin |
| M3-T19 | Routes file — register all M3 routes; apply `AuthMiddleware` everywhere; `RoleMiddleware(['staff','dept_admin'])` on upload/add/result/duplicates; `RoleMiddleware(['dept_admin'])` on overrides; `RoleMiddleware(['institution_admin'])` on summary; `DeptScopeMiddleware` on all dept-scoped routes | 2 | T10–T18 | P1 | Correct 403s for each role boundary; CSRF on all POSTs |

---

## 6. Views

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M3-T20 | `onboarding/upload.php` — Bootstrap card; file input (.xlsx accept); JS client-side size check (> 5 MB warn); spinner on submit; CSRF token | 2 | T11 | P1 | File type/size hint shown; spinner fires on submit |
| M3-T21 | `onboarding/result.php` — summary banner (Created/Held/Failed/Total); three collapsible panels (green/amber/red); "Download Error Report" button conditional on failed > 0; "Review Held Rows" link conditional on held > 0 | 4 | T12,T13,T14 | P1 | Correct counts; panels collapse/expand; buttons appear only when relevant |
| M3-T22 | `onboarding/duplicates.php` — table of held rows; per-row radio (Skip / Override); Override reveals reason textarea (JS); form validates reason non-empty before submit; CSRF | 4 | T14 | P1 | Radio toggle works; empty reason blocked client + server side |
| M3-T23 | `onboarding/override_review.php` — dept_admin view; table with student data, existing record, reason note; per-row Approve/Reject buttons with confirmation modal; CSRF | 4 | T15 | P1 | Modal fires on button click; correct action POSTed |
| M3-T24 | `onboarding/add.php` — Bootstrap form with all 11 fields; Academic Year / Class / Section `<select>` from master data; inline duplicate warning panel (amber) on POST with duplicate; Override checkbox + reason textarea revealed by JS; CSRF | 5 | T16 | P1 | Form retains values after failed POST; duplicate warning shown; override path works |
| M3-T25 | `onboarding/index.php` — student table (columns: Name, Mobile, Dept, Programme, Academic Year, Class, Status, Added by, Date); filter bar (Status multi-select, Academic Year, Dept for inst_admin); search box (name/mobile); Bootstrap pagination; status badges colour-coded | 5 | T17 | P1 | Filters + search narrow table correctly; pagination links work |
| M3-T26 | `onboarding/summary.php` — Bootstrap stat cards per dept (Total/Pending/Assigned/Submitted/Approved); Academic Year filter (select); inst_admin only | 3 | T18 | P1 | Cards reflect live counts; filter changes counts |
| M3-T27 | Dept Admin dashboard badge — pending override count injected into nav via shared layout; updates after approve/reject | 2 | T15 | P2 | Badge shows correct count; disappears when 0 |

---

## 7. Tests

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M3-T28 | Unit: `OnboardingValidator` — all 11 fields × valid + each failure mode (missing, format, age < 15, future date, wrong dept) | 5 | T07 | P1 | All branches green |
| M3-T29 | Unit: `DuplicateDetector` — no match, mobile match, name+DOB match, both match; excludeId prevents self-match | 3 | T08,T04 | P1 | Green |
| M3-T30 | Unit: `SpreadsheetImport::buildTemplate` — correct sheet structure; `parseOnboarding` — valid rows, missing columns, extra columns | 3 | T09 | P1 | Green |
| M3-T31 | Unit: `Student` model — create, findByMobile, findByNameDob, getList filters, updateStatus, enableLogin | 4 | T04 | P1 | Green; mobile uniqueness throws on second insert |
| M3-T32 | Unit: `DuplicateOverrideRequest` model — status transitions (pending→approved, pending→rejected); reviewed_by set | 3 | T06 | P1 | Green |
| M3-T33 | Integration: bulk upload — file with valid rows + duplicate rows + failing rows → correct created/held/failed counts; upload_batches row reflects counts; students table row count correct | 6 | T11 | P1 | Green |
| M3-T34 | Integration: duplicate resolution — skip removes request; override-request stays pending with reason; Dept Admin approve creates student + logs; reject discards + logs | 5 | T14,T15 | P1 | Green |
| M3-T35 | Integration: single-add — clean record created; duplicate triggers warning; override path persists request | 3 | T16 | P1 | Green |
| M3-T36 | Integration: RBAC — staff cannot reach overrides or summary; inst_admin cannot upload; dept-scope guard returns 403 for wrong dept on result/duplicates | 4 | T19 | P1 | Green |
| M3-T37 | Integration: audit log — every write action (create, override_requested, override_approved, override_rejected, skipped) produces an audit_log row | 3 | T11,T14,T15 | P1 | Green |
| M3-T38 | Edge cases: empty file, file > 5 MB, > 1,000 rows, header-only file, concurrent mobile collision at DB level (unique constraint caught) | 4 | T11 | P2 | All handled without 500 errors; user-friendly messages shown |

---

## 8. Build order (critical path)

1. **Data layer:** T01 → T02 → T03
2. **Helpers (parallel after data):** T07, T09; then T08 (needs T04)
3. **Models:** T04 (needs T01), T05 (needs T02), T06 (needs T03)
4. **Controllers:** T10, T11 → T12 → T13, T14, T16 → T15, T17, T18 → T19
5. **Views:** T20 → T21 → T22, T23, T24, T25, T26, T27
6. **Tests:** T28–T32 (unit, alongside code); T33–T38 (integration, after controllers)

---

## 9. Estimate summary

| Group | Hours |
|-------|------:|
| Data layer (T01–T03) | 6 |
| Models (T04–T06) | 11 |
| Helpers (T07–T09) | 11 |
| Controllers & routes (T10–T19) | 43 |
| Views (T20–T27) | 29 |
| Tests (T28–T38) | 43 |
| **Total** | **~143 ideal hours (~18 dev-days)** |

---

## 10. Definition of Done

- All P1 tasks complete; all unit + integration tests green (`./vendor/bin/phpunit`) on PHP 8 / MySQL 5.7.
- Bulk upload (1,000-row file) processes in < 30 s; result screen shows correct Created / Held / Failed counts.
- Duplicate detection fires for mobile-match and name+DOB-match; override approval requires Dept Admin in-app confirmation.
- Single-record add works with same validation + duplicate path.
- RBAC boundaries verified: staff cannot approve overrides; inst_admin cannot upload; dept-scope guard blocks cross-dept access.
- Every write action has an `audit_log` row.
- `login_enabled` defaults to 0; students cannot log in until Module 4 flips the flag.
- Commit via `scripts/commit-module.sh "M3 Student Onboarding: implementation complete"`; user pushes from Mac.

---

## 11. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, Module 3 is fully specified and ready for implementation in Claude Code. After confirming "Module 3 done" (tests green + pushed), the Module 4 spec cycle (Enrolment Number Generation & Approval) will begin.
