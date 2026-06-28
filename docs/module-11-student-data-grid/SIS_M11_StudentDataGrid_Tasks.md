# SIS — Module 11: Student Data Grid & Export
## Stage 3: Tasks (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 11 of 12 — Student Data Grid & Export
**Document stage:** Requirements ✅ → Design ✅ → **Tasks (this document)**
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M11_StudentDataGrid_Design.md`

---

## 1. How to read this

Each task: ID, deliverable, estimate (ideal hours), dependencies, priority, "done when". P1 = required for the module to function; P2 = hardening/polish. Build order in §8.

---

## 2. Migrations

No new tables or migrations. All data exists from M2–M10.

---

## 3. Controller

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M11-T01 | `StudentGridController` (`app/Controllers/StudentGridController.php`) — implements two public actions (`index()`, `export()`) plus private helpers: `parseFilters(): array`; `buildWhere(array $filters): array` (returns `[$whereClause, $params]`); `fetchCount(array $filters): int`; `fetchPage(array $filters, int $page, int $perPage, string $sort, string $dir): array`; `fetchAll(array $filters, string $sort, string $dir): array`; `fetchStatChips(array $filters): array` (returns `[total, submitted, approved]` counts). Base JOIN includes students + student_profiles (LEFT) + departments + option_values (for academic year label). SORT_COLUMNS whitelist constant (`enrolment_number`, `name`, `form_status`, `programme_level`). DEFAULT_SORT = `enrolment_number`, DEFAULT_DIR = `ASC`. | 6 | — | P1 | Controller instantiates without error; buildWhere produces correct SQL fragment for each filter type |
| M11-T02 | `index()` action — `RoleMiddleware::handle(['staff','dept_admin','institution_admin'])`; call `parseFilters()`; enforce dept scope (`Auth::departmentId()`) in filters for non-inst_admin roles (cannot be overridden by query param); `fetchCount()` + `fetchPage()`; `fetchStatChips()`; load academic year options (from option_values JOIN option_lists WHERE list_key='academic_year'); load department list for filter dropdown (inst_admin only); render `students/index.php` with all vars | 3 | M11-T01 | P1 | Grid renders with correct rows; dept-scoped roles cannot see other-dept students; inst_admin sees all |
| M11-T03 | `export()` action — same role guard + `parseFilters()` + dept scope; `fetchAll()`; load custom fields: for dept roles `FieldConfig::resolveCustomFields(Auth::departmentId())`; for inst_admin fetch all active custom fields across all departments (one query: `SELECT * FROM custom_fields WHERE status='active' ORDER BY section, sort_order, id`); batch-load `student_custom_data` for all student IDs (`SELECT student_id, custom_field_id, value FROM student_custom_data WHERE student_id IN (...)`); resolve geography names (states/districts/taluks) via single JOIN query keyed by student_id; build and stream .xlsx (see §4); `MasterAuditLogger::log(...)` with filter snapshot and row count; set Content-Type + Content-Disposition headers; `$writer->save('php://output')`; exit | 6 | M11-T01 | P1 | .xlsx downloads with correct column headers and data; custom field columns present; doc path columns absent; audit log entry written |

---

## 4. Export builder (within T03)

The `.xlsx` is built with `\PhpOffice\PhpSpreadsheet\Spreadsheet`. Column set (in order):

**Identification columns** (always first):
`Enrolment Number`, `First Name`, `Last Name`, `Gender`, `Date of Birth`, `Mobile`, `Programme Level`, `Department`, `Academic Year`, `Class`, `Section`, `Admission Date`, `Enrolment Approval Status`, `Form Status`, `Form Completion %`

**Personal Details** (excluding locked identity fields already above and doc paths):
`Blood Group`, `Mother Tongue`, `Religion`, `Caste`, `Caste Category`, `Sub-Caste`, `Nationality`, `Place of Birth`, `Aadhaar Number`, `Student Email`, `Alternate Mobile`, `Marital Status`, `Physically Challenged`, `Disability Nature`, `First Graduate`, `Annual Family Income`

**Contact & Address**:
`Perm Address 1`, `Perm Address 2`, `Perm City`, `Perm Taluk`, `Perm District`, `Perm State`, `Perm Pincode`, `Comm Same as Perm`, `Comm Address 1`, `Comm Address 2`, `Comm City`, `Comm Taluk`, `Comm District`, `Comm State`, `Comm Pincode`

**Family Details**:
`Family Situation`, `Father Name`, `Father Occupation`, `Father Qualification`, `Father Annual Income`, `Father Mobile`, `Father Email`, `Mother Name`, `Mother Occupation`, `Mother Qualification`, `Mother Annual Income`, `Mother Mobile`, `Mother Email`, `Guardian Name`, `Guardian Relationship`, `Guardian Mobile`, `Guardian Address`, `Guardian Email`

**Qualification Details** (no doc path columns):
`SSLC`, `HSC`, `UG`, `Diploma`, `Other Qual 1`, `Other Qual 2`

**Admission Details** (no doc path columns):
`Admission Type`, `Entrance Exam Name`, `Entrance Hall Ticket`, `Entrance Rank/Score`, `Admission Number`, `Community Cert Number`, `Transfer Cert Number`

**Bank & Scholarship Details**:
`Bank Account Holder`, `Bank Name`, `Bank Branch`, `Bank Account Number`, `Bank IFSC`, `Scholarship Applied`, `Scholarship Scheme`, `Scholarship App Number`

**Custom field columns** (appended after built-in columns):
One column per active custom field, header = `cf['label']`; value from `$customData[$studentId][$cfId] ?? ''`.

Header row: bold, background fill `#4472C4` (blue), white font, frozen row 1.
Column widths: auto-size (PhpSpreadsheet `getDefaultColumnDimension()->setAutoSize(true)` or iterate columns).

