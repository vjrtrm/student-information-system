# SIS — Module 8: Dashboards, Statistics & Personalisation
## Stage 2: Design (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 8 of 12 — Dashboards, Statistics & Personalisation
**Document stage:** Requirements ✅ → **Design (this document)** → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M8_Dashboards_Requirements.md`

---

## 1. Design goals

- Single controller (`DashboardController`) with one public `index()` action — role-branching at the data layer, not via separate routes.
- All aggregate queries use `COUNT` + `GROUP BY` on existing indexed columns; no subqueries or window functions (MySQL 5.7 compatible).
- Chart data serialised server-side with `json_encode` into inline `<script>` blocks; no AJAX endpoint for chart data in v1.
- Session preferences stored as plain `$_SESSION` keys; no new table, no cookie.
- Chart.js loaded from `cdn.jsdelivr.net` (already in CSP); no build tooling required.
- Post-login redirect to `/dashboard` done in `AuthController` — single change, all roles benefit.

---

## 2. Resolved design decisions (from open questions)

| # | Question | Decision |
|---|----------|----------|
| 1 | What counts toward "Pending Enrolment Approvals" on staff/dept_admin dashboard? | Students where `enrolment_approval_status = 'pending'`. Students with no enrolment number yet (`enrolment_number IS NULL` but status not `pending`) are not counted separately — they are already covered by their `onboarding_status`. |
| 2 | Does the academic year filter affect the per-department breakdown table? | Yes — the filter applies to both the KPI cards and the breakdown table. All counts, charts, and the table respond to the same filter. |
| 3 | Staff recent activity feed — all dept actions or only my own? | All department audit log entries (any actor in the same department), limited to last 10 rows ordered by `created_at DESC`. Rationale: staff need visibility of colleagues' actions, not just their own. |
| 4 | Do students see a notification snippet? | Yes — a small card showing count of notification events received by the student in the last 30 days (`notification_events WHERE student_id = ? AND created_at >= ?`). Links to no sub-page in v1 (display only). |

---

## 3. Component architecture (MVC)

### Controller

**`app/Controllers/DashboardController.php`**

| Method | Route | Roles | Description |
|--------|-------|-------|-------------|
| `index()` | GET /dashboard | all authenticated | Role-branches to a private builder method; renders `dashboard/<role>.php` |

Private builder methods (all return a data array passed to the view):

- `buildStudent(int $studentId): array`
- `buildStaff(int $deptId): array`
- `buildDeptAdmin(int $deptId): array`
- `buildInstitutionAdmin(array $prefs): array`

### Models / query helpers

No new Model classes. All queries issued directly in the controller via `Db::selectOne()` / `Db::selectAll()` using helper methods grouped in a single static class:

**`app/Helpers/DashboardQuery.php`** — all methods static, all accept `?int $deptId` and `?int $academicYearId`:

| Method | Returns | Query basis |
|--------|---------|-------------|
| `studentSummary(int $studentId): array` | onboarding_status, enrolment_number, enrolment_serial, form_status | `students JOIN student_profiles` |
| `pendingRtc(int $studentId): ?array` | change_request row or null | `change_requests WHERE student_id=? AND status='pending'` |
| `recentNotifications(int $studentId, int $days): int` | count | `notification_events WHERE student_id=? AND created_at>=?` |
| `queueCounts(int $deptId): array` | pending_approvals, pending_rtcs, pending_enrolments | three COUNT queries in one call |
| `unsentNotifications(int $deptId): int` | count | `notification_events JOIN students WHERE sent_at IS NULL AND department_id=?` |
| `deptSummary(int $deptId, ?int $ayId): array` | total, approved, pending_form, pending_enrolment | `students GROUP BY onboarding_status` filtered by dept + ay |
| `institutionKpis(?int $deptId, ?int $ayId): array` | six KPI integers | `students` + `change_requests` + `notification_events` |
| `deptBreakdown(?int $deptId, ?int $ayId): array` | one row per department | `departments LEFT JOIN students GROUP BY department_id` |
| `recentAudit(int $deptId, int $limit): array` | audit_log rows | `audit_log JOIN users WHERE students in deptId OR actor dept = deptId` |
| `enrolmentStatusChartData(?int $deptId, ?int $ayId): array` | labels[], counts[] | `students GROUP BY onboarding_status` |
| `deptComparisonChartData(?int $deptId, ?int $ayId): array` | dept names[], approved[], pending_form[], pending_enrolment[] | `departments LEFT JOIN students GROUP BY department_id` |
| `formCompletionChartData(?int $deptId, ?int $ayId): array` | labels[], counts[] | `student_profiles GROUP BY form_status` |
| `funnelChartData(int $deptId): array` | labels[], counts[] for 4 stages | single dept onboarding breakdown |

### Views

| File | Used by role(s) |
|------|----------------|
| `app/Views/dashboard/student.php` | Student |
| `app/Views/dashboard/staff.php` | Staff |
| `app/Views/dashboard/dept_admin.php` | Department Admin |
| `app/Views/dashboard/institution_admin.php` | Institution Admin |

All extend `layouts/app.php` via the standard `ob_start()` / `$content` / `require` pattern.

---

## 4. Data model (module-relevant tables/columns)

No new tables or columns. Read-only access to:

| Table | Columns used |
|-------|-------------|
| `students` | id, department_id, onboarding_status, enrolment_number, enrolment_serial, enrolment_approval_status, academic_year_id |
| `student_profiles` | student_id, form_status, student_email |
| `change_requests` | student_id, department_id, status |
| `notification_events` | student_id, recipient_type, sent_at, created_at |
| `enrolment_batches` | department_id, academic_year_id |
| `audit_log` | actor_id, action, entity, entity_id, created_at |
| `users` | id, department_id, role, status |
| `departments` | id, name, code |
| `option_lists` / `option_values` | list_key='academic_year', value, display |

Academic year filtering joins `students.academic_year_id` to the selected option_value id. When no year is selected, the `WHERE` clause omits the `academic_year_id` condition.

---

## 5. Flows

### 5.1 Dashboard page load

```
GET /dashboard
  → AuthMiddleware::handle()           (redirect to /login if not authenticated)
  → RoleMiddleware::handle(['student','staff','dept_admin','institution_admin'])
  → DashboardController::index()
      switch Auth::role():
        'student'          → buildStudent()    → render dashboard/student.php
        'staff'            → buildStaff()      → render dashboard/staff.php
        'dept_admin'       → buildDeptAdmin()  → render dashboard/dept_admin.php
        'institution_admin'→ buildInstitutionAdmin() → render dashboard/institution_admin.php
