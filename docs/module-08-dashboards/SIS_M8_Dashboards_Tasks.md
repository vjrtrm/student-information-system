# SIS — Module 8: Dashboards, Statistics & Personalisation
## Stage 3: Tasks (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 8 of 12 — Dashboards, Statistics & Personalisation
**Document stage:** Requirements ✅ → Design ✅ → **Tasks (this document)**
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M8_Dashboards_Design.md`

---

## 1. How to read this

Each task: ID, deliverable, estimate (ideal hours), dependencies, priority, "done when". P1 = required for the module to function; P2 = hardening/polish. Estimates assume M1–M7 codebase in place. Build order in §9.

No database migrations in this module — all queries are read-only against existing tables.

---

## 2. Query helper

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M8-T01 | `DashboardQuery` (`app/Helpers/DashboardQuery.php`) — static class with all aggregate query methods: `studentSummary(int $studentId): array`, `pendingRtc(int $studentId): ?array`, `recentNotifications(int $studentId, int $days): int`, `queueCounts(int $deptId): array` (returns pending_approvals, pending_rtcs, pending_enrolments in one call), `unsentNotifications(int $deptId): int`, `deptSummary(int $deptId, ?int $ayId): array` (total/approved/pending_form/pending_enrolment), `institutionKpis(?int $deptId, ?int $ayId): array` (six KPI integers), `deptBreakdown(?int $deptId, ?int $ayId): array` (one row per department), `recentAudit(int $deptId, int $limit): array`, `enrolmentStatusChartData(?int $deptId, ?int $ayId): array` (labels[], counts[]), `deptComparisonChartData(?int $deptId, ?int $ayId): array` (dept names[], approved[], pending_form[], pending_enrolment[]), `formCompletionChartData(?int $deptId, ?int $ayId): array` (labels[], counts[]), `funnelChartData(int $deptId): array` (labels[], counts[] for 4 stages). All methods use `Db::selectOne()` / `Db::selectAll()` with prepared statements; no raw interpolation. Academic year filter adds `AND s.academic_year_id = ?` when `$ayId` is non-null. | 6 | — | P1 | All methods return correct data types; unit tested against SQLite |

---

## 3. Controller

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M8-T02 | `DashboardController` (`app/Controllers/DashboardController.php`) — single public `index()` action on `GET /dashboard`; calls `RoleMiddleware::handle(['student','staff','dept_admin','institution_admin'])`; branches on `Auth::role()` to four private builder methods: `buildStudent()`, `buildStaff()`, `buildDeptAdmin()`, `buildInstitutionAdmin()`; each builder calls relevant `DashboardQuery` methods and returns a data array; `buildInstitutionAdmin()` reads/writes `$_SESSION['pref_academic_year']` and `$_SESSION['pref_department_id']` from GET params (cast to int, 0 → null); renders the appropriate `dashboard/<role>.php` view | 5 | M8-T01 | P1 | Each role sees correct data; institution_admin prefs persist across page loads within the session; other roles never see cross-department data |
| M8-T03 | Route + use statement — add `['GET', '/dashboard', [DashboardController::class, 'index'], ['auth']]` to route table in `public/index.php`; add `use App\Controllers\DashboardController;`; remove or redirect the existing `/` placeholder route to `/dashboard` for authenticated users | 1 | M8-T02 | P1 | GET /dashboard resolves for all roles; unauthenticated request redirects to /login |
| M8-T04 | Post-login redirect — in `AuthController::login()` (staff/admin flow) and `AuthController::studentLogin()` (student flow), change the success redirect target from its current value to `/dashboard` | 1 | M8-T03 | P1 | After successful login, all roles land on /dashboard |

---

## 4. Views

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M8-T05 | `dashboard/student.php` — Bootstrap 5 view; cards: Enrolment Status (badge + label), Enrolment Number (number or "Serial #NNN — Pending release"), Form Status (badge + "Open my form" link to /student/form), Pending Change Request (shown only if pending RTC: status badge + "View request" link to /rtc/history), Recent Notifications count ("N notification(s) in the last 30 days"). Uses `ob_start()` / `$content` / `require layout` pattern. No other student's data rendered. | 3 | M8-T02 | P1 | All cards render correctly for a student with and without an active RTC; enrolment number/serial logic correct |
| M8-T06 | `dashboard/staff.php` — Bootstrap 5 view; three queue count cards (Pending Approvals → /approvals, Pending RTCs → /approvals, Pending Enrolment Approvals → /enrolment), each card shows count as large number + label + link; recent activity feed (last 10 audit_log entries: action, entity, created_at); quick links row (Students, Approvals, Enrolment Numbers, Notifications). Zero counts shown, not hidden. | 3 | M8-T02 | P1 | Queue counts accurate; zero displays correctly; activity feed shows department entries newest first |
| M8-T07 | `dashboard/dept_admin.php` — extends staff view; adds: Notification Status card (unsent count + link to /notifications), Department Summary row (Total / Approved / Pending Form / Pending Enrolment as stat pills), and Chart.js horizontal bar chart (onboarding funnel — 4 stages). Chart data embedded as `json_encode($funnelData, JSON_HEX_TAG)` in inline `<script>`. `<canvas aria-label="Onboarding funnel">` with `<noscript>` fallback table. Chart.js loaded from `https://cdn.jsdelivr.net/npm/chart.js`. | 4 | M8-T02 | P1 | All staff cards present; dept summary accurate; funnel chart renders with correct 4-bar data; noscript fallback renders a plain table |
| M8-T08 | `dashboard/institution_admin.php` — Bootstrap 5 view; top section: Academic Year and Department filter dropdowns (GET form, `onchange` JS auto-submit + manual Apply button for no-JS); six KPI cards; per-department breakdown table (Department, Total, Approved, Pending Form, Pending Enrolment, Pending RTCs); three Chart.js charts below: (1) horizontal bar — enrolment status breakdown, (2) grouped/stacked bar — department comparison, (3) doughnut — form completion distribution. Each `<canvas>` has `aria-label`; each chart section has `<noscript>` fallback table. All chart data embedded server-side via `json_encode(..., JSON_HEX_TAG)`. Quick links row. | 7 | M8-T02 | P1 | All six KPI cards accurate; breakdown table correct; all three charts render; filter GET params pre-select dropdowns; noscript fallbacks render |
| M8-T09 | Nav update — add "Dashboard" as the first nav link in `layouts/app.php` for all authenticated roles, pointing to `/dashboard`, with active state when `$_SERVER['REQUEST_URI']` starts with `/dashboard` | 1 | M8-T03 | P2 | Dashboard link visible for all roles; active class applied correctly |

