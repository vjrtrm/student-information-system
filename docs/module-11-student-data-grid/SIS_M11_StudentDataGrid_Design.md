# SIS — Module 11: Student Data Grid & Export
## Stage 2: Design (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 11 of 12 — Student Data Grid & Export
**Document stage:** Requirements ✅ → **Design (this document)** → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M11_StudentDataGrid_Requirements.md`

---

## 1. Design goals

- A single `StudentGridController` handles both the grid (GET) and export (GET with `?export=1`); one shared query builder keeps the WHERE clause consistent between paginated display and full-result export.
- All filtering and sort state lives in the URL query string — no session state — so links are shareable and filters survive page reload.
- Department scoping is injected unconditionally at the query builder level for non-institution-admin roles; it cannot be bypassed from the view.
- Export streams directly from PhpSpreadsheet to the browser (`php://output`) without writing a temp file.
- Custom field columns in the export are appended after the built-in profile columns, in section order, using `FieldConfig::resolveCustomFields()` from M10.

---

## 2. Resolved design decisions

| # | Question | Decision |
|---|----------|----------|
| 1 | Show all students or only those with an enrolment serial? | **All students**, regardless of onboarding or enrolment status. |
| 2 | PDF download link per row? | **No** — "View" link only (links to M5 staffView). |
| 3 | Include document path columns in export? | **No** — document path columns excluded; they are storage paths, not useful in Excel. |
| 4 | Export column order? | **Section-grouped** — Personal Details → Contact & Address → Family Details → Qualification Details → Admission Details → Bank & Scholarship Details — matching the student form. |

---

## 3. Component architecture (MVC)

### New controller

**`app/Controllers/StudentGridController.php`** — two actions:

| Method | Route | Roles | Description |
|--------|-------|-------|-------------|
| `index()` | GET /students | staff, dept_admin, institution_admin | Paginated grid with filters |
| `export()` | GET /students/export | staff, dept_admin, institution_admin | Full-result .xlsx download |

Both actions call the same private `buildQuery(array $filters, bool $forCount): string` / `buildParams(array $filters): array` helpers to guarantee the WHERE clause is identical.

### Query builder design

`StudentGridController` contains private methods:

```
parseFilters(): array        — sanitise and extract filter values from $_GET
buildWhere(array $filters): array  — returns [whereClause string, params array]
fetchPage(array $filters, int $page, int $perPage, string $sort, string $dir): array
fetchCount(array $filters): int
fetchAll(array $filters, string $sort, string $dir): array  — for export
```

**Base JOIN** (same for all queries):
```sql
SELECT s.id, s.first_name, s.last_name, s.mobile,
       s.enrolment_number, s.enrolment_serial, s.enrolment_approval_status,
       s.programme_level, s.onboarding_status,
       s.academic_year_id,
       sp.form_status, sp.form_completion_pct,
       d.name  AS dept_name,
       ov.display AS academic_year_label
FROM   students s
LEFT JOIN student_profiles sp ON sp.student_id = s.id
LEFT JOIN departments      d  ON d.id = s.department_id
LEFT JOIN option_values    ov ON ov.id = s.academic_year_id
```

**WHERE clause components** (AND-composed):

| Filter | SQL fragment |
|--------|-------------|
| Dept scope (non-inst_admin) | `s.department_id = ?` |
| Department filter (inst_admin) | `s.department_id = ?` |
| Search (name / enrolment / mobile) | `(CONCAT(s.first_name,' ',s.last_name) LIKE ? OR s.enrolment_number LIKE ? OR s.mobile LIKE ?)` |
| Academic year | `s.academic_year_id = ?` |
| Programme level | `s.programme_level = ?` |
| Form status | `sp.form_status IN (?,?,...)` |
| Enrolment approval status | `s.enrolment_approval_status = ?` (or `IS NULL` for "Not Generated") |

