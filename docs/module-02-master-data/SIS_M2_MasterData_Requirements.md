# SIS — Module 2: Master Data & Department Management
## Stage 1: Requirements (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 2 of 12 — Master Data & Department Management
**Document stage:** Requirements → _Design_ → _Tasks_ (this is Requirements; design follows after approval)
**Version:** 0.1 (Draft) · June 2026
**Status:** Awaiting review & approval
**Builds on:** Foundation (Module 0), Authentication (Module 1)

---

## 1. Purpose & objectives

Master data is the set of reusable reference lists that the rest of the SIS depends on — departments, the State/District/Taluk hierarchy, and the option lists that populate dropdowns throughout the student form. This module gives administrators a single, governed place to create and maintain that data so every other module (onboarding, enrolment numbers, the student form, dashboards) draws from consistent, controlled values.

Objectives:

- Manage **departments** (the backbone of enrolment numbering, staff scoping and notifications).
- Manage the linked **State → District → Taluk** hierarchy that drives dependent address dropdowns.
- Manage all other **configurable option lists** used by the student form.
- Keep data integrity through **soft-delete** (deactivate, never silently break existing records) and **caution prompts** on changes that affect in-use values.
- Record every change in an **audit trail**.

## 2. In scope

- CRUD + deactivate for departments, states, districts, taluks, and option lists.
- Linkage between geographic levels (district→state, taluk→district) and dependent dropdown behaviour.
- Bulk import of geographic data (states/districts/taluks) and seedable defaults.
- Caution/confirmation on edits to in-use codes/values; prevention of hard-delete when referenced.
- Audit logging of all master-data changes.

## 3. Out of scope (this module)

- Student/staff records themselves (Modules 3, 5, 9).
- The dynamic student form field schema — adding/removing form fields (Module 10). This module manages the *values* in lists, not the *definition* of which fields exist.
- Enrolment number generation logic (Module 4) — this module only supplies department code + level.

## 4. Roles involved

| Role | Capability in this module |
|------|---------------------------|
| Institution Admin | Full CRUD on all master data (institution-wide): departments, geography, all option lists |
| Department Admin / Staff | **Consume** master data (read) in their workflows; cannot edit institution-wide masters |
| Student | Consumes resulting dropdowns only (no access to management) |

> Master data is institution-wide reference data, so management is an Institution Admin capability. (Open question 1 revisits whether any list should be delegated to Department Admins.)

## 5. Assumptions & dependencies

- Authentication & RBAC (Module 1) is in place; only `institution_admin` reaches master-data management routes.
- Departments created here are later consumed by onboarding (M3), enrolment numbers (M4) and scoping.
- Geographic and option-list values feed the student form (M5).

---

## 6. Epics & user stories

### Epic A — Department management

**A1. Add a department**
As an Institution Admin, I want to add a department with a name, unique code and programme level, so that students can be assigned to it and enrolment numbers can be formed.

Acceptance criteria:

- Given a name, a unique code (e.g. BCA) and a level (UG or PG), when I save, then the department is created and available for selection elsewhere.
- Given a code that already exists, when I save, then I get a validation error and nothing is created.
- The level is restricted to UG or PG (it supplies the U/P segment of the enrolment number).

**A2. Update a department (with caution on in-use code)**
As an Institution Admin, I want to edit a department's name or level, and be warned before changing a code that is already in use, so that I don't unintentionally affect how new enrolment numbers are formed.

Acceptance criteria:

- Given a department not yet referenced, when I edit any field, then the change saves normally.
- Given a department already referenced by students/enrolment numbers, when I change its code, then I must confirm a caution prompt before saving; existing approved enrolment numbers are never silently rewritten.
- Code uniqueness is re-validated on update.

**A3. Deactivate a department (soft-delete)**
As an Institution Admin, I want to deactivate a department instead of deleting it, so that historical student and staff links remain intact.

Acceptance criteria:

- Given a department in use, when I attempt to delete it, then hard-delete is prevented and I am offered deactivation instead.
- Given a deactivated department, then it no longer appears in new-selection dropdowns but remains valid on existing records.
- Deactivation is reversible (can be reactivated).

### Epic B — Geographic hierarchy (State → District → Taluk)

**B1. Manage states, districts, taluks**
As an Institution Admin, I want to add, edit and deactivate states, districts and taluks, so that address dropdowns offer correct, controlled values.

Acceptance criteria:

- I can CRUD each level; each entry has a name and active/inactive status.
- A district must be linked to a state; a taluk must be linked to a district.
- I cannot create a district/taluk without selecting its valid parent.

**B2. Dependent dropdowns**
As a user filling an address, I want the District list to reflect the chosen State, and the Taluk list to reflect the chosen District, so that only relevant options appear.

Acceptance criteria:

- Given a selected State, when I open the District dropdown, then only districts linked to that state are shown.
- Given a selected District, when I open the Taluk dropdown, then only taluks linked to that district are shown.
- Changing the parent resets the dependent child selection.

**B3. Bulk import geographic data**
As an Institution Admin, I want to import states/districts/taluks in bulk from a spreadsheet (and have seedable defaults), so that I don't enter hundreds of rows by hand.

Acceptance criteria:

- Given a correctly formatted file (with parent references), when I import, then valid rows are created and a summary of created/skipped/invalid rows is shown.
- Invalid rows (e.g. unknown parent, duplicate) are reported with row number and reason; valid rows still import.

### Epic C — Configurable option lists

**C1. Manage option lists**
As an Institution Admin, I want to manage the option lists used across the student form, so that dropdown choices stay consistent and correct.

Acceptance criteria:

- I can CRUD entries (value + display label + sort order + status) for each list: Academic Year, Class, Section, Community, Religion, Blood Group, SSLC Board, HSC Board/Group, Medium of Study, Father/Mother Education, Father/Mother Occupation, "How did you discover this college", "Reasons for choosing this college", Institution Name, Language.
- Each list renders in its configured sort order; inactive entries are hidden from new selections but retained on existing records.
- Adding a value makes it immediately available to the relevant form field.

### Epic D — Integrity, audit & access

**D1. Referential safety**
As the institution, I want master entries that are in use to be protected from destructive changes, so that existing student data stays valid.

Acceptance criteria:

- Hard-delete is blocked for any entry referenced by existing records; deactivate is offered instead.
- Deactivated values continue to display correctly on records that already use them.

**D2. Audit trail**
As an Institution Admin, I want every master-data change recorded, so that changes can be reviewed.

Acceptance criteria:

- Create, update, deactivate/reactivate and bulk-import actions are logged with actor, entity, before→after (where applicable) and timestamp.

**D3. Access control**
As the institution, I want only authorised admins to manage master data, so that reference data isn't changed by unauthorised users.

Acceptance criteria:

- Only `institution_admin` can reach master-data management routes; others receive 403.
- All write actions carry a CSRF token and are validated server-side.

---

## 7. Non-functional requirements (module-relevant)

- **Usability:** management screens are searchable and paginated; geographic screens make the parent linkage obvious.
- **Performance:** dependent dropdowns load quickly (indexed lookups by parent id).
- **Security:** PDO prepared statements; CSRF on all writes; server-side validation; institution-admin-only.
- **Consistency:** soft-delete everywhere; codes/values validated for uniqueness within their list.

## 8. Open questions

1. Should any option lists be delegated to Department Admins, or is all master data Institution-Admin-only (current assumption)?
2. Are department codes free-text (admin types e.g. "BCA") or chosen from a controlled list? Any length/format rule?
3. For bulk geographic import — confirm the expected file columns (e.g. State, District, Taluk in one sheet vs three sheets).
4. Should we ship seed data for a default State/District/Taluk set (e.g. Tamil Nadu) out of the box?

## 9. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, the next step is **Stage 2: Design** for Module 2 (data model for departments + geography + option lists, dependent-dropdown mechanics, import handling, screens, validation), submitted for your review before any Task breakdown.
