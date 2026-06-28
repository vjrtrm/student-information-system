# SIS — Module 10: Field Management
## Stage 1: Requirements (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 10 of 12 — Field Management
**Document stage:** **Requirements (this document)** → _Design_ → _Tasks_
**Version:** 0.2 (Revised) · June 2026 · **Status:** Awaiting review & approval
**Builds on:** Authentication (M1), Master Data (M2), Student Information Form (M5)

---

## 1. Purpose & objectives

The Student Information Form (M5) was built with fixed mandatory/optional rules applied uniformly across all departments. Module 10 gives Institution Admins the ability to customise those rules per department — marking individual fields as Required, Optional, or Hidden — so that departments with different intake requirements (UG vs PG, different programmes) can tailor the form without code changes.

Objectives:

- Allow Institution Admin to configure, per department, whether each of the ~95 student form fields is **Required**, **Optional**, or **Hidden**.
- Allow Institution Admin to define institution-wide defaults that apply to all departments unless overridden.
- Allow Institution Admin to override field settings for a specific department, inheriting defaults for fields not explicitly overridden.
- Allow Institution Admin to create **custom fields** — entirely new fields beyond the ~95 defined in M5 — which appear in the student form and are stored separately from the fixed schema.
- Ensure the student form (M5) respects the active field configuration and renders custom fields for the student's department.
- Ensure staff read-only views and RTC forms (M6) also respect Hidden fields and display custom fields.
- Track all field configuration and custom field changes in the audit log.

---

## 2. In scope

### 2.1 Field registry

- A **field registry** enumerates all configurable student form fields by `field_key` (matching the column or JSON key used in `student_profiles`). Each entry carries: field key, human-readable label, section name, default behaviour (Required / Optional / Hidden), and whether the field is **locked** (cannot be changed — e.g. `first_name`, `last_name`, `dob`, `mobile` are identity fields set during onboarding and always read-only in the form regardless of field config).
- The registry is seeded from a PHP constant array (not a DB table) — field keys are code, not data.
- ~95 configurable fields covering all six form sections defined in M5 §2.1.

### 2.2 Institution-wide default configuration

- Institution Admin can set a default `field_mode` (Required / Optional / Hidden) for any configurable field.
- Defaults stored in a `field_configs` table with `department_id = NULL`.
- Fields not explicitly configured fall back to the hard-coded default defined in the registry.

### 2.3 Department-level overrides

- Institution Admin can override field configuration for a specific department — selecting Required, Optional, or Hidden for any configurable field, or "Use Default" to inherit the institution-wide setting.
- Overrides stored in `field_configs` with the target `department_id`.
- Effective configuration = department override if present, else institution default if present, else registry hard-coded default.
- Department Admin has read-only visibility of their own department's effective field configuration (cannot edit).

### 2.4 Configuration UI

- **Institution-wide defaults page** (`/field-config`) — grid or table of all fields grouped by section; each row: Field Label, Section, Current Default, Edit control (dropdown: Required / Optional / Hidden / [registry default]).
- **Department overrides page** (`/field-config/{deptId}`) — same grid; each row additionally shows the Institution Default and allows "Use Default" as a choice.
- Changes submitted as a single bulk save per page (one form POST for the whole section or whole page) — not field-by-field AJAX in v1.
- A **"Reset to defaults"** button per department clears all overrides for that department, reverting to institution-wide defaults.
- Institution Admin can navigate between department configs via a department selector dropdown.

### 2.5 Effect on student form (M5 integration)

- When a student loads their form, the active field configuration for their department is resolved and applied:
  - **Hidden** fields are not rendered and not validated.
  - **Required** fields are validated on save/submit; empty required fields block submission.
  - **Optional** fields are rendered but not required for submission.