---

## 5. Tests

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M8-T10 | Unit: `DashboardQueryTest` (`tests/Unit/DashboardQueryTest.php`) — seed SQLite with dept + staff + students in various onboarding states; assert: `queueCounts()` returns correct pending_approvals/pending_rtcs/pending_enrolments; `deptSummary()` total = sum of all states; `institutionKpis()` with deptId=null sums all depts; `institutionKpis()` with deptId filters to one dept; `enrolmentStatusChartData()` returns correct labels and counts arrays; `funnelChartData()` returns 4 labels; `recentNotifications()` respects the 30-day window | 5 | M8-T01 | P1 | All assertions green |
| M8-T11 | Unit: `DashboardQueryAcademicYearTest` (`tests/Unit/DashboardQueryAcademicYearTest.php`) — seed two academic years; assert `institutionKpis()` with `$ayId` returns only that year's students; assert with `$ayId = null` returns all years | 3 | M8-T01 | P1 | Green |
| M8-T12 | Integration: `DashboardStudentTest` (`tests/Integration/DashboardStudentTest.php`) — seed full student + profile; assert `buildStudent()` data array contains correct onboarding_status, form_status, null pending RTC; assert pending RTC appears after seeding a pending change_request | 3 | M8-T02 | P1 | Green |
| M8-T13 | Integration: `DashboardStaffTest` (`tests/Integration/DashboardStaffTest.php`) — seed dept + two students (one form_submitted, one pending_enrolment) + one pending change_request; assert `buildStaff()` returns pending_approvals=1, pending_rtcs=1, pending_enrolments=1; assert staff in dept B sees 0 for dept A's data | 3 | M8-T02 | P1 | Green |
| M8-T14 | Integration: `DashboardInstitutionAdminTest` (`tests/Integration/DashboardInstitutionAdminTest.php`) — seed two depts with students; assert `buildInstitutionAdmin()` KPI totals sum both depts when deptId=null; assert filtering to deptId=deptA returns only deptA counts; assert deptBreakdown returns one row per dept | 4 | M8-T02 | P1 | Green |
| M8-T15 | Update `tests/bootstrap.php` — verify all tables used by DashboardQuery (`students`, `student_profiles`, `change_requests`, `notification_events`, `audit_log`, `departments`, `users`, `option_lists`, `option_values`) are present in `sis_test_schema()`. No new DDL expected (all added by M1–M7); add any missing columns if the test runner reveals gaps | 1 | — | P1 | PHPUnit boots without schema errors for M8 tests |

