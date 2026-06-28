# SIS — Module 11: Student Data Grid & Export
## Stage 1: Requirements (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 11 of 12 — Student Data Grid & Export
**Document stage:** **Requirements (this document)** → _Design_ → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Builds on:** Authentication (M1), Master Data (M2), Student Onboarding (M3), Enrolment Numbers (M4), Student Information Form (M5), Field Management (M10)

---

## 1. Purpose & objectives

Modules M3–M10 create and maintain student records, but there is no way for staff or admins to browse, search, filter, or export the full student population. Module 11 provides a tabular student data grid — a paginated, filterable, sortable list of all students — accessible to staff (own department) and admins (all or own department), with one-click Excel export of the filtered result set.

Objectives:

- Give **Department Staff** and **Department Admin** a paginated grid of all students in their department, with key columns visible at a glance.
- Give **Institution Admin** a cross-department grid with a department filter.
- Support **text search** (by name, enrolment number, mobile) and **faceted filters** (department, academic year, form status, enrolment approval status, programme level).
- Support **column-level sorting** (ascending / descending) on key columns.
- Provide a **one-click Excel (.xlsx) export** of the currently filtered/searched set, including all student profile fields and M10 custom field values as additional columns.
- Allow staff to click through from the grid to the student's read-only form view (M5 staffView).

---

## 2. In scope

### 2.1 Student data grid

- Paginated table of students, 25 per page by default (configurable via a per-page selector: 25 / 50 / 100).
- Default sort: enrolment number ascending (nulls last); secondary sort: student name ascending.
- **Columns displayed** (always visible):
  - Enrolment Number (or serial if not yet approved)
  - Full Name (first_name + last_name)
  - Programme Level (UG/PG)
  - Department Name
  - Academic Year
  - Form Status (badge: Incomplete / Complete / Submitted / Approved)
  - Enrolment Approval Status (badge: Pending / Approved)
  - Actions: View (links to `/student/form/{id}/view`)
- **Filters (sidebar or filter bar above grid)**:
  - Search box (matches against name, enrolment number, mobile — LIKE query)
  - Department dropdown (institution_admin only; dept roles see own dept only)
  - Academic Year dropdown (from option_lists)
  - Programme Level dropdown (UG / PG)
  - Form Status multi-select (Incomplete / Complete / Submitted / Approved)
  - Enrolment Approval Status dropdown (All / Pending / Approved / Not Generated)
- Filter state persisted in URL query string (GET parameters) so links can be shared and the browser back button works.
- Column headers for Name, Enrolment Number, Programme Level, and Form Status are clickable for ascending/descending sort.

### 2.2 Access control

| Role | Students visible |
|------|-----------------|
| Staff | Own department only |
| Department Admin | Own department only |
| Institution Admin | All departments; department filter available |
| Student | No access (student role has no grid) |

### 2.3 Student count summary

- Above the grid: a summary line — "Showing X–Y of Z students" — updates dynamically with filters.
- Small stat chips above the grid: Total | Submitted | Approved (counts for the current filter scope).

### 2.4 Excel export

- "Export to Excel" button above the grid; exports **all rows matching the current filters** (ignores pagination — full result set).
- Output: one worksheet named "Students"; one row per student.
- **Column set for export:**
  - All columns from the grid view
  - All built-in student_profiles fields (all ~95, including document path columns — just path strings, not the files)
  - M10 custom fields (active custom fields for the department or institution-scoped, as additional columns at the right; column header = custom field label; value from student_custom_data)
  - For institution_admin exports spanning multiple departments: include all active institution-scoped custom fields plus all active department-scoped custom fields (across all departments in the result, as separate columns; blank where not applicable)
- Export generated server-side with **PhpSpreadsheet** (already in stack).
- File named: `students_export_<YYYY-MM-DD>.xlsx`
- Audit log entry on every export (actor, filter params, row count).

### 2.5 Link to student detail view

- Each row has a "View" action link to `/student/form/{studentId}/view` (M5 staffView).
- Institution Admin can view any student; Dept Admin / Staff limited to their own department (403 otherwise — already enforced in M5 staffView).

---

## 3. Out of scope (this module)

- Inline editing of student records from the grid — editing is handled by RTC (M6).
- Bulk actions from the grid (bulk approve, bulk promote) — promotion is M12.
- Saved/named filter presets.
- PDF export — Excel only in v1.
- Column chooser (showing/hiding individual columns) — fixed column set in v1.
- Student deletion — not a supported operation.
- Custom field values in the on-screen grid columns — they appear in the export only.

---

## 4. Roles involved

| Role | Grid access | Export | Department scope |
|------|-------------|--------|-----------------|
| Student | None | None | N/A |
| Staff | ✓ | ✓ | Own department |
| Department Admin | ✓ | ✓ | Own department |
| Institution Admin | ✓ | ✓ | All (filter by dept) |

---

## 5. Assumptions & dependencies