---

## 5. Routes

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M11-T04 | Add to `public/index.php`: `use App\Controllers\StudentGridController;`; add routes (static before wildcard): `['GET', '/students/export', [StudentGridController::class, 'export'], ['auth']]`, `['GET', '/students', [StudentGridController::class, 'index'], ['auth']]`. Insert before the Module 5 `/student/form` routes (to avoid wildcard collision). | 0.5 | M11-T01 | P1 | Both routes resolve; `/students/export` returns .xlsx; `/students` returns HTML |

---

## 6. View

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M11-T05 | `app/Views/students/index.php` — Bootstrap 5; filter bar (search text, dept dropdown for inst_admin, academic year dropdown, programme level dropdown, form status checkboxes, enrolment status dropdown, Apply/Clear); stat chips row (Total / Submitted / Approved); "Export to Excel" button linking to `/students/export?{current query string}`; paginated table with columns: Enrolment No., Name (linked to staffView), Programme Level, Department (inst_admin only), Academic Year, Form Status badge, Enrolment Status badge, Actions (View link); sortable column headers (Name, Enrolment No., Form Status, Programme Level) with ▲/▼ indicator; pagination controls (prev/next/numbered); per-page selector (25/50/100); "Showing X–Y of Z students" summary; empty state when no results | 6 | M11-T02 | P1 | All filter controls render and submit correctly; badges colour-coded; pagination works; sort links toggle direction; export button carries current filters |
| M11-T06 | Nav update — add "Students" link in `app/Views/layouts/app.php` for `staff`, `dept_admin`, `institution_admin` roles, pointing to `/students`; active when URI starts with `/students` | 0.5 | M11-T04 | P2 | Nav link visible for staff/dept_admin/inst_admin; not shown for student role |

---

## 7. Tests

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M11-T07 | Unit: `StudentGridQueryTest` (`tests/Unit/StudentGridQueryTest.php`) — instantiate controller via reflection or extract buildWhere logic to a standalone `StudentGridQuery` helper; test: no filters → WHERE clause is empty (or just dept scope); search filter → LIKE clause present; form_status filter → IN clause with correct values; sort column whitelist → unknown sort key falls back to default; direction whitelist → unknown dir falls back to ASC | 3 | M11-T01 | P1 | Green |
| M11-T08 | Integration: `StudentGridIndexTest` (`tests/Integration/StudentGridIndexTest.php`) — seed two departments, seed students in each; staff user in dept A → fetchPage returns only dept A students; inst_admin user → fetchPage returns all; search filter → only matching students returned; form_status filter → only matching form_status rows returned; pagination → correct LIMIT/OFFSET applied | 4 | M11-T02 | P1 | Green |
| M11-T09 | Integration: `StudentGridExportTest` (`tests/Integration/StudentGridExportTest.php`) — seed student + profile + custom field + student_custom_data; call export logic (extract to testable method or use output buffering); assert returned column headers include custom field label; assert row contains correct student name; assert doc path column headers absent; assert audit_log entry written with correct action | 4 | M11-T03 | P1 | Green |

---

## 8. Build order (critical path)

1. **Controller skeleton:** M11-T01 (private helpers + constants, no DB calls yet)
2. **index() action:** M11-T02 (grid query + render)
3. **export() action:** M11-T03 (export builder + streaming)
4. **Routes:** M11-T04
5. **View:** M11-T05
6. **Nav:** M11-T06
7. **Tests:** M11-T07 (unit, alongside T01) → M11-T08, M11-T09 (integration, after T02/T03)

---

## 9. Estimate summary

| Group | Hours |
|-------|------:|
| Controller (T01–T03) | 15 |
| Routes (T04) | 0.5 |
| View (T05–T06) | 6.5 |
| Tests (T07–T09) | 11 |
| **Total** | **~33 ideal hours (~4 dev-days)** |

---

## 10. Definition of Done

- All P1 tasks complete; all unit + integration tests green (`./vendor/bin/phpunit`) on PHP 8 / MySQL 5.7.
- `/students` grid renders for staff (own dept), dept_admin (own dept), institution_admin (all depts with filter).
- Search, all filter types, sorting, and pagination work correctly and persist in the URL.
- `/students/export` downloads a valid .xlsx file with all built-in profile columns (no doc path columns) + custom field columns for the current filter scope; empty cells for missing values.
- Dept scoping enforced: staff/dept_admin cannot retrieve other-dept students via any query param.
- Every export produces an audit_log entry via `MasterAuditLogger`.
- "Students" nav link visible and active for staff/dept_admin/institution_admin.
- No regression on M5 staffView, M10 FieldConfig, or any earlier module.
- Commit via `scripts/commit-module.sh "M11 Student Data Grid & Export: implementation complete"`; user pushes from Mac.

---

## 11. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, implement in Claude Code.