---

## 6. Auth controller update

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M8-T16 | Edit `app/Controllers/AuthController.php` — change the post-login redirect in `login()` (staff/admin) and `studentLogin()` (student) to `header('Location: /dashboard')`. Verify no other redirect paths in the auth flow override this (OTP confirmation step, password reset completion). | 1 | M8-T03 | P1 | After any successful login, browser lands on /dashboard regardless of role |

---

## 7. Build order (critical path)

1. **Query helper:** M8-T01 (DashboardQuery — no deps, write and unit test first)
2. **Controller:** M8-T02 → M8-T03 → M8-T04 (depends on T01; route and redirect follow)
3. **Auth redirect:** M8-T16 (depends on route existing — T03)
4. **Views:** M8-T05, M8-T06 in parallel → M8-T07, M8-T08 (dept_admin and inst_admin build on staff pattern) → M8-T09 (nav, last)
5. **Tests:** M8-T15 (bootstrap check first) → M8-T10, M8-T11 (unit, alongside T01) → M8-T12, M8-T13, M8-T14 (integration, after T02)

---

## 8. Estimate summary

| Group | Hours |
|-------|------:|
| Query helper (T01) | 6 |
| Controller & routes (T02–T04) | 7 |
| Auth redirect (T16) | 1 |
| Views (T05–T09) | 18 |
| Tests (T10–T15) | 19 |
| **Total** | **~51 ideal hours (~6–7 dev-days)** |

---

## 9. Definition of Done

- All P1 tasks complete; all unit + integration tests green (`./vendor/bin/phpunit`) on PHP 8 / MySQL 5.7.
- All five roles (student, staff, dept_admin, institution_admin) land on `/dashboard` after login.
- Student dashboard shows only the logged-in student's own data.
- Staff and Dept Admin queue counts are scoped to their department; cross-department data is never returned.
- Institution Admin KPI cards, breakdown table, and all three charts respond correctly to academic year and department filters.
- Dept Admin funnel chart and Institution Admin charts render via Chart.js; `<noscript>` fallback tables render when JS is disabled.
- Chart data embedded with `JSON_HEX_TAG` (XSS-safe).
- Session preferences (`pref_academic_year`, `pref_department_id`) persist within the session and clear on logout.
- No new database tables or migrations.
- Commit via `scripts/commit-module.sh "M8 Dashboards, Statistics & Personalisation: implementation complete"`; user pushes from Mac.

---

## 10. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, implement in Claude Code.
