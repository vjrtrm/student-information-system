# SIS — Module 8: Dashboards, Statistics & Personalisation
## Stage 1: Requirements (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 8 of 12 — Dashboards, Statistics & Personalisation
**Document stage:** **Requirements (this document)** → _Design_ → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Builds on:** Authentication (M1), Master Data (M2), Student Onboarding (M3), Enrolment Numbers (M4), Student Information Form (M5), Submission & Edit Approval (M6), Notifications (M7)

---

## 1. Purpose & objectives

Modules 1–7 deliver the full student data lifecycle but land every authenticated user on a generic landing page with no personalised context. Module 8 gives each role a tailored **home dashboard** that surfaces the information most relevant to their day-to-day work, and gives Institution Admins a **statistics overview** with cross-department insight.

Objectives:

- Replace the current post-login landing page with a role-aware dashboard for all five roles (Student, Staff, Department Admin, Institution Admin, System Admin).
- Surface actionable queue counts and status summaries without requiring navigation to sub-pages.
- Give Institution Admin a statistics page with KPI cards and department-level breakdowns.
- Allow each user to set a display preference (academic year filter, department filter for institution_admin) that persists for their session.
- Keep all dashboard data scoped to the user's department; institution_admin sees aggregate + per-department data.

---

## 2. In scope

### 2.1 Student dashboard

- **Enrolment status card** — shows current `onboarding_status` with a human-readable label and colour (Pending Enrolment / Enrolment Generated / Approved / etc.).
- **Enrolment number** — displayed once released (`enrolment_number` not null); serial shown until then with "Pending release" label.
- **Form status card** — shows `form_status` (Incomplete / Submitted / etc.) with a link to open the form.
- **Pending RTC card** — if student has a pending change request, shows its status and a link to view it.
- **Notifications snippet** — count of recent notification events received by the student (last 30 days).
- No access to any other student's data.

### 2.2 Department Staff dashboard

- **My queue cards (three):**
  - Pending Approvals — count of `onboarding_status = form_submitted` in their department.
  - Pending RTCs — count of `change_requests.status = pending` in their department.
  - Pending Enrolment Approvals — count of students with `enrolment_approval_status = pending` in their department.
- **Recent activity feed** — last 10 audit log entries for the department (entity type: student, change_request, enrolment_batch).
- **Quick links** — Students, Approvals, Enrolment Numbers, Notifications.
- Scoped to `Auth::departmentId()`.

### 2.3 Department Admin dashboard

Same as Department Staff dashboard, plus:

- **Notification send status card** — count of `notification_events` where `sent_at IS NULL` in their department; link to /notifications.
- **Department summary row** — total students, total approved, total pending form, total pending enrolment.

### 2.4 Institution Admin dashboard

- **Statistics overview cards (KPIs):**
  - Total students across all departments.
  - Total approved students.
  - Total pending form submissions.
  - Total pending enrolment approvals.
  - Total pending RTCs.
  - Total unsent notifications.
- **Per-department breakdown table** — one row per department: Department Name, Total Students, Approved, Pending Form, Pending Enrolment, Pending RTCs.
- **Charts & visualisations (rendered inline using Chart.js via CDN):**
  - **Enrolment status breakdown** — horizontal bar chart showing student counts by `onboarding_status` across all departments (or filtered department).
  - **Department comparison bar chart** — grouped/stacked bar chart: one bar group per department showing Approved vs. Pending Form vs. Pending Enrolment counts.
  - **Form completion distribution** — pie/doughnut chart: proportion of students by `form_status` (Incomplete / Submitted / Approved).
  - Charts respect the academic year and department filters; they re-render on filter change (page reload with GET params).
- **Academic year filter** — dropdown to filter all statistics and charts to a selected academic year (from `option_lists` list_key `academic_year`). Selected year persists in session.
- **Department filter** — dropdown to drill into a single department (or "All Departments"). Persists in session.
- Quick links to global views (all students, all approvals, enrolments, notifications).

### 2.5 Department Admin dashboard — charts

- **Onboarding funnel bar chart** — single horizontal bar chart showing student counts across the four main stages: Pending Form / Submitted / Pending Enrolment / Approved, scoped to the admin's own department.
- Rendered using Chart.js via CDN; no additional back-end infrastructure required.