**Allowed sort columns** (whitelist to prevent SQL injection):
```php
private const SORT_COLUMNS = [
    'enrolment_number' => 's.enrolment_number',
    'name'             => 's.first_name',
    'form_status'      => 'sp.form_status',
    'programme_level'  => 's.programme_level',
];
private const DEFAULT_SORT = 'enrolment_number';
private const DEFAULT_DIR  = 'ASC';
```

### Views

| File | Purpose |
|------|---------|
| `students/index.php` | Grid view — filter bar, stat chips, table, pagination, export button |

No separate export view — export streams directly from the controller.

### Export design

`export()` action:
1. Parse filters from `$_GET` (same `parseFilters()`).
2. Fetch all matching rows via `fetchAll()`.
3. Determine custom field columns: `FieldConfig::resolveCustomFields($deptId)` for dept roles; for institution_admin fetch all active custom fields (institution-scoped + all dept-scoped).
4. Load all `student_custom_data` rows for the student IDs in the result set in one batch query — index as `$customData[$studentId][$customFieldId]`.
5. Build column header row: fixed built-in columns (section-grouped, doc paths excluded) + custom field label columns.
6. Stream .xlsx via PhpSpreadsheet `\PhpOffice\PhpSpreadsheet\Writer\Xlsx`.
7. Log to audit_log via `MasterAuditLogger`.

**Built-in export columns** (section-grouped, doc paths excluded):

*Personal Details:* first_name, last_name, gender, dob, mobile, programme_level, academic_year (label), class, section, admission_date, blood_group, mother_tongue, religion, caste, caste_category, sub_caste, nationality, place_of_birth, aadhaar_number, student_email, alternate_mobile, marital_status, physically_challenged, disability_nature, first_graduate, annual_family_income

*Contact & Address:* perm_address1, perm_address2, perm_city, perm_taluk, perm_district, perm_state, perm_pincode, comm_same_as_perm, comm_address1, comm_address2, comm_city, comm_taluk, comm_district, comm_state, comm_pincode

*Family Details:* family_situation, father_name, father_occupation, father_qualification, father_annual_income, father_mobile, father_email, mother_name, mother_occupation, mother_qualification, mother_annual_income, mother_mobile, mother_email, guardian_name, guardian_relationship, guardian_mobile, guardian_address, guardian_email

*Qualification Details:* qual_sslc, qual_hsc, qual_ug, qual_diploma, qual_other_1, qual_other_2

*Admission Details:* admission_type, entrance_exam_name, entrance_hall_ticket, entrance_rank_score, admission_number, community_cert_number, transfer_cert_number

*Bank & Scholarship Details:* bank_account_holder, bank_name, bank_branch, bank_account_number, bank_ifsc, scholarship_applied, scholarship_scheme, scholarship_app_number

*Grid metadata:* enrolment_number, enrolment_approval_status, form_status, form_completion_pct, department_name

Geography fields (taluk, district, state) are resolved to names via JOIN in a separate query for export.

---

## 4. Data model

No new tables or migrations. All data already exists in:
- `students` (M3/M4 columns)
- `student_profiles` (M5)
- `departments` (M2)
- `option_values` (M2 — academic year labels)
- `custom_fields`, `student_custom_data` (M10)
- `states`, `districts`, `taluks` (M2 geography)

---

## 5. Flows

### 5.1 Grid page load

```
GET /students?search=John&form_status[]=submitted&sort=name&dir=ASC&page=2

StudentGridController::index()
  → RoleMiddleware::handle(['staff','dept_admin','institution_admin'])
  → parseFilters($_GET)        → $filters array
  → buildWhere($filters)       → [$where, $params]
  → fetchCount($filters)       → $total
  → fetchPage($filters, $page=2, $perPage=25, 'name', 'ASC') → $rows
  → Fetch stat chips (total/submitted/approved for current scope — 3 simple COUNT queries)
  → Fetch academic year options, department list (for filter dropdowns)
  → Render students/index.php
```

