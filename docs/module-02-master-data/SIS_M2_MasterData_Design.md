# SIS — Module 2: Master Data & Department Management
## Stage 2: Design (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 2 of 12 — Master Data & Department Management
**Document stage:** Requirements ✅ → **Design (this document)** → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M2_MasterData_Requirements.md` (Epics A–D)

---

## 1. Design goals

Turn the approved requirements into a buildable design on the SIS stack (PHP 8 MVC, MySQL 5.7, PDO, Bootstrap 5, PhpSpreadsheet), reusing Module 1's foundations (Db, Validator, Csrf, RoleMiddleware, AccessControl). Deliver department management, a shared State→District→Taluk hierarchy with dependent dropdowns and bulk import, generic admin-managed option lists, soft-delete with referential safety, and an audit trail.

## 2. Resolved design decisions (from requirements' open questions)

| # | Question | Decision (default — flag to change) |
|---|----------|-------------------------------------|
| 1 | Delegate any lists to Dept Admins? | **No** — all master data is Institution-Admin-only in v1. |
| 2 | Department code format | Admin-typed, **2–10 chars, uppercase A–Z/0–9**, unique, normalised to uppercase on save. |
| 3 | Geographic import shape | **Single denormalised sheet**: columns `State, District, Taluk`; system derives the hierarchy (upserts each level). |
| 4 | Seed defaults | **Yes, optional** — a seed script for Tamil Nadu geography; install can run it or skip. |

## 3. Component architecture (MVC)

```
Controllers/
  DepartmentController.php   // CRUD + deactivate/reactivate
  GeographyController.php    // states/districts/taluks CRUD, AJAX children, bulk import
  OptionListController.php   // manage lists + values
  LookupController.php       // read-only dependent-dropdown endpoints for forms
Models/
  Department.php (extend M1) State.php  District.php  Taluk.php
  OptionList.php  OptionValue.php
Helpers/
  SpreadsheetImport.php  // PhpSpreadsheet read + row validation (reused by M3 later)
  ReferenceCheck.php     // is a master row in use? (registry of referencing table/columns)
  Audit.php              // general audit_log writer (shared, cross-cutting)
Middleware/
  (reuse) AuthMiddleware + RoleMiddleware(['institution_admin'])
Views/
  admin/master-data/ (index, departments, geography, option-list)