- M5 `student_profiles` table and M4 `students.enrolment_number` columns exist.
- M10 `custom_fields` and `student_custom_data` tables exist; `FieldConfig::resolveCustomFields()` is available.
- PhpSpreadsheet is installed (already used in M3 for upload error report).
- Academic year labels come from the `option_lists` / `option_values` tables (M2).
- Pagination is done with SQL `LIMIT` / `OFFSET`; total count with a `COUNT(*)` query sharing the same WHERE clause.
- No full-text index — LIKE search on name, enrolment_number, and mobile is acceptable for the expected dataset size (hundreds to low thousands per department).
- The export does not include document file attachments — only path strings for document columns.
- Department names and academic year labels are resolved via JOIN, not stored denormalised.

---

## 6. Epics & user stories

### Epic A — Grid view

**A1. Staff browses their department's students**
As a department staff member, I want to see a paginated list of all students in my department so that I can quickly find and open any student's record.

Acceptance criteria:
- Given I visit `/students`, then I see a table of students in my department, 25 per page, sorted by enrolment number.
- Given there are more than 25 students, then pagination controls appear and allow navigation.
- Given I click a student's "View" link, then I am taken to their read-only form view.
- Given I am a staff member, then I cannot see students from other departments.

**A2. Institution Admin browses all students with a department filter**
As an institution admin, I want to see students across all departments and filter by department so that I can monitor the entire institution.

Acceptance criteria:
- Given I visit `/students`, then I see students from all departments.
- Given I select a department from the department filter dropdown, then only students from that department are shown.
- Given I clear the department filter, then all students are shown again.

**A3. Staff uses search and filters to find a student**
As a department staff member, I want to search by name or enrolment number and filter by form status so that I can quickly locate specific students.

Acceptance criteria:
- Given I type a partial name or enrolment number in the search box, then only matching students are shown.
- Given I select "Submitted" from the form status filter, then only students whose form_status is 'submitted' are shown.
- Given I apply multiple filters simultaneously, then the grid shows the intersection of all filters.
- Given I apply filters, then the URL updates with query parameters so I can bookmark or share the filtered view.
- Given I reload the page with a filter URL, then the same filters are applied and the filter controls reflect the applied state.

**A4. Staff sorts the grid**
As a staff member, I want to click column headers to sort the grid so that I can arrange students by name or form completion status.

Acceptance criteria:
- Given I click the "Name" column header, then students are sorted alphabetically ascending; clicking again sorts descending.
- Given I click "Form Status", then students are sorted by status; the sort direction indicator updates.

### Epic B — Export

**B1. Staff exports filtered students to Excel**
As a department staff member, I want to export all students matching my current search/filters to an Excel file so that I can work with the data offline.

Acceptance criteria:
- Given I have applied filters, when I click "Export to Excel", then a .xlsx file downloads containing all rows matching those filters (not just the current page).
- Given the export includes custom fields, then each active custom field appears as a column header with the student's value in each row.
- Given a student has no value for a custom field, then the cell is blank.
- Given the export contains 500 students, then it downloads without timeout.
- Given I export, then an audit log entry is written with my user ID, filter params, and row count.

**B2. Institution Admin exports cross-department data**
As an institution admin, I want to export students across all departments (or a filtered subset) with all custom field columns so that I can produce institution-wide reports.

Acceptance criteria:
- Given I export with no department filter, then all students from all departments are included, with all institution-scoped and department-scoped custom field columns.
- Given a department-scoped custom field does not apply to a student's department, then that column is blank for that student.

---

## 7. Non-functional requirements (module-relevant)

- **Performance** — The grid query (with JOIN to student_profiles + departments + academic year) must return in < 2 s for up to 5,000 students per department. The export query may take longer but must not exceed PHP's max_execution_time (set to 120 s in config).
- **Pagination** — COUNT(*) and data queries share the same WHERE clause builder to guarantee consistency.
- **Export size** — Tested up to 2,000 rows × ~100 columns (PhpSpreadsheet handles this in-memory; output streamed directly to browser).
- **Audit** — Every export logged via `MasterAuditLogger` with actor, timestamp, filter snapshot, and row count.
- **CSRF** — Export is a GET request (filters in query string); no CSRF needed. All future POST actions CSRF-protected.
- **Dept scoping** — WHERE clause always includes `students.department_id = ?` for non-institution-admin roles; injected at the query builder level, not at the view level.

---

## 8. Open questions

| # | Question | Owner | Resolution needed by |
|---|----------|-------|---------------------|
| 1 | Should the grid show **all** students (including those in `pending_enrolment` onboarding status) or only students who have a generated enrolment serial? | Product | Before Design |
| 2 | Should the "Actions" column include a direct **Download Submitted Form** (PDF) link, or is that deferred to a future module? | Product | Before Design |
| 3 | For the export, should document path columns (e.g. `passport_photo_path`) be included, or filtered out as they are storage paths not useful in Excel? | Product | Before Design |
| 4 | Should export column order follow the same section grouping as the student form (Personal → Contact → Family → Qualification → Admission → Bank), or alphabetical? | Product | Before Design |

---

## 9. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, proceed to Design.
