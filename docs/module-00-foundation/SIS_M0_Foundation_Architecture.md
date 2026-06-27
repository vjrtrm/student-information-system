# SIS — Foundation & Architecture (Module 0)
## Shared design (for review & approval)

**Project:** Student Information System (SIS)
**Scope:** Cross-cutting decisions shared by all 12 modules — settled once here so individual modules don't re-decide them and we avoid foundation refactors later.
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval

> This is a one-off foundation document (not a feature module). Each feature module's own Requirements/Design/Tasks build on top of this and only detail what is specific to that module.

---

## 1. Architecture overview

Server-rendered **PHP 8.x MVC** application on **MySQL 5.7**, **Bootstrap 5** front end, **PDO** data access, **PHPMailer** email, **PhpSpreadsheet** (Excel/CSV), **TCPDF** (PDF). (5.7 gives JSON + utf8mb4; avoid MySQL-8-only features.)

Request lifecycle: `public/index.php` (single front controller) → router → middleware chain (`AuthMiddleware` → `RoleMiddleware` → `DepartmentScopeMiddleware`) → Controller → Model (PDO) → View. Authorisation is therefore enforced before any controller logic runs, on every request.

```
Browser (Bootstrap 5 + vanilla JS + flatpickr)
   ⇅ HTTPS
PHP 8.x MVC (Controllers / Models / Views / Middleware / Helpers)
   ⇅ PDO (prepared statements)
MySQL 5.7
   ⇅ SMTP
PHPMailer → college SMTP (PII-safe templates)
Local/object file store for uploaded PDFs & photos
```

## 2. Project scaffold (shared)

```
/student-information-system/
├── app/
│   ├── Controllers/   # one per feature area (Auth, Student, Profile, Enrolment, ...)
│   ├── Models/        # User, Student, Department, ApprovalRequest, FieldDefinition, ...
│   ├── Helpers/       # Auth, Lockout, Otp, Mailer, Validator, ProfileCompletion,
│   │                  # EnrolmentGenerator, SpreadsheetIO, AuditLogger, Pagination
│   ├── Middleware/    # AuthMiddleware, RoleMiddleware, DepartmentScopeMiddleware, Csrf
│   └── Views/         # auth/ student/ staff/ admin/ layouts/
├── public/index.php   # front controller; public/assets/ (css, js, img)
├── config/            # database.php, mail.php, app.php (no secrets committed)
├── database/          # migrations/ + seeds/
├── storage/           # logs/, uploads/ (git-ignored)
├── tests/             # unit + integration
└── composer.json
```

Composer: `phpmailer/phpmailer ^6.9`, `phpoffice/phpspreadsheet ^2.1`, `tecnickcom/tcpdf ^6.7` (or `dompdf/dompdf`).

## 3. Global data model (ERD, high level)

Core shared entities; **each module details its own columns** in its Design doc.

```
states ──< districts ──< taluks                 (linked master hierarchy)
departments ──< users        (staff/admins; one dept_admin per department)
departments ──< students ──1:1── student_profiles (the ~95 fields)
                       ├──< student_documents       (uploaded files)
                       └──< approval_requests >── users (reviewer)
academic_years ──< students
field_definitions ──< field_options               (admin-managed form schema)
notifications ──< notification_recipients
audit_log        (cross-cutting: who/when/what)
import_log       (bulk uploads)
```

Shared/core tables and their owning module:

| Table | Owner module | Notes |
|-------|--------------|-------|
| `users` | M1/M9 | staff & admins; role, department_id, bcrypt |
| `students` | M1/M3/M5 | login (mobile+dob), serial_no, enrolment_no, statuses |
| `student_profiles` | M5 | the configurable ~95 fields |
| `student_documents` | M5 | UUID files, ≤2 MB |
| `departments` | M2 | code, level (UG/PG → U/P), one dept_admin |
| `academic_years` | M2 | label + 2-digit short year |
| `states`/`districts`/`taluks` | M2 | linked hierarchy, soft-delete |
| `field_definitions`/`field_options` | M10 | dynamic form schema |
| `approval_requests` | M6 | submission/change, single-approval |
| `notifications`/`notification_recipients` | M7 | in-app + email |
| `audit_log` | M0 (shared) | all sensitive actions |
| `import_log` | M3 | bulk upload runs |