- The existing hard-coded required/optional logic in M5's form renderer is replaced by a lookup against the resolved field config.
- Locked fields (identity fields pre-filled from onboarding) remain read-only regardless of field config.
- Document upload fields can be Hidden or Optional but not Required in v1 (to avoid upload-blocking submission for departments that don't need certain documents).

### 2.6 Effect on staff views and RTC

- Staff read-only form view (M5 staffView) hides Hidden fields.
- RTC creation form (M6) does not show Hidden fields as options for change requests.
- Staff approval view (M6 rtc_detail) does not show Hidden fields in the comparison table.

### 2.7 Locked (non-configurable) fields

The following fields are **locked** — always present, always validated by their M5 rules, and never configurable via Field Management. They are excluded from the field config UI:

Identity/onboarding fields (read-only in form): `first_name`, `last_name`, `dob`, `mobile`, `gender`, `programme_level`, `academic_year_id`, `class_id`, `section_id`, `admission_date`, `department_id`.

Always-required form fields: `blood_group`, `nationality`, `passport_photo_path`, `perm_address1`, `perm_city`, `perm_state_id`, `perm_pincode`, `father_name`, `qual_sslc`, `admission_type`.

---

### 2.8 Custom field creation

Institution Admin can define entirely new fields that do not exist in the `student_profiles` schema. Custom fields are stored in a `custom_fields` table and their values stored in a `student_custom_data` EAV table (student_id, custom_field_id, value).

**Custom field properties:**
- **Label** (required) — human-readable display name shown on the student form.
- **Field type** — one of: `text` (single-line), `textarea` (multi-line), `number`, `date`, `select` (dropdown from a defined list of options).
- **Section** — which form section the field appears in (one of the six M5 sections). Custom fields appear at the end of their assigned section, after all built-in fields.
- **Scope** — `institution` (appears for all departments) or `department` (appears only for a specific department).
- **Mode** — Required / Optional / Hidden (same three states as built-in fields; can be further overridden per department via field_configs).
- **Status** — Active / Inactive. Inactive fields are hidden from the form and not validated; existing saved values preserved.
- **Options** (for `select` type only) — a comma-separated or line-separated list of option values defined at creation; stored as JSON in `custom_fields.options`.

**Custom field management UI (`/field-config/custom`):**
- List of all custom fields (label, type, section, scope, mode, status) with Edit and Deactivate actions.
- "Add Custom Field" form: label, type, section, scope (institution/department + dept selector), mode, options (shown for select type only).
- Edit: can change label, mode, status, and options. Cannot change field_type after creation (changing type would invalidate saved data).
- Deactivate: sets status=inactive; field hidden from form; data preserved.
- No hard-delete of custom fields in v1 (data integrity).

**Student form integration:**
- Active custom fields for the student's department are appended to their assigned section when the student opens the form.
- Values saved to `student_custom_data`; loaded and displayed alongside built-in fields.
- Custom fields participate in the form's completion percentage calculation and submission validation based on their mode (Required/Optional).

**Staff & RTC integration:**
- Staff read-only view (M5 staffView) shows custom field values below the built-in fields for each section.
- RTC creation form (M6) includes active custom fields as selectable fields for change requests; values stored in `change_requests.proposed_changes` JSON alongside built-in fields.
- Staff approval view (M6 rtc_detail) shows custom field changes in the comparison table.

**Export integration (M11):**
- Custom field values included in the student data grid and Excel export as additional columns.

---

## 3. Out of scope (this module)

- Field ordering / reordering — built-in field order within sections remains fixed; custom fields always appear at the end of their section.
- Programme-level or class-level overrides — department is the lowest granularity in v1.
- Student-visible explanations or help text per field — tooltip/help text configuration.
- Conditional visibility rules (e.g. "show field X only when field Y = value Z") — the existing M5 conditional rules (disability_nature, communication address, scholarship fields) remain hard-coded.
- Bulk import/export of field configurations.
- Field Management access for Department Admin (read-only view only, no edits).

---

## 4. Roles involved

| Role | Access |
|------|--------|
| Student | None — field config is invisible; form simply renders accordingly |
| Staff | None |
| Department Admin | Read-only: view effective field config for own department |
| Institution Admin | Full CRUD on institution-wide defaults, department overrides, and custom fields |

---

## 5. Assumptions & dependencies

- All M5 `student_profiles` columns exist; `field_key` values in the registry map 1-to-1 to these column names.
- M5's form renderer currently uses a hard-coded PHP array of required fields. This module introduces a `FieldConfig` helper that replaces that array with a dynamic lookup. M5 views and the form controller call `FieldConfig::resolve(int $deptId): array` which returns a merged map of `[field_key => 'required'|'optional'|'hidden']` covering both built-in and active custom fields.
- Two new tables: `field_configs` (built-in field overrides) and `custom_fields` (custom field definitions). One additional table: `student_custom_data` (EAV: student_id, custom_field_id, value TEXT).
- Custom field `field_key` is auto-generated as `custom_{id}` after INSERT, ensuring no collision with built-in keys.
- `MasterAuditLogger` used for all config and custom field saves.
- Changes take effect immediately on save — no publish/draft workflow.
- Existing submitted student forms are not retroactively revalidated when field config changes.
- Deactivating a custom field preserves all saved `student_custom_data` values.

---

## 6. Epics & user stories

### Epic A — Institution-wide defaults

**A1. Institution Admin sets institution-wide field defaults**
As an institution admin, I want to set default Required/Optional/Hidden settings for each student form field so that all departments share a sensible baseline without needing individual configuration.

Acceptance criteria:
- Given I visit `/field-config`, then I see all configurable fields grouped by section with their current default mode.
- Given I change a field from Optional to Required and save, then all departments that have no override for that field immediately treat it as Required.
- Given I change a field to Hidden and save, then the field disappears from the student form for all departments with no override.
- Given a locked field (e.g. `passport_photo_path`), then it does not appear in the configuration UI.

**A2. Configuration persists and is immediately effective**
As an institution admin, I want configuration changes to take effect immediately so that students filling the form see the updated requirements without a system restart.

Acceptance criteria:
- Given I save a field config change, when a student in the affected department opens their form, then the new field mode is in effect.
- Given I set a field to Hidden, when a student whose form is in progress opens the form, then that field is not shown and its saved value is not cleared (data preserved but field hidden).

### Epic B — Department overrides

**B1. Institution Admin overrides config for a specific department**
As an institution admin, I want to override field settings for individual departments so that PG departments can require fields that UG departments leave optional.

Acceptance criteria:
- Given I visit `/field-config/{deptId}`, then I see all fields with three columns: Field, Institution Default, Department Setting (dropdown: Use Default / Required / Optional / Hidden).
- Given I set a field to "Required" for Department A and save, then students in Department A must fill that field; students in Department B (no override) still follow the institution default.
- Given I click "Reset to Defaults" for a department, then all overrides for that department are deleted and the department inherits institution-wide defaults.

**B2. Department Admin views their effective field configuration**
As a department admin, I want to see the effective field configuration for my department so that I can understand what my students are required to fill.

Acceptance criteria:
- Given I visit `/field-config/my-dept` (redirects to own dept), then I see the effective configuration (merged institution defaults + department overrides) as read-only.
- Given a field has a department override, then it is shown with a "Dept override" indicator.
- Given a field uses the institution default, then it is shown with a "Institution default" indicator.

### Epic C — Form integration

**C1. Student form respects active field configuration**
As a student, I want the form to show only the fields relevant to my department so that I am not confused by fields that do not apply to my programme.

Acceptance criteria:
- Given a field is Hidden for my department, when I open my form, then that field is not rendered.
- Given a field is Required for my department, when I try to submit without filling it, then submission is blocked with a validation error for that field.
- Given a field is Optional for my department, when I leave it blank and submit, then submission succeeds.
- Given a field's data was saved before it was Hidden, then the saved value is preserved in the database but not shown in the form.

### Epic D — Custom fields

**D1. Institution Admin creates a custom field**
As an institution admin, I want to create a new form field that does not exist in the standard form so that departments can collect institution-specific data without requiring a code change.

Acceptance criteria:
- Given I visit `/field-config/custom` and click "Add Custom Field", when I fill in label, type, section, scope, and mode and submit, then the field is created and immediately active.
- Given I select type `select`, then I see an options input; the field is not saved without at least two options defined.
- Given the label is blank, then the form shows a validation error and no field is created.
- Given I create a field with scope `department`, then I must select a specific department; the field appears only for students in that department.
- Given I create a field with scope `institution`, then it appears for all departments.

**D2. Custom field appears on the student form**
As a student, I want to see and fill any custom fields created for my department so that I can provide all required information.

Acceptance criteria:
- Given an active institution-scoped custom field exists, when I open my form, then the field appears at the end of its assigned section.
- Given an active department-scoped custom field exists for my department, when I open my form, then it appears; students in other departments do not see it.
- Given a custom field is Required and I leave it blank, then submission is blocked with a validation error.
- Given a custom field is of type `select`, then it renders as a dropdown with the defined options.
- Given I save a value for a custom field and return later, then my saved value is shown.

**D3. Institution Admin edits or deactivates a custom field**
As an institution admin, I want to edit a custom field's label or options and deactivate fields that are no longer needed so that the form stays current.

Acceptance criteria:
- Given I edit a custom field's label, then the updated label appears on the student form immediately.
- Given I add a new option to a select field, then students see the new option on their next form load.
- Given I deactivate a custom field, then it no longer appears on the student form; existing saved values are preserved in the database.
- Given I try to change a field's type after creation, then the type field is read-only (displayed, not editable).

---

## 7. Non-functional requirements (module-relevant)

- **Performance** — `FieldConfig::resolve(int $deptId)` result cached in a static PHP variable for the duration of the request (one DB query per request, not per field); custom fields fetched in the same query.
- **No retroactive revalidation** — changing a field to Required does not invalidate already-submitted forms.
- **Data preservation** — setting a field to Hidden or deactivating a custom field never deletes existing saved data.
- **Custom field key stability** — `field_key` is set once on creation (`custom_{id}`) and never changed, so RTC proposed_changes JSON references remain valid.
- **Audit** — every bulk save to `field_configs` and every custom field create/edit/deactivate logged via `MasterAuditLogger`.
- **CSRF** — all POST forms CSRF-protected.
- **Consistency** — a field cannot be both in the locked list and the configurable list; the registry enforces this at definition time. Custom field keys are prefixed `custom_` ensuring no collision with built-in keys.

---

## 8. Open questions

| # | Question | Owner | Resolution needed by |
|---|----------|-------|---------------------|
| 1 | Should document upload fields (e.g. `qual_sslc_doc_path`) be configurable as Required, or only Optional/Hidden? The M5 spec marks them optional; some departments may want to enforce them. | Product | Before Design |
| 2 | When Institution Admin saves the institution-wide defaults page, should the save apply to all fields at once (bulk POST), or only the changed fields? Bulk is simpler; changed-only is more efficient for large field lists. | Product | Before Design |
| 3 | Should there be a "Copy config from department X to department Y" convenience action, or is manual configuration per department sufficient for v1? | Product | Before Design |
| 4 | Should the field config UI show all ~95 fields on a single scrollable page grouped by section, or paginate section by section? | Product | Before Design |
| 5 | For department-scoped custom fields: should the field be visible to Institution Admin in all department views, or only in the specific department's view? | Product | Before Design |
| 6 | Should custom field values be included in the RTC flow as first-class fields (selectable in RTC creation form), or treated as supplementary data outside the RTC process? | Product | Before Design |

---

## 9. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, proceed to Design.
