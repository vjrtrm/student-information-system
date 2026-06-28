# SIS — Module 9: Staff Management
## Stage 1: Requirements (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 9 of 12 — Staff Management
**Document stage:** **Requirements (this document)** → _Design_ → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Builds on:** Authentication & Access Control (M1), Master Data & Department Management (M2)

---

## 1. Purpose & objectives

Staff users (role `staff` and `dept_admin`) are currently seeded or created directly in the database. Module 9 gives Department Admins the ability to manage their own department's staff accounts through the application — creating, editing, activating, deactivating, and resetting credentials — without requiring database access. Institution Admins can manage staff across all departments.

Objectives:

- Allow Department Admins to create and manage staff accounts for their own department.
- Allow Institution Admins to manage staff across all departments, including promoting staff to dept_admin.
- Enforce one Department Admin per department (the locked product decision: "one staff-admin per dept, full CRUD on its data").
- Provide secure credential reset (password reset for staff; no OTP/email for the admin trigger — admin sets a temporary password).
- Track all staff management actions in the audit log.
- Ensure deactivated staff cannot log in (existing `status = 'inactive'` check in M1 already enforces this).

---

## 2. In scope

### 2.1 Staff list

- Department Admin sees all staff in their own department (`users` table where `department_id = Auth::departmentId()` and `role IN ('staff', 'dept_admin')`).
- Institution Admin sees all staff across all departments; can filter by department.
- Columns: Name, Email, Role, Status (Active / Inactive), Staff Code, Date Added, Actions.
- Search by name or email (client-side filter in v1; no separate search endpoint).

### 2.2 Create staff account

- Department Admin can create a `staff` account in their own department.
- Institution Admin can create a `staff` or `dept_admin` account in any department.
- Fields: Full Name (required), Email (required, unique across `users`), Staff Code (optional, alphanumeric), Role (`staff` — fixed for dept_admin; `staff` or `dept_admin` for institution_admin), Department (fixed for dept_admin; dropdown for institution_admin).
- On creation: a **temporary password** is set by the creator (must meet minimum complexity: 8+ chars); staff must change it on first login (enforced by a `must_change_password` flag).
- No automated welcome email in this module — notification events are M7's domain and staff email notification is out of scope for v1.
- Department Admin cannot create another `dept_admin`; only Institution Admin can assign `dept_admin` role.
- One `dept_admin` per department enforced: if a `dept_admin` already exists for the department, Institution Admin must demote them first before promoting another.

### 2.3 Edit staff account

- Department Admin can edit Name, Staff Code, and Status (Active/Inactive) of staff in their department. Cannot change email or role.
- Institution Admin can edit Name, Email, Staff Code, Role, Status, and Department for any staff member.
- Changing a staff member's department removes their access to the old department's data immediately (session-level: they will see the new department on next login).
- A staff member cannot edit their own role or status (to prevent self-escalation or self-lockout).

### 2.4 Deactivate / reactivate staff

- Department Admin can deactivate or reactivate any `staff` (not `dept_admin`) in their department.
- Institution Admin can deactivate/reactivate any staff or dept_admin in any department.
- Deactivation sets `users.status = 'inactive'`; M1's `AuthMiddleware` already blocks inactive users from logging in.
- A dept_admin cannot deactivate themselves.
- The last active staff member in a department can be deactivated (no minimum-staff enforcement in v1).

### 2.5 Reset staff password

- Department Admin can reset the password of any `staff` in their department.
- Institution Admin can reset any staff/dept_admin password.
- Admin enters and confirms a new temporary password (8+ chars); this overwrites `password_hash` (bcrypt).
- `must_change_password` flag set to `1`; on next login the staff member is redirected to a "Change Password" page before reaching the dashboard.
- A staff member can also change their own password via a "Change Password" link in the nav (always available, not reset-triggered).

### 2.6 Forced password change on first login / after reset