### 2.6 Personalisation (session preferences)

- **Academic year preference** — Institution Admin can select a default academic year; stored in `$_SESSION['pref_academic_year']`; pre-selected in all filters on the dashboard.
- **Department filter preference** — Institution Admin can select a default department view (or All); stored in `$_SESSION['pref_department_id']`.
- Preferences reset on logout (session destroyed).
- Staff/Dept Admin: no personalisation in v1 (department is fixed by their account).

### 2.7 Redirect after login

- After successful login, all roles are redirected to `/dashboard` (currently some roles land on `/` with minimal content). The existing `AuthController` redirect target updated to `/dashboard`.

---

## 3. Out of scope (this module)

- Exportable dashboard reports — covered in M11 (Student Data Grid & Export).
- Staff Management views — M9.
- Field Management — M10.
- Scheduled/automated statistics refresh or background jobs.
- Email digests or push notifications summarising dashboard data.
- Per-student analytics or engagement tracking.
- Dark mode or theme selection.

---

## 4. Roles involved

| Role | Dashboard section | Can personalise? |
|------|------------------|-----------------|
| Student | §2.1 | No |
| Staff | §2.2 | No |
| Department Admin | §2.3 | No |
| Institution Admin | §2.4 | Yes (academic year + dept filter) |

---

## 5. Assumptions & dependencies

- All M1–M7 tables and columns exist; specifically `students`, `student_profiles`, `change_requests`, `notification_events`, `enrolment_batches`, `audit_log`.
- `option_lists` contains `academic_year` entries (seeded in M2).
- `Auth::role()`, `Auth::departmentId()`, `Auth::userId()` helpers are available (M1).
- `DepartmentScopeMiddleware` enforces department isolation (M1).
- Aggregate SQL queries avoid MySQL-8-only window functions; use `COUNT` + `GROUP BY` only.
- No new database tables are required for this module (all data read from existing tables).
- Session (`$_SESSION`) is started by `Auth::start()` in the front controller; module may add preference keys to the existing session without altering session handling.

---

## 6. Epics & user stories

### Epic A — Role-aware home dashboard

**A1. Student sees their own status at a glance**
As a student, I want to open `/dashboard` after login and immediately see my enrolment status, form status, and any pending change request, so that I don't have to navigate to multiple pages to understand where I stand.

Acceptance criteria:
- Given I am logged in as a student, when I visit `/dashboard`, then I see: my enrolment status label, my enrolment number (or serial + "Pending release"), my form status with a link to the form, and a card showing any open RTC.
- Given I have no pending RTC, then the RTC card is hidden or shows "No pending change requests."
- Given another student's data, when I visit `/dashboard`, then I see only my own data.

**A2. Department Staff sees their work queue**
As department staff, I want to see pending approval counts on my dashboard so that I can prioritise my review workload without opening each queue separately.

Acceptance criteria:
- Given I am logged in as staff, when I visit `/dashboard`, then I see three queue count cards: Pending Approvals, Pending RTCs, Pending Enrolment Approvals — all scoped to my department.
- Given counts are zero, then the card shows "0" (not hidden).
- Given I click a queue count card, then I am taken to the relevant list page.
- Given I am staff in Department A, then I see no data from Department B.

**A3. Department Admin sees staff view plus notification status**
As a department admin, I want all the staff dashboard features plus a notification send status card so that I can see how many emails are queued.

Acceptance criteria:
- Given I am logged in as dept_admin, when I visit `/dashboard`, then I see all three queue cards plus a Notification Status card showing unsent count with a link to /notifications.
- Given all notifications are sent, then the card shows "0 pending."
- Given the department summary row, then Total, Approved, Pending Form, and Pending Enrolment counts are accurate.

**A4. Institution Admin sees cross-department KPI overview with charts**
As an institution admin, I want a statistics overview page with KPI cards, charts, and a per-department breakdown table so that I can monitor the institution's enrolment health in one place.

