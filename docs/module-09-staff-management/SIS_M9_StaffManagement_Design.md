# SIS — Module 9: Staff Management
## Stage 2: Design (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 9 of 12 — Staff Management
**Document stage:** Requirements ✅ → **Design (this document)** → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M9_StaffManagement_Requirements.md`

---

## 1. Design goals

- Single `StaffController` handles list, create, edit, deactivate/reactivate, and password reset — all scoped by role at the query level.
- `StaffProfile` self-service and forced password change live in a separate `StaffSelfController` to keep auth-adjacent logic isolated.
- One migration: add `must_change_password` column to `users`; all other queries read/write existing columns.
- `must_change_password` redirect enforced inside `AuthMiddleware` immediately after session is established — cannot be bypassed by navigating directly to any route.
- No new model class needed: `Db` queries in the controller are thin enough; a lightweight `StaffUser` model is introduced only to encapsulate the one-dept_admin-per-department check and password hashing.
- All write actions guarded by CSRF and `MasterAuditLogger`.

---

## 2. Resolved design decisions (from open questions)

| # | Question | Decision & rationale |
|---|----------|----------------------|
| 1 | Deactivating staff with pending queue items — warning or silent? | **Silent deactivation.** No reassignment logic. Pending approvals and RTCs remain in the department queue; another active staff member picks them up. A warning banner ("This staff member has N pending items") is shown on the deactivate confirmation modal as information only — not a blocker. Rationale: enforcing reassignment adds significant complexity for an edge case; the queue is department-wide, not person-owned. |
| 2 | Show locked-until status / allow dept_admin to unlock? | **Out of scope for v1.** The staff list will show `status = active/inactive` only. Account lockout (`locked_until`) is an auth-layer concern; unlocking is done by waiting for the lockout window to expire (M1 behaviour). Adding an unlock action is deferred to a future hardening release. |
| 3 | Password complexity — 8 chars only or mixed rules? | **8 characters minimum, no character-class requirement in v1.** Rationale: staff accounts are internal; the forced-change flow on first login is the primary security control. A stricter policy can be added in a future iteration without schema changes. |
| 4 | Department change — what happens to pending RTCs/approvals? | **Leave as-is.** Pending `change_requests` and approval-queue items referencing the original department remain unchanged. Another active staff member in the original department will action them. No reassignment or warning needed. Rationale: RTCs and approvals are scoped to `department_id`, not `reviewed_by`; the queue is always accessible to any active staff in that department. |

---

## 3. Component architecture (MVC)

### Controllers

**`app/Controllers/StaffController.php`** — department-scoped staff management (dept_admin + institution_admin)

| Method | Route | Roles | Description |
|--------|-------|-------|-------------|
| `index()` | GET /staff | dept_admin, institution_admin | Staff list; dept_admin sees own dept; inst_admin sees all with optional ?department_id= filter |
| `createForm()` | GET /staff/create | dept_admin, institution_admin | Blank create form |
| `store()` | POST /staff/create | dept_admin, institution_admin | Validate + create user; set must_change_password=1 |
| `editForm(int $id)` | GET /staff/{id}/edit | dept_admin, institution_admin | Pre-filled edit form; dept_admin sees limited fields |
| `update(int $id)` | POST /staff/{id}/edit | dept_admin, institution_admin | Validate + update; role/dept changes inst_admin only |
| `toggleStatus(int $id)` | POST /staff/{id}/toggle-status | dept_admin, institution_admin | Activate / deactivate; with pending-items count in flash warning |
| `resetPasswordForm(int $id)` | GET /staff/{id}/reset-password | dept_admin, institution_admin | Reset password form |
| `resetPassword(int $id)` | POST /staff/{id}/reset-password | dept_admin, institution_admin | Bcrypt new temp password; set must_change_password=1 |

**`app/Controllers/StaffSelfController.php`** — self-service for all staff roles

| Method | Route | Roles | Description |
|--------|-------|-------|-------------|
| `profileForm()` | GET /staff/profile | staff, dept_admin, institution_admin | Own profile edit form (name + staff_code only) |
| `profileUpdate()` | POST /staff/profile | staff, dept_admin, institution_admin | Save name/staff_code; audit log |
| `changePasswordForm()` | GET /staff/change-password | staff, dept_admin, institution_admin | Change password form (current + new + confirm) |
| `changePassword()` | POST /staff/change-password | staff, dept_admin, institution_admin | Verify current pw; bcrypt new pw; clear must_change_password flag |

### Model

**`app/Models/StaffUser.php`** — static helpers that keep the controller thin:

| Method | Description |
|--------|-------------|
| `findById(int $id): ?array` | SELECT from users |
| `findByDept(int $deptId): array` | All staff/dept_admin in a department |
| `findAll(?int $deptId): array` | All staff across depts; optional dept filter for inst_admin |
| `create(array $data): int` | INSERT; returns new id |
| `update(int $id, array $data): void` | UPDATE; accepts only whitelisted columns |
| `hasDeptAdmin(int $deptId, ?int $excludeId = null): bool` | One-dept_admin-per-dept check; excludeId skips self when editing |
| `pendingItemsCount(int $userId, int $deptId): int` | COUNT pending change_requests where department_id=? (for deactivation warning) |

Password hashing is always done in the controller immediately before calling `StaffUser::create()` / `StaffUser::update()` — never passed to the model in plaintext.

### Views

| File | Used by |
|------|---------|
| `app/Views/staff/index.php` | Staff list (dept_admin, institution_admin) |
| `app/Views/staff/form.php` | Create + edit (shared; role controls which fields are editable) |
| `app/Views/staff/reset_password.php` | Password reset form |
| `app/Views/staff/profile.php` | Self-service profile edit |
| `app/Views/staff/change_password.php` | Self-service / forced password change |

---

## 4. Data model

### Migration

**`database/migrations/024_alter_users_must_change_password.sql`**

```sql
ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0;
```

All existing users default to `0` (no forced change). New staff created via M9 UI get `1`.

### Columns used (existing, no change)

| Column | Used for |
|--------|---------|
| `users.name` | Display name |
| `users.email` | Login credential; unique |
| `users.password_hash` | bcrypt hash |
| `users.role` | `staff` / `dept_admin` |
| `users.department_id` | Department scoping |
| `users.staff_code` | Optional identifier |
| `users.status` | `active` / `inactive` |
| `users.failed_attempts` | Auth lockout (read-only in M9) |
| `users.locked_until` | Auth lockout (not surfaced in M9 UI) |
| `users.created_at` | Display in staff list |

---

## 5. Flows

### 5.1 Create staff (dept_admin)

```
GET /staff/create
  → StaffController::createForm() → render staff/form.php
    (role field absent; department fixed to Auth::departmentId())