- When `must_change_password = 1`, after successful credential verification the auth flow redirects to `GET /staff/change-password` instead of `/dashboard`.
- Staff submits new password (8+ chars, confirmed); on success `must_change_password` set to `0`, redirected to `/dashboard`.
- If staff navigates away or closes the browser, the flag remains; they are redirected again on next login.

### 2.7 Staff own profile (self-service)

- Any logged-in staff member can update their own display name and staff code via `GET/POST /staff/profile`.
- Cannot change their own email, role, department, or status.
- Password change (current password required for verification) available at `GET/POST /staff/change-password`.

---

## 3. Out of scope (this module)

- Student account management — students are created via bulk upload (M3) and login via mobile + DOB (M1).
- Institution Admin account creation — Institution Admin accounts are seeded/provisioned outside the app in v1.
- Role: `institution_admin` — no UI to create or manage institution_admin users in v1.
- Automated welcome emails to new staff — no email notifications for staff account creation.
- Two-factor authentication for staff.
- Staff profile photo or avatar upload.
- Bulk staff import via CSV.
- Staff activity reports or individual audit trails per staff member.

---

## 4. Roles involved

| Role | Can do |
|------|--------|
| Staff | View and edit own profile; change own password |
| Department Admin | Full CRUD on `staff` in own dept; reset passwords; cannot create/modify `dept_admin` |
| Institution Admin | Full CRUD on all staff + dept_admin across all departments; promote/demote dept_admin |

---

## 5. Assumptions & dependencies

- `users` table already has `name`, `email`, `password_hash`, `role`, `department_id`, `staff_code`, `status`, `failed_attempts`, `locked_until`, `created_at` columns (M1).
- A `must_change_password` column does not yet exist — this module adds it (`TINYINT(1) NOT NULL DEFAULT 0`).
- `bcrypt` password hashing via PHP `password_hash()` / `password_verify()` (M1 convention).
- `MasterAuditLogger` used for all staff management actions (create/edit/deactivate/reset); `AuditLogger` is for auth events only (M1 convention — do not mix).
- Email uniqueness enforced at the application layer (SELECT before INSERT); MySQL unique index already exists on `users.email` (M1 migration).
- Department Admin's own account cannot be edited via the staff management UI — they use the self-service staff profile page.

---

## 6. Epics & user stories

### Epic A — Staff list & creation

**A1. Department Admin views their department's staff**
As a department admin, I want to see a list of all staff in my department so that I can manage their accounts from one place.

Acceptance criteria:
- Given I am logged in as dept_admin, when I visit `/staff`, then I see a table of all users in my department with role `staff` or `dept_admin`.
- Given there are no staff besides myself, then the table shows only my own row.
- Given I search by name or email, then the table filters to matching rows (client-side).

**A2. Department Admin creates a new staff account**
As a department admin, I want to create a new staff account so that I can onboard a new team member without IT involvement.

Acceptance criteria:
- Given I fill in Name, Email, temporary password, and optionally Staff Code, when I submit, then a new `staff` user is created in my department with `status = active` and `must_change_password = 1`.
- Given the email already exists in `users`, then form shows "Email already in use" and no record is created.
- Given the password is fewer than 8 characters, then form shows "Password must be at least 8 characters."
- Given creation succeeds, then an audit log entry is written and I am redirected to the staff list with a flash success message.

**A3. Institution Admin creates staff or dept_admin in any department**
As an institution admin, I want to create staff or dept_admin accounts in any department so that I can set up new departments without database access.

Acceptance criteria:
- Given I select role `dept_admin` and the selected department already has a `dept_admin`, then form shows "This department already has a Department Admin. Demote the existing one first."
- Given I select role `staff`, then the account is created normally regardless of existing dept_admin.
- Given I create a `dept_admin`, then audit log records the role assignment.

### Epic B — Edit, deactivate, reset

**B1. Department Admin edits a staff member**
As a department admin, I want to edit a staff member's name, staff code, or status so that I can keep accounts up to date.