```

All management routes run `AuthMiddleware → RoleMiddleware(['institution_admin'])`. Read-only lookup endpoints require only `AuthMiddleware` (any signed-in user filling a form).

## 4. Data model

Departments already exist (minimal) from Module 1; this module adds geography, option lists, and a shared audit log.

**states**

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| name | VARCHAR(100) | |
| status | ENUM('active','inactive') default 'active' | |
| created_at / updated_at | TIMESTAMP | |
| | | UNIQUE(name) |

**districts**

| id | INT PK AI |
| state_id | INT FK→states.id |
| name | VARCHAR(100) |
| status | ENUM('active','inactive') |
| | UNIQUE(state_id, name); INDEX(state_id) |

**taluks**

| id | INT PK AI |
| district_id | INT FK→districts.id |
| name | VARCHAR(100) |
| status | ENUM('active','inactive') |
| | UNIQUE(district_id, name); INDEX(district_id) |

**option_lists** (registry of admin-managed lists)

| id | INT PK AI |
| list_key | VARCHAR(50) UNIQUE  — stable key, e.g. `community`, `religion`, `blood_group`, `sslc_board` |
| label | VARCHAR(100) — human name |

**option_values**

| id | INT PK AI |
| list_id | INT FK→option_lists.id |
| value | VARCHAR(100) — stored value |
| label | VARCHAR(150) — displayed text |
| sort_order | INT default 0 |
| status | ENUM('active','inactive') |
| | UNIQUE(list_id, value); INDEX(list_id, status, sort_order) |

**audit_log** (shared, cross-cutting — introduced here, used by later modules)

| id | INT PK AI |
| actor_id | INT NULL — users.id |
| action | VARCHAR(50) — create/update/deactivate/reactivate/import |
| entity | VARCHAR(50) — department/state/district/taluk/option_value |
| entity_id | INT NULL |
| details | TEXT NULL — JSON of before→after / summary |
| created_at | TIMESTAMP |
| | INDEX(entity, entity_id) |

Migrations (M2): `007_create_states`, `008_create_districts`, `009_create_taluks`, `010_create_option_lists`, `011_create_option_values`, `012_create_audit_log`, `013_seed_option_lists` (registers the known list keys).

## 5. Dependent-dropdown mechanics (Epic B2)

- Read-only JSON endpoints: `GET /lookup/districts?state_id=` and `GET /lookup/taluks?district_id=`, returning only **active** children of the given parent, ordered by name.
- Client (vanilla JS): on State change → fetch districts, repopulate + clear Taluk; on District change → fetch taluks. Changing a parent resets the child selection.
- Server still validates the final submitted state/district/taluk triplet for consistency (taluk's district == selected district, etc.) — never trust the client.

## 6. Bulk geographic import (Epic B3)

Flow (Institution Admin → `GeographyController::import`):

1. Upload `.xlsx`/`.csv` with columns `State, District, Taluk` (header row required). Validated as PDF-not-applicable; size/type checked.
2. `SpreadsheetImport` parses rows; each row validated: all three non-empty, trimmed; collect per-row errors (row number + reason).
3. **Preview** screen shows counts: new states/districts/taluks to create, rows to skip (already exist), invalid rows (downloadable error report).
4. On confirm, idempotent **upsert**: ensure state by name → district by (state,name) → taluk by (district,name). Existing entries are reused, not duplicated.
5. Result summary + an `audit_log` `import` entry (file name, totals).

Seed: `database/seeds/geography_tn.php` loads a default Tamil Nadu set via the same upsert path (idempotent, safe to re-run).

## 7. Referential safety & soft-delete (Epic A3, D1)

- `ReferenceCheck` holds a registry of where each master entity is referenced, e.g.
  - department → `students.department_id`, `users.department_id`
  - state → `districts.state_id` (+ student address table once Module 5 exists)
  - district → `taluks.district_id` (+ student address)
  - taluk / option_value → student profile columns (Module 5)
- `ReferenceCheck::inUse(entity, id)` returns true if any referencing row exists (counts via prepared statements; tables that don't exist yet are skipped).
- **Delete** is blocked when `inUse` is true → UI offers **Deactivate** instead. When not in use, hard-delete is allowed (or admin may still deactivate).
- Deactivated rows are excluded from new-selection queries (`WHERE status='active'`) but render normally on existing records.
- **Code-change caution (A2):** before saving a department code change where `ReferenceCheck::inUse('department', id)` is true, the controller requires a confirmation flag (`confirm_code_change=1`); existing enrolment numbers are never rewritten.

## 8. RBAC & validation

- Management routes: `institution_admin` only (RoleMiddleware) → else 403.
- Lookup routes: any authenticated user.
- All writes carry a CSRF token; server-side validation:
  - Department: name required; code matches `/^[A-Z0-9]{2,10}$/` (uppercased), unique; level ∈ {UG,PG}.
  - Geography: name required; parent must exist and be active; uniqueness within parent.
  - Option value: value + label required; unique within list; sort_order integer.

## 9. Screen behaviour

| Screen | Route | Elements |
|--------|-------|----------|
| Master data home | `/admin/master-data` | cards/links to Departments, Geography, Option Lists |
| Departments | `/admin/departments` | searchable table; add/edit modal; deactivate/reactivate; caution dialog on in-use code change |
| Geography | `/admin/geography` | tabs States/Districts/Taluks; parent selectors; bulk-import button → preview → confirm |
| Option list | `/admin/option-lists/{key}` | values table with inline add/edit, sort order, activate/deactivate |

All tables paginate (20/page) and are searchable; inactive rows shown with a muted badge and a Reactivate action.

## 10. Configuration

`masterdata.seed_geography` (bool, default false — run TN seed on install) · import limits reuse the global 2 MB / type rules.

## 11. Edge cases

- Import row with blank parent (e.g. Taluk without District) → invalid, reported, others still import.
- Duplicate import rows → idempotent (no duplicates created).
- Deactivating a State → its districts/taluks remain but the State is hidden from new address entry (children handled independently; UI warns).
- Renaming an option value label → existing records keep the stored `value`; only display label changes.

## 12. Traceability (requirements → design)

| Requirement | Design |
|-------------|--------|
| A1–A3 departments | §4 (departments), §7 (soft-delete, code caution), §8 |
| B1 geography CRUD | §3, §4 (states/districts/taluks) |
| B2 dependent dropdowns | §5, §11 |
| B3 bulk import + seed | §6 |
| C1 option lists | §4 (option_lists/values), §9 |
| D1 referential safety | §7 |
| D2 audit | §4 (audit_log), §3 (Audit helper) |
| D3 access control | §3, §8 |

## 13. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, the next step is **Stage 3: Tasks** for Module 2 — a build-ready breakdown (migrations, models, controllers, import, lookups, views, tests) with estimates, dependencies and "done when", submitted for your review before implementation.