POST /staff/create
  → requireCsrf()
  → RoleMiddleware(['dept_admin','institution_admin'])
  → validate: name required, email valid + unique, password 8+ chars
  → if role=dept_admin in POST → reject (dept_admin cannot set role)
  → password_hash(password, PASSWORD_BCRYPT)
  → StaffUser::create([name, email, password_hash, role='staff',
       department_id=Auth::departmentId(), staff_code, status='active',
       must_change_password=1, created_at])
  → MasterAuditLogger::log(actor, 'create', 'user', newId, ['role','dept'])
  → flash success → redirect /staff
```

### 5.2 Promote to dept_admin (institution_admin only)

```
POST /staff/{id}/edit  (role field = 'dept_admin')
  → StaffUser::hasDeptAdmin(deptId, excludeId=$id)
      true  → flash error "Department already has a Dept Admin" → redirect back
      false → StaffUser::update($id, ['role'=>'dept_admin'])
           → MasterAuditLogger::log(... 'role_change' ...)
           → flash success → redirect /staff
```

### 5.3 must_change_password enforcement in AuthMiddleware

```
AuthMiddleware::handle()
  → session valid → Auth::user() loaded
  → if Auth::user()['must_change_password'] === 1
       AND current route !== '/staff/change-password'
       AND current route !== '/logout'
     → header('Location: /staff/change-password') → exit
```

This runs on every authenticated request — no route can bypass it.

### 5.4 Forced password change

```
GET /staff/change-password
  → StaffSelfController::changePasswordForm() → render staff/change_password.php
    (shows "You must set a new password before continuing" banner if must_change_password=1)

