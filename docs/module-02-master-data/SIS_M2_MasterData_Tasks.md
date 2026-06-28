# SIS — Module 2: Master Data & Department Management
## Stage 3: Tasks (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 2 of 12 — Master Data & Department Management
**Document stage:** Requirements ✅ → Design ✅ → **Tasks (this document)**
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M2_MasterData_Design.md`

---

## 1. How to read this

Each task: ID, deliverable, estimate (ideal hours), dependencies, "done when". Estimates assume the Module 1 codebase/conventions are in place. P1 = required for the module to function; P2 = hardening/nice-to-have. Build order in §8.

---

## 2. Data layer

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M2-T01 | Migrations `007_states`, `008_districts`, `009_taluks` (FKs + unique-within-parent indexes) | 3 | — | P1 | Run on MySQL 5.7; hierarchy FKs + uniques present |
| M2-T02 | Migrations `010_option_lists`, `011_option_values` (unique(list_id,value), index) | 2 | — | P1 | Tables created per design §4 |
| M2-T03 | Migration `012_audit_log` (shared cross-cutting log) | 1 | — | P1 | Table + index(entity,entity_id) created |
| M2-T04 | Migration `013_seed_option_lists` — register known list_keys (community, religion, blood_group, sslc_board, hsc_board, hsc_group, medium, education, occupation, discover_source, choose_reason, institution_name, language, academic_year, class, section) | 2 | T02 | P1 | All list keys present after migrate |
| M2-T05 | (Optional) ALTER departments: add `updated_at` | 1 | — | P2 | Column added; backward compatible |

## 3. Models

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M2-T06 | `State`, `District`, `Taluk` models — active-only finders, children-by-parent, CRUD, deactivate/reactivate | 5 | T01 | P1 | Unit-tested children queries return only active rows |
| M2-T07 | `OptionList` + `OptionValue` models — list by key, values (ordered, active), CRUD | 4 | T02,T04 | P1 | valuesByKey() returns ordered active values |
| M2-T08 | Extend `Department` model — create/update (code normalise+validate), deactivate/reactivate, all-active | 3 | — | P1 | Code uppercased + uniqueness enforced |

## 4. Helpers

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M2-T09 | `Audit` helper → writes `audit_log` (actor, action, entity, entity_id, details JSON) | 2 | T03 | P1 | Every master write produces a record |
| M2-T10 | `ReferenceCheck` helper — registry of referencing table/columns; `inUse(entity,id)`; skips tables that don't exist yet | 4 | T01 | P1 | Returns true when a referencing row exists; safe when table absent |
| M2-T11 | `SpreadsheetImport` helper (PhpSpreadsheet) — read rows, trim, per-row validation, collect errors | 5 | — | P1 | Parses xlsx/csv; returns rows + error list (reused by M3) |

## 5. Controllers & endpoints

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M2-T12 | `DepartmentController` CRUD + deactivate/reactivate + in-use code-change caution (confirm flag) | 5 | T08,T09,T10 | P1 | Epic A1–A3 behaviours incl. caution + soft-delete |
| M2-T13 | `GeographyController` CRUD for states/districts/taluks (+ parent validation) | 5 | T06,T09,T10 | P1 | Cannot create child without valid active parent |
| M2-T14 | `GeographyController::import` — upload → preview (counts + error report) → confirm idempotent upsert + audit | 6 | T11,T06,T09 | P1 | Flow §6; valid rows imported, no duplicates, errors reported |
| M2-T15 | `LookupController` — `GET /lookup/districts?state_id`, `/lookup/taluks?district_id` (active children JSON, auth required) | 3 | T06 | P1 | Returns only active children of parent |
| M2-T16 | `OptionListController` — manage values per list_key (add/edit/sort/deactivate) | 4 | T07,T09 | P1 | Epic C1 behaviours; ordered, soft-delete |
| M2-T17 | Routes + `RoleMiddleware(['institution_admin'])` on management; `AuthMiddleware` on lookups | 2 | T12–T16 | P1 | Non-admin → 403; lookups need only login |

## 6. Views

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M2-T18 | Master-data home + Departments screen (table, add/edit, deactivate, caution dialog) | 5 | T12 | P1 | Searchable, paginated; caution modal works |
| M2-T19 | Geography screen (States/Districts/Taluks tabs, parent selectors, dependent dropdowns via lookup JS) | 5 | T13,T15 | P1 | Child dropdown filters by parent; resets on change |
| M2-T20 | Geography bulk-import UI (upload → preview → confirm + error download) | 4 | T14 | P1 | Preview counts + invalid-row report shown |
| M2-T21 | Option-list management screen (values table, inline add/edit, sort, activate/deactivate) | 4 | T16 | P1 | CRUD + reorder + soft-delete from UI |

## 7. Tests

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M2-T22 | Unit: Department code normalise/validate/uniqueness; deactivate vs in-use hard-delete | 4 | T08,T10 | P1 | All branches green |
| M2-T23 | Unit: geography parent linkage + active-only children; option list ordering/active | 4 | T06,T07 | P1 | Green |
| M2-T24 | Unit: `ReferenceCheck` (in-use true/false; missing table safe) | 3 | T10 | P1 | Green |
| M2-T25 | Integration: import — valid+invalid+duplicate rows → upsert counts + error report | 5 | T11,T14 | P1 | Idempotent; correct summary |
| M2-T26 | Integration: lookup endpoints return active children; RBAC 403 for non-admin on management routes | 4 | T15,T17 | P1 | Green |
| M2-T27 | Audit: each master write records an `audit_log` row | 2 | T09 | P2 | Spot-check passes |

## 8. Build order (critical path)

1. Data layer: T01 → T02 → T03 → T04 (T05 optional)
2. Helpers: T09, T10, T11 (parallel after data layer)
3. Models: T06, T07, T08
4. Controllers/endpoints: T12, T13, T15 → T14 → T16 → T17
5. Views: T18, T19 → T20, T21
6. Tests: T22–T27 (write unit alongside; integration at the end)

## 9. Estimate summary

| Group | Hours |
|-------|------:|
| Data layer (T01–T05) | 9 |
| Models (T06–T08) | 12 |
| Helpers (T09–T11) | 11 |
| Controllers (T12–T17) | 25 |
| Views (T18–T21) | 18 |
| Tests (T22–T27) | 22 |
| **Total** | **~97 ideal hours (~12–14 dev-days)** |

## 10. Definition of Done

- All P1 tasks complete; unit + integration tests green (run in a PHP 8 / MySQL 5.7 env).
- Departments, geography (with dependent dropdowns + bulk import), and option lists fully manageable by Institution Admin; non-admins blocked.
- Soft-delete + referential safety verified; in-use department-code change requires confirmation.
- Every write recorded in `audit_log`.
- Traceability holds: each Epic A–D criterion has a passing test.
- Local commit via `scripts/commit-module.sh` after tests pass; user pushes from Mac.

## 11. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, Module 2 is fully specified. Next would be implementation of Module 2 (in Claude Code, per the recommended split), or starting the cycle for Module 3 — Student Onboarding.
