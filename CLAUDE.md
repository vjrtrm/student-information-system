# CLAUDE.md — Student Information System (SIS)

> Auto-loaded each session. Keep it short. Its job is to stop Claude re-deriving context (= saved tokens). Read the per-module `.md` specs, **not** the large `.docx`, unless a binary/print artifact is explicitly needed.

## What this project is
College Student Information System. Web app. Roles: Student, Department Staff, Department Admin (one staff-admin per dept, full CRUD on its data), Institution Admin (cross-department, in v1).

## Stack (fixed)
PHP 8.x · MVC · MySQL 5.7 · Bootstrap 5 · PDO · PHPMailer/SMTP · PhpSpreadsheet · TCPDF. Avoid MySQL-8-only features. File uploads stored locally under storage/uploads/.

## Locked product decisions
- Student login = mobile + DOB. Staff/admin = email + password (bcrypt).
- Enrolment no.: `<YY><U|P><DeptCode><serial>` e.g. `24UBCA041`, `26PMCA100`. Prepared in bulk by dept staff, **released after admin approval**; student sees serial until then.
- Submission/edit approval = **single approval** (dept staff primary; admin may also). Any one approval verifies.
- ~95 student fields. Blue colour-coded parent fields optional. Document uploads optional, PDF/image ≤ 2 MB. Passport photo image-only.
- No PII in email notifications (code/link only).
- Features: Request-to-Change, bulk upload + duplicate handling, promotion (June window), student data grid + Excel export, admin statistics cards, master data incl. Department/Taluk/District/State.

## Mandatory workflow (do not skip)
Every feature/module goes **Requirements → Design → Tasks**, each as a Markdown file, **each presented for the user's review + explicit approval before the next stage.** Never jump ahead. Use the `sis-spec` project skill templates (`.claude/skills/sis-spec/templates/`) so structure isn't regenerated from scratch.

## Module roadmap — ALL 12 MODULES COMPLETE (specs + code committed)
1. Authentication & Access Control  ✅
2. Master Data & Department Management  ✅
3. Student Onboarding (bulk upload)  ✅
4. Enrolment Number Generation & Approval  ✅
5. Student Information Form (dynamic fields, partial save, submit/lock)  ✅
6. Submission & Edit Approval (Request-to-Change)  ✅
7. Notifications  ✅
8. Dashboards, Statistics & Personalisation  ✅
9. Staff Management  ✅
10. Field Management  ✅
11. Student Data Grid & Export  ✅
12. Student Promotion  ✅

v1 feature-complete. Remaining work is hardening: full-suite test run, UAT, security review, deployment.

## File conventions
- Per-module specs live under `docs/module-<NN>-<name>/` as `SIS_M<N>_<Name>_<Requirements|Design|Tasks>.md`.
- Master combined spec: `docs/SIS_Specification.docx` (reference only; don't read unless necessary).

## Code conventions (established M1–M5 — reuse, don't reinvent)
- **Routing:** add routes to the table in `public/index.php` (`matchRoute` supports `{param}`). Group by module.
- **Controllers** extend `App\Controllers\Controller`; call `RoleMiddleware::handle([...])` at the top of each action; verify CSRF via `$this->requireCsrf()` on POST; department-scope with `Auth::departmentId()` / `DepartmentScopeMiddleware`.
- **DB:** `App\Helpers\Db` (static, injectable PDO) with prepared statements only. Compute timestamps in PHP (`date('Y-m-d H:i:s')`), never `NOW()` (SQLite tests).
- **Migrations:** one file per change, `NNN_verb_subject.sql`, unique prefix; plain `ADD COLUMN` (no `IF NOT EXISTS`). Mirror every new table/column into `tests/bootstrap.php` `sis_test_schema()` as `CREATE TABLE IF NOT EXISTS` (SQLite-compatible).
- **Audit:** master/business actions → `audit_log` via `MasterAuditLogger`; auth events → `auth_audit_log` via `AuditLogger`. Don't mix.
- **PII:** mask Aadhaar with `View::maskAadhaar()`; no PII in emails/logs. Uploads via `DocumentUploadHandler` (≤ 2 MB; photo = image-only).
- **UI:** Bootstrap 5 via `Views/layouts/app.php`; user feedback through `$_SESSION['flash']`.
- **Tests:** PHPUnit on in-memory SQLite; extend `Tests\TestCase` (seeders: `seedDepartment/seedUser/seedStudent/seedFullStudent`).

## Where work happens
- **Specs (Requirements/Design/Tasks)** are authored in Cowork, approval-gated.
- **Implementation** is done in **Claude Code** on the user's Mac (real PHP 8 / MySQL 5.7, runs PHPUnit, pushes). The Cowork sandbox has no PHP runtime.
- Cowork must **WAIT** for the user's "Module N done" confirmation (tests green + pushed from Claude Code) before starting the next module's spec cycle.

## Implement a module in Claude Code
```
cd "<project>" && claude
```
Then: "Implement Module <N> (<name>) per docs/module-<NN>-<name>/SIS_M<N>_<Name>_Tasks.md, in build order; reuse Module 1 conventions; run ./vendor/bin/phpunit until green; commit via scripts/commit-module.sh; do not start the next module."

## Git rule
**Commit after a module's IMPLEMENTATION is complete (code written + tests passing)** — not at the spec stage. Use `scripts/commit-module.sh "<message>"`. Pushing is done by the user from their Mac. Remote: https://github.com/vjrtrm/student-information-system

## Token-saving habits
- Prefer the small per-module `.md` over the `.docx`.
- Delegate broad searches/verification to the subagents in `.claude/agents/` (they keep their own context out of the main thread).
- Don't re-read a file you just wrote; the tools error on failure.