POST /staff/change-password
  → requireCsrf()
  → if must_change_password=0: verify current_password via password_verify()
    if must_change_password=1: skip current-password check (no known current pw)
  → validate new_password 8+ chars, matches confirm_password
  → password_hash(new_password, PASSWORD_BCRYPT)
  → UPDATE users SET password_hash=?, must_change_password=0 WHERE id=?
  → MasterAuditLogger::log(actor, 'password_change', 'user', id, [])
  → redirect /dashboard
```

### 5.5 Deactivate with warning

```
POST /staff/{id}/toggle-status
  → load user; verify dept scope
  → if toggling to inactive:
       count = StaffUser::pendingItemsCount($id, $deptId)
       if count > 0: flash warning "Staff deactivated. {N} pending item(s) remain in the department queue."
  → UPDATE users SET status='inactive'/'active'
  → MasterAuditLogger::log(... 'deactivate'/'reactivate' ...)
  → redirect /staff
```

---

## 6. RBAC & department scoping

| Action | Staff | Dept Admin | Institution Admin |
|--------|-------|-----------|------------------|
| View staff list | — | Own dept only | All depts + filter |
| Create staff account | — | Own dept, role=staff only | Any dept, any role |
| Edit name/staff_code/status | — | Own dept staff only | Any staff |
| Edit email / role / department | — | ✗ | ✓ |
| Deactivate/reactivate staff | — | Own dept staff only (not dept_admin) | Any |
| Deactivate/reactivate dept_admin | — | ✗ | ✓ |
| Reset any staff password | — | Own dept staff only | Any |
| Edit own profile | ✓ | ✓ | ✓ |
| Change own password | ✓ | ✓ | ✓ |
| Reset own password via admin UI | ✗ (blocked) | ✗ (blocked) | ✗ (blocked) |
| Create institution_admin | — | ✗ | ✗ (not in UI) |

Department scoping enforced in `StaffUser::findByDept()` / `findAll()` and in `StaffController` before every write: load the target user, verify `department_id` matches `Auth::departmentId()` (or bypass for institution_admin).

Self-action blocks: `StaffController` checks `$id === Auth::userId()` before deactivate and admin password reset — returns 403 with flash error.

---

## 7. Session / security & validation

| Rule | Implementation |
|------|----------------|
| Passwords never logged | `MasterAuditLogger` receives only field keys (e.g., `['password_hash']`), never values |
| Plaintext password never stored | `password_hash()` called in controller before any DB write |
| must_change_password bypass prevention | Checked in `AuthMiddleware::handle()` before route dispatch |
| CSRF | `$this->requireCsrf()` on all POST actions |
| Email uniqueness | SELECT check before INSERT; MySQL unique index is the final safety net (catches race) |
| Role escalation prevention | Role field in POST ignored for dept_admin; inst_admin cannot set role=institution_admin (whitelist: ['staff','dept_admin']) |
| Self-deactivation prevention | `$id === Auth::userId()` guard in toggleStatus(); 403 + flash error |
| Dept scope on writes | Load target user row; check `department_id` matches caller's dept (or caller is inst_admin) |

### Validation rules

| Field | Rules |
|-------|-------|
| name | Required, 2–100 chars |
| email | Required, valid email format, unique in `users` |
| password (create/reset) | Required, 8+ chars |
| new_password (change) | Required, 8+ chars, matches confirm_password |
| current_password (self-change, must_change=0) | Must verify against stored hash |
| staff_code | Optional, alphanumeric + hyphens, max 20 chars |
| role | Whitelist: `staff`, `dept_admin` (inst_admin only) |
| department_id | Must exist in `departments`; inst_admin only |

---

## 8. Screen behaviour & messages

### Staff list (`/staff`)

- Table columns: Name, Email, Role badge, Status badge, Staff Code, Date Added, Actions.
- Actions column: Edit | Reset Password | Deactivate/Reactivate (toggle label by status).
- Dept_admin row for the logged-in user: Actions column shows "You" label; Edit/Deactivate buttons absent for own row.
- Institution Admin: department column added; department filter dropdown at top.
- Client-side search: `<input>` filters table rows by name or email on `keyup` (no page reload).

### Create/Edit form (`staff/form.php`)

Shared view; PHP variable `$editable` controls which fields render as `<input>` vs `<span>` (read-only display):

| Field | Dept Admin (create) | Dept Admin (edit) | Inst Admin (create/edit) |
|-------|--------------------|--------------------|--------------------------|
| Name | editable | editable | editable |
| Email | editable | read-only | editable |
| Password | editable | absent (use reset) | editable |
| Staff Code | editable | editable | editable |
| Role | absent (fixed=staff) | absent | dropdown (staff/dept_admin) |
| Department | absent (fixed) | absent | dropdown |
| Status | absent | toggle | toggle |

### Deactivation confirmation

A Bootstrap modal confirms deactivation. If `pendingItemsCount > 0`, modal body includes: "Warning: this staff member has {N} pending item(s) in the department queue. They will remain visible to other staff." Confirm button proceeds; Cancel dismisses.

### Flash messages

| Action | Message |
|--------|---------|
| Create success | "Staff account for [Name] created. They must set a new password on first login." |
| Edit success | "Staff account updated." |
| Deactivate | "Account deactivated." (+ warning if pending items) |
| Reactivate | "Account reactivated." |
| Password reset | "Temporary password set. Staff must change it on next login." |
| Profile save | "Profile updated." |
| Password change | "Password changed successfully." |
| Email duplicate | "This email address is already in use." |
| Dept_admin exists | "This department already has a Department Admin. Demote the existing one first." |
| Self-action blocked | "You cannot perform this action on your own account." |

---

## 9. Configuration parameters

No new config keys. Password minimum length (8) is a PHP constant in `StaffController`:

```php
private const MIN_PASSWORD_LENGTH = 8;
```

Changing it in a future version requires only editing this constant (and updating the view hint text).

---

## 10. Edge cases

| Scenario | Handling |
|----------|----------|
| Dept_admin tries to create another dept_admin via POST manipulation | Role field whitelisted server-side; any value other than `staff` silently coerced to `staff` for dept_admin callers |
| Institution admin promotes to dept_admin but dept already has one | `hasDeptAdmin()` check → flash error, no update |
| Staff member's `must_change_password=1` but they navigate to `/logout` | Logout allowed; flag persists for next login |
| Admin resets password for already-inactive staff | Allowed (admin may reset then reactivate); `must_change_password=1` set |
| Two admins simultaneously create a dept_admin for the same dept | MySQL unique index on email prevents duplicate user; hasDeptAdmin race is benign — second request hits the check after first commits, returns error |
| Email changed by institution_admin to one already in use | SELECT check returns duplicate → validation error; unique index is final guard |
| `must_change_password` flag on institution_admin account | AuthMiddleware applies to all roles including institution_admin; they also see the change-password page if flagged |
| Staff with `locked_until` in the future | Lock is auth-layer concern (M1); M9 does not surface or clear it. Staff must wait for lock to expire. |
| Department changed for a staff member currently logged in | Their session retains old `department_id` until next login; `Auth::departmentId()` reads from session. Acceptable: next login picks up new dept. |

---

## 11. Traceability (requirement → design)

| Requirement | Design element |
|-------------|---------------|
| A1 — Staff list | `StaffController::index()` + `StaffUser::findByDept/findAll()` + `staff/index.php` |
| A2 — Dept admin creates staff | `StaffController::createForm/store()` + validation + `StaffUser::create()` |
| A3 — Inst admin creates staff/dept_admin | `store()` role whitelist + `StaffUser::hasDeptAdmin()` check |
| B1 — Dept admin edits staff | `editForm/update()` + `$editable` field control in shared form view |
| B2 — Inst admin edits any | `update()` + role/dept fields enabled for inst_admin |
| B3 — Admin resets password | `resetPasswordForm/resetPassword()` + `must_change_password=1` |
| C1 — Forced password change | `AuthMiddleware` redirect + `StaffSelfController::changePassword()` + skip current-pw if flag=1 |
| D1 — Self-service profile | `StaffSelfController::profileForm/profileUpdate()` + `staff/profile.php` |
| NFR — Audit | `MasterAuditLogger` call in every write action |
| NFR — CSRF | `requireCsrf()` on all POST |
| NFR — One dept_admin | `StaffUser::hasDeptAdmin()` checked in `store()` and `update()` |
| NFR — No PII in audit | Field keys only passed to logger; no password values |
| Migration | `024_alter_users_must_change_password.sql` |

---

## 12. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, proceed to Tasks.