## 4. Shared conventions

**Naming.** Tables snake_case plural; columns snake_case; PK `id`; FKs `<entity>_id`; booleans `is_*`; timestamps `created_at`/`updated_at`. Enrolment level stored as `UG`/`PG`, rendered to `U`/`P` in the number.

**Status / soft-delete.** Master and account rows carry `status ENUM('active','inactive')`; never hard-delete in-use records — deactivate so history stays intact.

**Security baseline (all modules).** PDO prepared statements only (no string-concatenated SQL); CSRF token on every state-changing form; `htmlspecialchars()` on output + CSP header; bcrypt passwords; HTTPS enforced; role + department checked server-side every request; sensitive fields (e.g. Aadhaar, DOB) access-restricted and encrypted at rest where required.

**Validation.** Centralised `Validator` helper with reusable rules: mobile = 10 digits, Aadhaar = 12 digits, email, date, pincode = 6 digits, file = PDF (docs) or image (photo) ≤ **2 MB**. Client-side validates format; server always re-validates.

**File uploads.** MIME whitelist, ≤ 2 MB, renamed to UUID on save, stored under `storage/uploads/`, served via an authorised route (never a public path).

**Errors.** Generic user-facing messages (no stack traces/SQL); full detail to server logs only; friendly 403 / 404 / 500 pages.

**Email / notifications.** In-app + email (PHPMailer/SMTP). **No PII in emails** — code/link + first-name greeting only; per-recipient/BCC sends; notification log stores trigger/recipient/status, not bodies or PII. (OTP/reset emails carry only the code/link.)

**Audit.** `AuditLogger` writes `audit_log` (actor, action, entity, details, timestamp) for auth events, approvals, enrolment changes, master-data edits, promotion, field changes, imports — no secrets stored.

**Pagination & export.** List pages paginate (default 20; the student grid offers 25/50/100/250/500). Excel export via PhpSpreadsheet; statistics drill-downs export CSV.

**Config.** Environment/config in `config/*.php`; tunables (timeouts, lockout, OTP TTL, page sizes) read from config with sane defaults; secrets never committed.

## 5. Roles & access (shared)

Roles: `student`, `staff`, `dept_admin` (one per department, full CRUD on its data), and `institution_admin` (cross-department; **in scope for v1**). Students reach only their own record. `DepartmentScopeMiddleware` injects a mandatory department filter for staff/dept_admin; institution_admin bypasses it. Detailed in Module 1.

## 6. Cross-cutting non-functional requirements

Mobile-responsive (Bootstrap 5, usable ≥ 320px, tap targets ≥ 44px); page load < 2 s on college LAN; prepared statements + sensible indexes, no N+1; 99% uptime in operational hours; supported browsers Chrome/Firefox/Edge/Safari (latest 2) + Android Chrome (IE not supported).

## 7. Module dependency map

```
M0 Foundation ─ underpins all
M1 Auth ── M2 Master data ── M3 Onboarding ── M4 Enrolment numbers
                       └────── M5 Student form ── M6 Edit approval ── M7 Notifications
M5/M6 ── M8 Dashboards/Statistics
M1 ── M9 Staff mgmt        M2/M5 ── M10 Field mgmt
M3 ── M11 Data grid/export   M3 ── M12 Promotion
```

Build order follows this map (the roadmap in `CLAUDE.md`).

## 8. Resolved decisions

1. **MySQL 5.7** is the target version (JSON + utf8mb4 available; avoid 8-only features).
2. **Local file storage** under `storage/uploads/` (served via an authorised route, not a public path).
3. **`institution_admin` is in scope for v1** — cross-department role alongside the per-department `dept_admin`.

## 9. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, this becomes the shared baseline. We then resume **Module 1** (approve its Tasks doc → implement → commit), and every later module's specs build on this foundation.