```

### 5.2 Institution Admin preference save

```
GET /dashboard?academic_year_id=3&department_id=2
  → DashboardController::index()
      read GET params → validate (int or 0/null)
      $_SESSION['pref_academic_year'] = $ayId
      $_SESSION['pref_department_id'] = $deptId
      → buildInstitutionAdmin(['ay_id'=>$ayId, 'dept_id'=>$deptId])
      → render dashboard/institution_admin.php
        (filter dropdowns pre-selected with saved prefs)
```

Preferences are read from `$_SESSION` on every load; GET params override and update them.

### 5.3 Post-login redirect

`AuthController` (M1) — change the redirect target from `/` (or hardcoded role paths) to `/dashboard` for all roles. One line change in the success branch of `login()` and `studentLogin()`.

---

## 6. RBAC & department scoping

| Role | Data scope | Chart access |
|------|-----------|-------------|
| Student | Own student record only (`student_id = Auth::userId()` — actually `Auth::studentId()`) | None |
| Staff | `department_id = Auth::departmentId()` | None |
| Department Admin | `department_id = Auth::departmentId()` | Funnel chart (own dept) |
| Institution Admin | All departments, or filtered by `pref_department_id` | All three charts |

All scoping enforced in `DashboardQuery` methods, not in the view. Institution Admin passing `$deptId = null` fetches all departments; passing a specific id filters to that department.

`Auth::studentId()` — this helper returns the student `id` for the logged-in student user (linking `users` → `students` via mobile + DOB). If this helper does not yet exist in M1's `Auth` class, `DashboardQuery::studentSummary()` will perform the lookup itself: `SELECT id FROM students WHERE mobile = ? AND dob = ?` using session values.

---

## 7. Session / security & validation

- **Preference params:** `academic_year_id` and `department_id` cast to `(int)` immediately; invalid (non-numeric or negative) values treated as `null` (no filter). Never passed raw to SQL.
- **CSRF:** no POST actions on the dashboard — filters submitted via GET. No CSRF token needed.
- **No PII in views:** student name from `students.first_name / last_name` may appear on the student's own dashboard (greeting only — "Welcome, [first_name]"). No other student's name appears on any dashboard.
- **Audit log on dashboard:** audit_log rows contain actor names and entity IDs only — no PII field values (by M2 convention). Safe to display.
- **Session keys added:** `pref_academic_year` (int|null), `pref_department_id` (int|null). Both cleared on `Auth::logout()` — no code change needed (session is destroyed).

---

## 8. Screen behaviour & messages

### Student dashboard cards

| Card | Content | Condition |
|------|---------|-----------|
| Enrolment Status | Badge + human label | Always shown |
| Enrolment Number | Number or "Serial #NNN — Pending release" | `enrolment_number` null → serial shown |
| Form Status | Badge + link "Open my form" | Always shown |
| Pending Change Request | Status badge + "View request" link | Only if pending RTC exists |
| Recent Notifications | "N notification(s) in the last 30 days" | Always shown; 0 is valid |

### Queue count cards (Staff & Dept Admin)

Each card: large number, label, link. Zero is shown, not hidden.

### Institution Admin — filter behaviour

- Dropdowns for Academic Year and Department at top of page; submit on change (JS `onchange` → form submit, or manual Apply button for no-JS).
- "All Departments" is the default option (value=0 → null in PHP).
- KPI cards, breakdown table, and all three charts re-render with each filter change.

### Chart rendering

Charts rendered by Chart.js after DOM load via inline `<script>` at bottom of view. Data embedded as:

```php
<script>
const enrolmentData = <?= json_encode($enrolmentChart, JSON_HEX_TAG) ?>;
// Chart.js initialisation...
</script>
```

`JSON_HEX_TAG` prevents XSS from user-controlled label strings (e.g., a department name containing `</script>`).

Fallback for no-JS: a `<noscript>` block renders a plain `<table>` with the same data.

### Flash messages

No write actions on the dashboard — no flash messages in this module.

---

## 9. Configuration parameters

No new config keys. Chart.js CDN URL is hardcoded in the view (`https://cdn.jsdelivr.net/npm/chart.js`) — already covered by the existing CSP.