Acceptance criteria:
- Given I am logged in as institution_admin, when I visit `/dashboard`, then I see six KPI cards (Total Students, Approved, Pending Form, Pending Enrolment, Pending RTCs, Unsent Notifications) and a per-department breakdown table.
- Given I visit `/dashboard`, then I also see three charts: an enrolment status bar chart, a department comparison bar chart, and a form completion doughnut chart.
- Given the academic year filter is set to "2024–25", then all counts and charts reflect only students in that academic year.
- Given no academic year filter is applied, then counts and charts include all years.
- Given I select a department from the department filter, then the KPI cards, table, and charts filter to that department.
- Given JavaScript is unavailable, then the KPI cards and table still render correctly (charts degrade gracefully to a "Charts unavailable" message).

**A5. Department Admin sees onboarding funnel chart**
As a department admin, I want a bar chart showing my department's student pipeline stages so that I can see where students are getting stuck without reading through a table.

Acceptance criteria:
- Given I am logged in as dept_admin, when I visit `/dashboard`, then I see a horizontal bar chart with four bars: Pending Form, Submitted, Pending Enrolment, Approved — all scoped to my department.
- Given all students are approved, then all other bars show zero.

### Epic B — Personalisation

**B1. Institution Admin persists academic year preference**
As an institution admin, I want my selected academic year to be remembered during my session so that I don't have to re-select it every time I navigate away and return to the dashboard.

Acceptance criteria:
- Given I select academic year "2025–26" on the dashboard, when I navigate to another page and return, then "2025–26" is still selected.
- Given I log out and log back in, then the preference is cleared (no persistence across sessions).

**B2. Institution Admin persists department filter**
As an institution admin, I want my selected department filter to be remembered during my session so that I can work within a single department without resetting the filter each visit.

Acceptance criteria:
- Given I select "Department of Computer Science" from the department filter, when I return to `/dashboard`, then the filter is still set to that department.
- Given I select "All Departments", then all data is shown unfiltered.

### Epic C — Correct post-login routing

**C1. All roles land on /dashboard after login**
As any authenticated user, I want to be taken to my personalised dashboard immediately after login so that I see relevant information right away.

Acceptance criteria:
- Given I log in as a student, when authentication succeeds, then I am redirected to `/dashboard`.
- Given I log in as staff, dept_admin, or institution_admin, when authentication succeeds, then I am redirected to `/dashboard`.
- Given I am already logged in and visit `/`, then I am redirected to `/dashboard`.

---

## 7. Non-functional requirements (module-relevant)

- **Performance** — dashboard page must load within 2 seconds; aggregate queries should use indexed columns (`department_id`, `onboarding_status`, `status`). No N+1 queries: fetch all department counts in a single `GROUP BY` query.
- **Security** — all dashboard data is scoped by role and department at the query level, not the view level. No raw SQL values exposed in HTML.
- **No new tables** — all data read from existing tables. No schema migrations in this module.
- **Chart library** — Chart.js loaded from the Bootstrap CDN (`cdn.jsdelivr.net`), consistent with the existing CSP (`script-src 'self' https://cdn.jsdelivr.net`). No npm build step required; inline `<script>` blocks in views only. Chart data is injected as JSON into the page via `json_encode` (server-side); no separate AJAX endpoint for chart data in v1.
- **Graceful degradation** — if Chart.js fails to load, the KPI cards and breakdown table still render. Charts area shows a static fallback message.
- **Accessibility** — status badges use both colour and text label (not colour alone). Charts include `aria-label` on `<canvas>` elements and a visually hidden data table as fallback for screen readers.

---

## 8. Open questions

| # | Question | Owner | Resolution needed by |
|---|----------|-------|---------------------|
| 1 | Should "Pending Enrolment Approvals" on the staff/dept_admin dashboard count students with `enrolment_approval_status = 'pending'` only, or also those with no enrolment number yet (`enrolment_number IS NULL`)? | Product | Before Design |
| 2 | Should the Institution Admin academic year filter also affect the per-department breakdown table, or only the KPI cards? | Product | Before Design |
| 3 | Should the recent activity feed on the Staff dashboard show all audit_log entries for the department, or only entries where `actor_id = Auth::userId()` (my own actions)? | Product | Before Design |
| 4 | Should students see a notification count/snippet, or is the notification log staff-only? The M7 `/notifications` page is staff/admin only, but students could see their own received events. | Product | Before Design |

---

## 9. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, proceed to Design.
