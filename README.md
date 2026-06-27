# Student Information System (SIS)

College SIS built module-by-module using an approval-gated spec workflow:
**Requirements -> Design -> Tasks** (each reviewed & approved before the next).

## Layout
- `SIS_M<N>_<Name>_<Stage>.md` — per-module specs (root + mirrored in `docs/`).
- `SIS_Specification.docx` — combined master reference.
- `CLAUDE.md` — project context & locked decisions (auto-loaded by Claude Code).
- `.claude/` — project skill (`sis-spec`), subagents (`spec-author`, `spec-verifier`, `code-reviewer`), hooks & settings.
- `scripts/commit-module.sh` — commit/push helper, run after each module completes.

## Workflow
See `CLAUDE.md`. Stack: PHP 8.x · MySQL 5.x · Bootstrap 5 · PDO · PHPMailer.

## Modules
1. Authentication & Access Control (specs complete)
2. Master Data & Department Management
3. Student Onboarding · 4. Enrolment Numbers · 5. Student Form · 6. Edit Approval
7. Notifications · 8. Dashboards/Statistics · 9. Staff Mgmt · 10. Field Mgmt
11. Data Grid & Export · 12. Promotion
