# SIS — Module 1: Authentication & Access Control
## Stage 2: Design (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 1 of 12 — Authentication & Access Control
**Document stage:** Requirements ✅ → **Design (this document)** → _Tasks_
**Version:** 0.1 (Draft) · June 2026
**Status:** Awaiting review & approval
**Traces:** Requirements doc `SIS_M1_Authentication_Requirements.md` (Epics A–D)

---

## 1. Design goals

Turn the approved requirements into a concrete, buildable design on the SIS stack (PHP 8.x MVC, MySQL 5.7, Bootstrap 5, PDO, PHPMailer). The design specifies the components, data, flows, validation, and security controls needed to satisfy Epics A–D, without yet breaking work into tasks.

## 2. Resolved design decisions (from requirements' open questions)

These defaults are applied in this design; flag any you want changed.

| # | Open question | Design decision (default) |
|---|---------------|---------------------------|
| 1 | Student OTP default | **OFF** by default; controlled by config flag `auth.student_otp_enabled`. |
| 2 | Timeout / lockout | Inactivity **30 min**; lockout after **5** fails for **15 min** (all config-driven). |
| 3 | Institution Admin role | **In scope for v1**; role set `student, staff, dept_admin, institution_admin`. If no institution admin exists, all admin actions are department-scoped. |
| 4 | MySQL version | **MySQL 5.7**; schema avoids 8.x-only features (no functional indexes, no `CHECK` reliance, no window functions). Confirmed (5.7). |

## 3. Component architecture (MVC)

```
Controllers/
  AuthController.php        // login (student + staff), logout, OTP verify
  PasswordResetController.php // forgot, OTP/link verify, set new password
Middleware/
  AuthMiddleware.php        // requires a valid session; else redirect to login
  RoleMiddleware.php        // requires one of allowed roles; else 403
  DepartmentScopeMiddleware.php // injects + enforces department filter
Helpers/
  Auth.php                  // login(), logout(), current_user(), hashing, session
  Lockout.php               // attempt counting + lock window
  Otp.php                   // generate/verify time-boxed codes
  Mailer.php                // PHPMailer wrapper (PII-safe templates)
  AuditLogger.php           // writes auth_audit_log
Views/
  auth/login.php            // tabbed: Student | Staff/Admin
  auth/otp.php              // OTP entry
  auth/forgot-password.php  // request reset
  auth/reset-password.php   // set new password
```

Request lifecycle: `public/index.php` (front controller) → route → middleware chain (`AuthMiddleware` → `RoleMiddleware` → `DepartmentScopeMiddleware`) → controller → view. RBAC is therefore enforced before any controller logic runs.

## 4. Data model (auth-relevant)

Only the columns relevant to this module are shown; other modules extend these tables.

**users** (staff & admin)

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| name | VARCHAR(100) | |
| email | VARCHAR(150) UNIQUE | login id |
| password_hash | VARCHAR(255) | bcrypt |
| role | ENUM('staff','dept_admin','institution_admin') | |
| department_id | INT FK→departments.id | NULL only for institution_admin |
| status | ENUM('active','inactive') | inactive cannot log in |
| failed_attempts | INT default 0 | |
| locked_until | DATETIME NULL | lockout window |
| created_at | TIMESTAMP | |

**students** (auth-relevant subset; full record defined in Module 5)

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| mobile | VARCHAR(10) | login factor 1, indexed |
| dob | DATE | login factor 2 |
| department_id | INT FK | scoping |
| status | ENUM('active','inactive') | |
| failed_attempts / locked_until | as above | shared lockout logic |

**password_resets**

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| user_id | INT FK→users.id | |
| token_hash | VARCHAR(255) | hashed OTP/link token |
| expires_at | DATETIME | 15 min |
| used_at | DATETIME NULL | single-use |

**login_otps** (used only when student OTP enabled)

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| principal_type | ENUM('student','user') | |
| principal_id | INT | |
| code_hash | VARCHAR(255) | hashed |
| expires_at | DATETIME | 15 min, single-use |

**auth_audit_log**

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| principal_type | ENUM('student','user','unknown') | |
| principal_id | INT NULL | |
| event | ENUM('login_success','login_fail','lockout','logout','reset_request','reset_success') | |
| ip / user_agent | VARCHAR | source |
| created_at | TIMESTAMP | |

Indexes: `students(mobile)`, `users(email)`, `auth_audit_log(principal_type,principal_id)`, `password_resets(user_id)`.

## 5. Authentication flows

**5.1 Student login (Epic A1)**