### 5.2 Export

```
GET /students/export?search=John&form_status[]=submitted

StudentGridController::export()
  → RoleMiddleware::handle([...])
  → parseFilters($_GET)
  → fetchAll($filters, DEFAULT_SORT, DEFAULT_DIR)  → $rows (all matching)
  → resolveCustomFields for export
  → batch-load student_custom_data for $studentIds
  → resolve geography names (single JOIN query)
  → Build PhpSpreadsheet Spreadsheet object:
      - Sheet "Students"
      - Row 1: column headers (bold, frozen)
      - Rows 2+: student data
  → MasterAuditLogger::log(actor, 'export', 'student_grid', null, ['filters'=>$filters, 'count'=>count($rows)])
  → Set response headers: Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
                          Content-Disposition: attachment; filename="students_export_YYYY-MM-DD.xlsx"
  → $writer->save('php://output'); exit
```

---

## 6. RBAC & department scoping

- Non-institution-admin roles: `buildWhere()` always prepends `s.department_id = Auth::departmentId()` as the first WHERE condition, regardless of any `dept` query parameter.
- Institution admin: no mandatory department filter, but a `dept` query parameter is accepted and applied if present.
- `export()` applies the same role-scoping as `index()`.
- Accessing `/students` or `/students/export` as a student role → 403 (RoleMiddleware).

---

## 7. Session / security

| Rule | Implementation |
|------|----------------|
| Dept scoping | WHERE clause always applied for non-inst_admin; cannot be overridden by query param |
| Sort injection | Column name validated against `SORT_COLUMNS` whitelist; direction validated to `ASC`/`DESC` |
| SQL injection | All values via PDO prepared statements |
| Export audit | `MasterAuditLogger` entry on every export |
| CSRF | Not applicable — GET requests only |
| No student role | RoleMiddleware excludes student role |

---

## 8. Screen behaviour

### Grid page (`/students`)

- **Filter bar** (above table): search text input, dropdowns for dept (inst_admin only), academic year, programme level, form status (checkboxes or multi-select), enrolment status. "Apply Filters" submit button + "Clear" link.
- **Stat chips**: "Total: N | Submitted: N | Approved: N" for the current filter scope.
- **Export button**: top-right, "Export to Excel (.xlsx)"; links to `/students/export` with same query params.
- **Table**: fixed columns as per §2.1; form_status and enrolment_approval_status shown as colour-coded Bootstrap badges.
- **Sortable headers**: clicking adds/toggles `sort=column&dir=ASC|DESC` to URL; current sort indicated with ▲/▼ icon.
- **Pagination**: Bootstrap pagination; "Showing 26–50 of 143 students" summary above table; per-page selector (25/50/100).
- **Empty state**: "No students match the current filters." with a "Clear filters" link.

### Flash messages

| Action | Message |
|--------|---------|
| Export (audit only — no visible flash) | — |

---

## 9. Traceability (requirement → design)

| Requirement | Design element |
|-------------|---------------|
| A1 — Staff grid (own dept) | `index()` + dept scope in buildWhere |
| A2 — Institution Admin cross-dept | Dept filter in parseFilters; no forced dept WHERE for inst_admin |
| A3 — Search & filters | `buildWhere()` WHERE components; URL query string persistence |
| A4 — Sortable columns | SORT_COLUMNS whitelist; sort/dir query params |
| B1 — Excel export | `export()` + PhpSpreadsheet stream; custom field columns from M10 |
| B2 — Cross-dept export | resolveCustomFields for all active fields; blank cells for non-applicable |
| NFR — Performance | Shared WHERE builder; LIMIT/OFFSET pagination; batch custom data load |
| NFR — Audit | MasterAuditLogger on every export |
| NFR — Dept scoping | WHERE clause injected unconditionally for non-inst_admin |

---

## 10. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, proceed to Tasks.
