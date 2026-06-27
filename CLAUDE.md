# CLAUDE.md — Student Information System (SIS)

> Auto-loaded each session. Keep it short. Its job is to stop Claude re-deriving context (= saved tokens). Read the per-module `.md` specs, **not** the large `.docx`, unless a binary/print artifact is explicitly needed.

## What this project is
College Student Information System. Web app. Roles: Student, Department Staff, Department Admin (one staff-admin per dept, full CRUD on its data), optional Institution Admin.

## Stack (fixed)
PHP 8.x · MVC · MySQL 5.x (confirm minor — note says 5.4) · Bootstrap 5 · PDO · PHPMailer/SMTP · PhpSpreadsheet · TCPDF. Avoid MySQL-8-only features.

## Locked product decisions
- Student login = mobile + DOB. Staff/admin = email + password (bcrypt).
- Enrolment no.: `<YY><U|P><DeptCode><serial>` e.g. `24UBCA041`, `26PMCA100`. Prepared in bulk by dept staff, **released after admin approval**; student sees serial until then.
- Submission/edit approval = **single approval** (dept staff primary; admin may also). Any one approval verifies.
- ~95 student fields. Blue colour-coded parent fields optional. Document uploads optional, PDF/image ≤ 2 MB. Passport photo image-only.
- No PII in email notifications (code/link only).
- Features: Request-to-Change, bulk upload + duplicate handling, promotion (June window), student data grid + Excel export, admin statistics cards, master data incl. Department/Taluk/District/State.

## Mandatory workflow (do not skip)
Every feature/module goes **Requirements → Design → Tasks**, each as a Markdown file, **each presented for the user's review + explicit approval before the next stage.** Never jump ahead. Use the `sis-spec` project skill templates (`.claude/skills/sis-spec/templates/`) so structure isn't regenerated from scratch.

## Module roadmap (run the 3-stage cycle in this order)
1. Authentication & Access Control  ← specs done
2. Master Data & Department Management
3. Student Onboarding (bulk upload)
4. Enrolment Number Generation & Approval
5. Student Information Form (dynamic fields, partial save, submit/lock)
6. Submission & Edit Approval (Request-to-Change)
7. Notifications
8. Dashboards, Statistics & Personalisation
9. Staff Management
10. Field Management
11. Student Data Grid & Export
12. Student Promotion

## File conventions
- Specs live at repo root as `SIS_M<N>_<Name>_<Requirements|Design|Tasks>.md`. `docs/` holds any other long-form docs.
- Master combined spec: `SIS_Specification.docx` (reference only; don't read unless necessary).

## Git rule
**Commit and push to `origin` after every module is completed.** Use `scripts/commit-module.sh "<message>"`. Remote: https://github.com/vjrtrm/student-information-system

## Token-saving habits
- Prefer the small per-module `.md` over the `.docx`.
- Delegate broad searches/verification to the subagents in `.claude/agents/` (they keep their own context out of the main thread).
- Don't re-read a file you just wrote; the tools error on failure.