1. Student enters mobile (10 digits) + DOB; client validates format only.
2. Server looks up active student by `mobile`.
3. If found and lockout window not active, compare `dob`.
4. On match: if `student_otp_enabled` → go to OTP flow (5.3); else create session, regenerate session id, reset `failed_attempts`, log `login_success`, redirect to student dashboard.
5. On mismatch/not found: increment `failed_attempts`, log `login_fail`, show generic error; at 5 fails set `locked_until = now()+15m` and log `lockout`.

**5.2 Staff/Admin login (Epic B1)** — same as 5.1 but keyed on `email` and `password_verify()` against `password_hash`; routes to role-specific dashboard.

**5.3 OTP step (Epic A2)** — generate 6-digit code, store hash in `login_otps` (15-min expiry), email via PII-safe template; verify on submit; single-use; expired/invalid → re-issue allowed with rate limit.

**5.4 Password reset (Epic B2)**

1. User submits email → always show the same neutral confirmation (no account enumeration).
2. If the email matches an active user, store a hashed token in `password_resets` (15-min) and email the OTP/link (no PII).
3. User submits token + new password (≥8 chars, ≥1 number, confirmed twice).
4. On success: update `password_hash`, mark token `used_at`, invalidate existing sessions, log `reset_success`.

## 6. RBAC & department scoping (Epic C)

**Permission matrix (Module 1 surface):**

| Capability | Student | Staff | Dept Admin | Inst Admin |
|------------|:------:|:----:|:----------:|:----------:|
| Access own student record | ✓ | – | – | – |
| Access department student list | – | ✓ (own dept) | ✓ (own dept) | ✓ (all) |
| Reach `/admin/*` routes | – | – | ✓ (own dept) | ✓ (all) |
| Cross-department access | – | – | – | ✓ |

- `RoleMiddleware` declares allowed roles per route; mismatch → HTTP 403 page.
- `DepartmentScopeMiddleware` adds a mandatory `department_id = :session_dept` filter to all scoped queries for `staff`/`dept_admin`; `institution_admin` bypasses the filter.
- Direct-object access (e.g. `/students/{id}`) re-checks ownership/department server-side before rendering.

## 7. Session & security design (Epic D)

- Session cookie: `HttpOnly`, `Secure`, `SameSite=Lax`; session id regenerated on every successful login and on privilege change.
- Inactivity timeout 30 min via last-activity timestamp; hard expiry on logout.
- Lockout via `failed_attempts` + `locked_until` on the principal row (shared `Lockout` helper for students and users).
- CSRF token on every POST (login, OTP, reset); verified server-side.
- Generic user-facing errors; detailed errors only in server logs.
- All auth emails use PII-safe templates (code/link only).

## 8. Screen behaviour & validation

| Screen | Fields | Client validation | Server validation | Key messages |
|--------|--------|-------------------|-------------------|--------------|
| Login (Student tab) | Mobile, DOB | 10 digits; date picker | active student match; lockout check | "Invalid login details"; "Account temporarily locked, try again in N minutes" |
| Login (Staff/Admin tab) | Email, Password | email format; non-empty | credential verify; lockout | as above |
| OTP | 6-digit code | numeric, length 6 | hash + expiry + single-use | "Code expired — request a new one" |
| Forgot password | Email | email format | neutral confirmation always | "If the email exists, a reset link has been sent" |
| Reset password | New, Confirm | ≥8 chars, ≥1 number, match | re-validate + token valid | "Password updated — please sign in" |

Login is a single page with two tabs (Student · Staff/Admin), responsive ≥320px, tap targets ≥44px.

## 9. Configuration parameters

`auth.student_otp_enabled` (bool, default false) · `auth.session_timeout_minutes` (30) · `auth.lockout_threshold` (5) · `auth.lockout_minutes` (15) · `auth.otp_ttl_minutes` (15) · `auth.reset_ttl_minutes` (15) · `auth.password_min_length` (8).

## 10. Edge cases

- Inactive account → refused with generic error; logged.
- Two students sharing a mobile (data error) → reject login, flag for admin review (mobile expected unique post-onboarding).
- Reset token reuse / expiry → refused; user must re-request.
- Clock-skew on expiry handled server-side (UTC timestamps).

## 11. Traceability (requirements → design)

| Requirement | Design section |
|-------------|----------------|
| A1, A2 student login / OTP | §5.1, §5.3, §4 (students, login_otps) |
| B1, B2 staff login / reset | §5.2, §5.4, §4 (users, password_resets) |
| C1, C2 RBAC / scoping | §3 middleware, §6 matrix |
| D1 lockout | §4 columns, §7 |
| D2 sessions/timeout | §7, §9 |
| D3 audit | §4 auth_audit_log, §5/§7 logging |

## 12. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, the next step is **Stage 3: Tasks** for Module 1 — a build-ready work breakdown (with estimates, dependencies and test tasks) derived from this design, submitted for your review before any implementation.