Acceptance criteria:
- Given I edit a staff member's name and save, then the change is reflected immediately in the staff list and audit log.
- Given I try to change a staff member's email via the edit form, then the email field is not present (read-only in dept_admin edit form).
- Given I try to edit a `dept_admin` account while I am a dept_admin, then the edit action is blocked (403).

**B2. Institution Admin edits any staff member**
As an institution admin, I want to edit any staff member's details including role and department so that I can reorganise staff without database access.

Acceptance criteria:
- Given I change a staff member's department, then on their next login they see data for the new department only.
- Given I demote a `dept_admin` to `staff`, then the department now has no dept_admin (acceptable).
- Given I try to change a staff member's role to `institution_admin`, then the role dropdown does not include that option.

**B3. Admin resets a staff password**
As a department admin or institution admin, I want to reset a staff member's temporary password so that I can unblock a staff member who has forgotten their credentials.

Acceptance criteria:
- Given I set a new temporary password and save, then `password_hash` is updated and `must_change_password = 1`.
- Given the staff member next logs in, then they are redirected to the change-password page before reaching the dashboard.
- Given I reset my own password via the admin UI, then the action is blocked with an error ("Use the Change Password page to update your own password").

### Epic C — Forced password change

**C1. Staff is forced to change a temporary password**
As a staff member whose password was reset or who just joined, I want to be prompted to set my own password on first login so that only I know my credentials.

Acceptance criteria:
- Given `must_change_password = 1` and I complete credential verification, then I am redirected to `/staff/change-password` rather than `/dashboard`.
- Given I submit a new password that matches the confirmation and is 8+ characters, then `must_change_password` is set to 0 and I am redirected to `/dashboard`.
- Given I navigate directly to `/dashboard` while `must_change_password = 1`, then I am redirected back to `/staff/change-password`.
- Given I submit mismatched passwords, then form shows "Passwords do not match."

### Epic D — Self-service

**D1. Staff updates their own display name**
As a staff member, I want to update my display name and staff code so that my profile stays current.

Acceptance criteria:
- Given I submit a new name, then `users.name` is updated and a flash success message shown.
- Given I try to change my own email via this form, then the email field is read-only (displayed but not editable).

---

## 7. Non-functional requirements (module-relevant)

- **Security** — password fields never logged or stored in plaintext; temporary passwords set by admins are bcrypt-hashed immediately on save. `must_change_password` redirect enforced server-side in `AuthMiddleware` (cannot be bypassed by navigating directly to `/dashboard`).
- **Audit** — every create, edit, deactivate, reactivate, and password reset logged to `audit_log` via `MasterAuditLogger`. Fields logged: entity=`user`, entity_id, action, actor_id, actor_role. No password values in audit log.
- **CSRF** — all POST forms include CSRF token; controller verifies via `$this->requireCsrf()`.
- **One dept_admin per department** — enforced at application layer on create and role-change; not a DB constraint (to allow zero dept_admins after demotion).

---

## 8. Open questions

| # | Question | Owner | Resolution needed by |
|---|----------|-------|---------------------|
| 1 | Should deactivating a staff member who has pending RTC reviews in their queue trigger any warning or reassignment? Or is the deactivation silent (another staff member will action the queue)? | Product | Before Design |
| 2 | Should the staff list show the `locked_until` status (account temporarily locked by M1 failed-login logic) so dept_admin can see and potentially unlock? Or is that out of scope for v1? | Product | Before Design |
| 3 | Password complexity: is 8 characters minimum sufficient, or should we also require a mix of uppercase, lowercase, and digits? | Product | Before Design |
| 4 | When Institution Admin changes a staff member's department, should any pending RTCs or approval queue items that were assigned to that staff member be reassigned, or left as-is (another staff member in the original department picks them up)? | Product | Before Design |

---

## 9. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, proceed to Design.