---

## 10. Edge cases

| Scenario | Handling |
|----------|----------|
| Student has no `student_profiles` row yet | `studentSummary()` returns null for form_status fields; view shows "Form not started" |
| Institution Admin selects a dept_id that doesn't exist | Cast to int; query returns empty results gracefully; KPI cards show 0 |
| Academic year filter selected but no students in that year | All counts show 0; charts render with empty datasets (Chart.js renders empty axes, no crash) |
| All notification events are sent | Unsent Notifications KPI = 0; Dept Admin card shows "0 pending" |
| Dept Admin has no students | All queue cards show 0; funnel chart renders with all-zero bars |
| Audit log is empty for a department | Recent activity feed shows "No recent activity." |
| `option_list` for academic_year is empty | Academic year dropdown shows only "All Years" option; no filter applied |
| Student has multiple pending RTCs | Not possible — M6 enforces one pending RTC per student (`hasPending()` check). Design can assume at most one. |
| Chart.js CDN unavailable | `<canvas>` element fails silently; `<noscript>` fallback table always present via `<noscript>` tag |

---

## 11. Traceability (requirement → design)

| Requirement | Design element |
|-------------|---------------|
| A1 — Student dashboard | `buildStudent()` + `dashboard/student.php` + `DashboardQuery::studentSummary/pendingRtc/recentNotifications` |
| A2 — Staff queue cards | `buildStaff()` + `dashboard/staff.php` + `DashboardQuery::queueCounts/recentAudit` |
| A3 — Dept Admin + notification card | `buildDeptAdmin()` + `dashboard/dept_admin.php` + `DashboardQuery::unsentNotifications/deptSummary/funnelChartData` |
| A4 — Institution Admin KPIs + charts | `buildInstitutionAdmin()` + `dashboard/institution_admin.php` + `DashboardQuery::institutionKpis/deptBreakdown/enrolmentStatusChartData/deptComparisonChartData/formCompletionChartData` |
| A5 — Dept Admin funnel chart | `DashboardQuery::funnelChartData()` + Chart.js canvas in `dashboard/dept_admin.php` |
| B1/B2 — Session preferences | `$_SESSION['pref_academic_year']` / `pref_department_id` read/written in `buildInstitutionAdmin()` |
| C1 — Post-login redirect | `AuthController::login()` + `studentLogin()` redirect target changed to `/dashboard` |
| NFR — no N+1 queries | `deptBreakdown()` fetches all departments in one `GROUP BY` query |
| NFR — XSS safe chart data | `json_encode(..., JSON_HEX_TAG)` on all chart label/data arrays |
| NFR — graceful degradation | `<noscript>` fallback table in all chart views |
| NFR — accessibility | `aria-label` on `<canvas>`; hidden `<table>` fallback |

---

## 12. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, proceed to Tasks.
