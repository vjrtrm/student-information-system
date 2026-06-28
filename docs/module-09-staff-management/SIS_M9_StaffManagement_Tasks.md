# SIS — Module 9: Staff Management
## Stage 3: Tasks (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 9 of 12 — Staff Management
**Document stage:** Requirements ✅ → Design ✅ → **Tasks (this document)**
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M9_StaffManagement_Design.md`

---

## 1. How to read this

Each task: ID, deliverable, estimate (ideal hours), dependencies, priority, "done when". P1 = required for the module to function; P2 = hardening/polish. Estimates assume M1–M8 codebase in place. Build order in §8.

---

## 2. Data layer

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M9-T01 | Migration `024_alter_users_must_change_password.sql` — `ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0;` | 1 | — | P1 | Column exists in MySQL 5.7; existing rows default to 0; migration file committed |

---

## 3. Model

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M9-T02 | `StaffUser` (`app/Models/StaffUser.php`) — static methods: `findById(int $id): ?array`; `findByDept(int $deptId): array` — all users where role IN ('staff','dept_admin') and department_id=?; `findAll(?int $deptId): array` — all staff, optional dept filter; `create(array $data): int` — INSERT whitelisted columns, return lastInsertId; `update(int $id, array $data): void` — UPDATE whitelisted columns only (name, email, password_hash, role, department_id, staff_code, status, must_change_password); `hasDeptAdmin(int $deptId, ?int $excludeId = null): bool` — SELECT COUNT where role='dept_admin' and department_id=? [and id != excludeId]; `pendingItemsCount(int $userId, int $deptId): int` — COUNT pending change_requests where department_id=? (proxy warning count; staff is not assigned to items, queue is dept-wide). All queries use `Db::selectOne()` / `Db::selectAll()` / `Db::execute()` with prepared statements. | 3 | M9-T01 | P1 | All methods return correct types; tested against SQLite |

---

## 4. Middleware update

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M9-T03 | Edit `AuthMiddleware::handle()` — after session validation and `Auth::user()` load, add: if `Auth::user()['must_change_password'] === 1` AND current URI is not `/staff/change-password` AND not `/logout`, then `header('Location: /staff/change-password'); exit;`. Applies to all roles. | 1 | M9-T01 | P1 | Staff with must_change_password=1 cannot reach any page except /staff/change-password and /logout; verified in integration test |

---

## 5. Controllers & routes

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M9-T04 | `StaffController` (`app/Controllers/StaffController.php`) — extends `Controller`; all actions call `RoleMiddleware::handle(['dept_admin','institution_admin'])` except where noted: `index()` GET /staff — loads staff via `StaffUser::findByDept` or `findAll`; passes dept list for inst_admin filter dropdown; `createForm()` GET /staff/create; `store()` POST /staff/create — CSRF; validate name/email/password/staff_code; email uniqueness check; hasDeptAdmin check when role=dept_admin; `password_hash()`; `StaffUser::create()`; audit log; flash; redirect; `editForm(int $id)` GET /staff/{id}/edit — load user; dept-scope guard; `update(int $id)` POST /staff/{id}/edit — CSRF; validate; hasDeptAdmin check on role change; dept-scope guard; self-action block; `StaffUser::update()`; audit; `toggleStatus(int $id)` POST /staff/{id}/toggle-status — CSRF; dept-scope guard; self-action block (cannot deactivate self); `pendingItemsCount` for warning; toggle status; audit; flash with warning if items pending; `resetPasswordForm(int $id)` GET /staff/{id}/reset-password; `resetPassword(int $id)` POST /staff/{id}/reset-password — CSRF; self-action block; validate password 8+; `password_hash()`; `StaffUser::update(['password_hash','must_change_password'=>1])`; audit | 7 | M9-T02, M9-T03 | P1 | All actions enforce role + dept scope; all POST actions CSRF-protected; self-action blocks return 403 + flash; audit log entry on every write |
| M9-T05 | `StaffSelfController` (`app/Controllers/StaffSelfController.php`) — extends `Controller`; all actions call `RoleMiddleware::handle(['staff','dept_admin','institution_admin'])`: `profileForm()` GET /staff/profile — load own user row; `profileUpdate()` POST /staff/profile — CSRF; validate name (required) + staff_code (optional); `Db::execute()` UPDATE users SET name=?,staff_code=? WHERE id=?; audit; flash; `changePasswordForm()` GET /staff/change-password — no role guard (needed pre-dashboard when must_change_password=1, but user is authenticated); `changePassword()` POST /staff/change-password — CSRF; if must_change_password=0: verify current_password via `password_verify()`; validate new_password 8+ chars, matches confirm; `password_hash()`; UPDATE users SET password_hash=?,must_change_password=0; audit; redirect /dashboard | 4 | M9-T03 | P1 | Self-password-change verifies current pw when flag=0; skips when flag=1; must_change_password cleared to 0 on success; all field edits restricted to own record |
| M9-T06 | Routes — add `use App\Controllers\StaffController; use App\Controllers\StaffSelfController;` to `public/index.php`; register 10 routes (static paths before `{id}` wildcard): GET /staff, GET /staff/create, POST /staff/create, GET /staff/profile, POST /staff/profile, GET /staff/change-password, POST /staff/change-password, GET /staff/{id}/edit, POST /staff/{id}/edit, POST /staff/{id}/toggle-status, GET /staff/{id}/reset-password, POST /staff/{id}/reset-password | 1 | M9-T04, M9-T05 | P1 | All 12 routes resolve; unauthenticated requests redirect to /login; wrong-role requests return 403 |

---

## 6. Views

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M9-T07 | `staff/index.php` — Bootstrap 5; department filter dropdown for institution_admin (GET param); client-side search `<input>` filtering table rows by name/email on keyup (inline JS); table: Name, Email, Role badge (Staff/Dept Admin), Status badge (Active green / Inactive secondary), Staff Code, Date Added, Actions (Edit / Reset Password / Deactivate or Reactivate toggle); own row shows "You" chip in Name column with no action buttons; empty state message when no staff found | 4 | M9-T04 | P1 | Table renders for dept_admin (own dept only) and inst_admin (all + filter); own row has no action buttons; search filters rows client-side |
| M9-T08 | `staff/form.php` — shared create + edit view; PHP `$mode` ('create'/'edit') + `$editable` array control which fields render as input vs read-only text; fields: Name (always editable), Email (editable on create + inst_admin edit; read-only for dept_admin edit), Password + Confirm (create + reset-password only; absent on edit), Staff Code (always editable), Role dropdown (inst_admin only; options: Staff, Dept Admin), Department dropdown (inst_admin only), Status toggle (edit only; absent on create); validation error display; cancel link back to /staff | 4 | M9-T04 | P1 | Dept_admin create: email + name + password + staff_code only; dept_admin edit: name + staff_code + status only; inst_admin: all fields; no role=institution_admin option in dropdown |
| M9-T09 | `staff/reset_password.php` — simple Bootstrap form: new password + confirm password fields; staff member name displayed as context (read-only); submit and cancel buttons; validation error display | 2 | M9-T04 | P1 | Renders with correct staff name; password fields present; cancel returns to /staff |
| M9-T10 | `staff/profile.php` — self-service form: Name (editable), Email (read-only display), Staff Code (editable), Role (read-only display), Department (read-only display); submit and cancel; "Change Password" link to /staff/change-password | 2 | M9-T05 | P1 | Email, role, department are displayed only; name + staff_code editable; change-password link present |
| M9-T11 | `staff/change_password.php` — password change form; if `$mustChange` (view variable): banner "You must set a new password before continuing"; fields: Current Password (shown only when must_change_password=0), New Password, Confirm New Password; submit button; validation error display | 2 | M9-T05 | P1 | Banner shown when must_change=1; current-password field absent when must_change=1; present when must_change=0 |
| M9-T12 | Nav update — add "Staff" link in `layouts/app.php` for `dept_admin` and `institution_admin` roles pointing to `/staff`; active state when URI starts with `/staff` (excluding `/staff/profile` and `/staff/change-password` which are self-service) | 1 | M9-T06 | P2 | Link visible for correct roles; not shown for student or staff role |

---

## 7. Tests

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M9-T13 | Unit: `StaffUserTest` (`tests/Unit/StaffUserTest.php`) — seed dept + users; assert: `findByDept()` returns only staff/dept_admin for that dept; `hasDeptAdmin()` returns false when no dept_admin, true when one exists, false when excluding the only dept_admin's own id; `pendingItemsCount()` returns 0 when no pending change_requests, correct count when pending exist | 3 | M9-T02 | P1 | Green |
| M9-T14 | Integration: `StaffCreateTest` (`tests/Integration/StaffCreateTest.php`) — create staff via `StaffController::store()`; assert user row inserted with must_change_password=1, status=active, bcrypt hash (not plaintext); assert duplicate email returns validation error; assert dept_admin cannot set role=dept_admin | 4 | M9-T04 | P1 | Green |
| M9-T15 | Integration: `StaffEditTest` (`tests/Integration/StaffEditTest.php`) — dept_admin edits name; assert change persisted + audit log; assert dept_admin cannot change email (field ignored in POST); assert inst_admin can change role + department; assert hasDeptAdmin blocks second dept_admin creation | 3 | M9-T04 | P1 | Green |
| M9-T16 | Integration: `StaffToggleStatusTest` (`tests/Integration/StaffToggleStatusTest.php`) — deactivate staff; assert status=inactive + audit log; reactivate; assert status=active; assert self-deactivation returns 403; assert deactivation with pending items sets flash warning | 3 | M9-T04 | P1 | Green |
| M9-T17 | Integration: `StaffResetPasswordTest` (`tests/Integration/StaffResetPasswordTest.php`) — reset password; assert new hash stored (not old), must_change_password=1, audit log entry; assert self-reset returns 403 | 3 | M9-T04 | P1 | Green |
| M9-T18 | Integration: `MustChangePasswordTest` (`tests/Integration/MustChangePasswordTest.php`) — simulate request with must_change_password=1 in session; assert AuthMiddleware redirects to /staff/change-password for any other route; assert /logout not redirected; assert POST /staff/change-password clears flag and updates hash; assert current-password check skipped when flag=1 | 4 | M9-T03, M9-T05 | P1 | Green |
| M9-T19 | Integration: `StaffSelfProfileTest` (`tests/Integration/StaffSelfProfileTest.php`) — update own name/staff_code; assert change persisted; assert email unchanged after POST with email param; assert audit log entry | 2 | M9-T05 | P1 | Green |
| M9-T20 | Update `tests/bootstrap.php` — add `ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0` to `sis_test_schema()` array | 1 | — | P1 | All M9 tests run on SQLite without schema errors |

---

## 8. Build order (critical path)

1. **Data layer:** M9-T01 (migration — no deps)
2. **Bootstrap:** M9-T20 (test schema update — alongside T01)
3. **Model:** M9-T02 (StaffUser — depends on T01)
4. **Middleware:** M9-T03 (AuthMiddleware edit — depends on T01)
5. **Controllers:** M9-T04 (StaffController) → M9-T05 (StaffSelfController) → M9-T06 (routes)
6. **Views:** M9-T07, M9-T08, M9-T09 in parallel → M9-T10, M9-T11 → M9-T12 (nav last)
7. **Tests:** M9-T13 (unit, alongside model) → M9-T14 → M9-T15 → M9-T16 → M9-T17 → M9-T18 → M9-T19 (integration, after controllers)

---

## 9. Estimate summary

| Group | Hours |
|-------|------:|
| Data layer (T01) | 1 |
| Model (T02) | 3 |
| Middleware update (T03) | 1 |
| Controllers & routes (T04–T06) | 12 |
| Views (T07–T12) | 15 |
| Tests (T13–T20) | 23 |
| **Total** | **~55 ideal hours (~7 dev-days)** |

---

## 10. Definition of Done

- All P1 tasks complete; all unit + integration tests green (`./vendor/bin/phpunit`) on PHP 8 / MySQL 5.7.
- `must_change_password` migration applied; existing users unaffected (default 0).
- Department Admin can create, edit, deactivate/reactivate, and reset passwords for `staff` in their own department; cannot manage `dept_admin` accounts or cross-department users.
- Institution Admin can manage all staff/dept_admin across all departments; one-dept_admin-per-department rule enforced.
- Any user with `must_change_password = 1` is redirected to `/staff/change-password` on every request until they set a new password; redirect cannot be bypassed.
- Self-action blocks (deactivate self, admin-reset own password) return 403.
- Every write action produces an `audit_log` entry via `MasterAuditLogger`; no password values in the log.
- All POST actions CSRF-protected.
- Commit via `scripts/commit-module.sh "M9 Staff Management: implementation complete"`; user pushes from Mac.

---

## 11. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, implement in Claude Code.
