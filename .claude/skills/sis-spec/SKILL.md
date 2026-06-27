---
name: sis-spec
description: Author SIS module specifications using the project's gated workflow. Use whenever creating or updating a Requirements, Design, or Tasks document for any SIS module. Provides the fixed templates and the review/approval rules so structure and conventions are consistent and cheap to produce.
---

# sis-spec — gated module specification

The SIS is built module by module. Each module is specified in three approval-gated stages:

1. **Requirements** — narrative epics -> user stories -> acceptance criteria. WHAT/WHY only.
2. **Design** — concrete, buildable: architecture, data model, flows, validation, security, traceability. HOW.
3. **Tasks** — ID'd work breakdown with estimates, dependencies, "done when", test tasks, Definition of Done.

## Rules
- Produce only the current stage. Present it, then STOP and ask the user for review + approval. Never start the next stage before approval.
- Use the templates in `templates/` — fill, don't reinvent. File path: `docs/module-<NN>-<name>/SIS_M<N>_<Name>_<Stage>.md`.
- Honour locked decisions in `CLAUDE.md`. Undecided things go under "Open questions", never silently assumed.
- After authoring, run the `spec-verifier` subagent for traceability/consistency before showing the user.
- After a module's three stages are approved (and any code done), commit/push via `scripts/commit-module.sh`.

## Templates
- `templates/requirements.md`
- `templates/design.md`
- `templates/tasks.md`
