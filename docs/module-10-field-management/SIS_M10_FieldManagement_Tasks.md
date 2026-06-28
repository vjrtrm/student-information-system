# SIS — Module 10: Field Management
## Stage 3: Tasks (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 10 of 12 — Field Management
**Document stage:** Requirements ✅ → Design ✅ → **Tasks (this document)**
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M10_FieldManagement_Design.md`

---

## 1. How to read this

Each task: ID, deliverable, estimate (ideal hours), dependencies, priority, "done when". P1 = required for the module to function; P2 = hardening/polish. Estimates assume M1–M9 codebase in place. Build order in §10.

---

## 2. Migrations

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M10-T01 | `025_create_field_configs.sql` — `CREATE TABLE field_configs (id INT UNSIGNED AUTO_INCREMENT PK, field_key VARCHAR(60) NOT NULL, department_id INT UNSIGNED NOT NULL DEFAULT 0, mode ENUM('required','optional','hidden') NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE KEY uq_field_dept (field_key, department_id)) ENGINE=InnoDB` | 1 | — | P1 | Table created in MySQL 5.7; UNIQUE index enforced |
| M10-T02 | `026_create_custom_fields.sql` — `CREATE TABLE custom_fields (id INT UNSIGNED AUTO_INCREMENT PK, field_key VARCHAR(20) NOT NULL DEFAULT '', label VARCHAR(150) NOT NULL, field_type ENUM('text','textarea','number','date','select') NOT NULL, section VARCHAR(60) NOT NULL, scope ENUM('institution','department') NOT NULL DEFAULT 'institution', department_id INT UNSIGNED NULL, mode ENUM('required','optional','hidden') NOT NULL DEFAULT 'optional', options JSON NULL, status ENUM('active','inactive') NOT NULL DEFAULT 'active', sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0, created_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, UNIQUE KEY uq_field_key (field_key)) ENGINE=InnoDB` | 1 | — | P1 | Table created; field_key unique index enforced |
| M10-T03 | `027_create_student_custom_data.sql` — `CREATE TABLE student_custom_data (id INT UNSIGNED AUTO_INCREMENT PK, student_id INT UNSIGNED NOT NULL, custom_field_id INT UNSIGNED NOT NULL, value TEXT NOT NULL DEFAULT '', created_at DATETIME NOT NULL, updated_at DATETIME NULL, UNIQUE KEY uq_student_field (student_id, custom_field_id), KEY idx_student (student_id), FK student_id → students(id), FK custom_field_id → custom_fields(id)) ENGINE=InnoDB` | 1 | M10-T02 | P1 | Table created; FKs enforced |

---

## 3. Helpers

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M10-T04 | `FieldRegistry` (`app/Helpers/FieldRegistry.php`) — static class; `SECTIONS: array` (ordered list of 6 section name strings matching M5 form sections); `ALL_FIELDS: array` (map field_key → [label, section, default_mode, locked] covering all ~95 student_profiles columns); `LOCKED_KEYS: array` (21 identity + always-required keys excluded from config UI); `configurableFields(): array` (ALL_FIELDS filtered to locked=false); `isCustomKey(string $key): bool` (str_starts_with 'custom_'). No DB access. | 4 | — | P1 | All ~95 built-in field keys present; locked keys absent from configurableFields(); unit tested |
| M10-T05 | `FieldConfig` (`app/Helpers/FieldConfig.php`) — static class; `resolve(int $deptId): array` — (1) start from registry defaults, (2) apply field_configs WHERE department_id=0, (3) apply field_configs WHERE department_id=$deptId, (4) append active custom_fields WHERE status='active' AND (scope='institution' OR department_id=$deptId) as custom_{id} keys; result cached in `private static array $cache[$deptId]`; `resolveCustomFields(int $deptId): array` — returns full rows (id, label, field_type, section, options) for active custom fields for the dept; `clearCache(): void` — resets static cache (used in tests and after every write) | 4 | M10-T01, M10-T02, M10-T04 | P1 | resolve() returns correct merged map; dept override takes precedence over institution default which takes precedence over registry default; custom keys present; cache returns memoised result on second call; unit tested |

---

## 4. Model

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M10-T06 | `CustomField` (`app/Models/CustomField.php`) — static methods: `findAll(): array` (all rows JOIN departments for dept name); `findActive(int $deptId): array` (active, scope=institution OR dept); `findById(int $id): ?array`; `create(array $data): int` (INSERT whitelisted columns, return lastInsertId); `update(int $id, array $data): void` (UPDATE whitelisted: label, mode, options, status, updated_at; field_key and field_type excluded). All queries prepared statements via Db. | 2 | M10-T02 | P1 | All methods return correct types; create returns id; update excludes field_type and field_key |

---

## 5. Controllers & routes

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M10-T07 | `FieldConfigController` (`app/Controllers/FieldConfigController.php`) — 6 actions, all call appropriate RoleMiddleware: `index()` GET /field-config (institution_admin) — load all configurable fields from registry + current field_configs rows (dept_id=0); group by section; render field-config/index.php; `saveBulk()` POST /field-config — requireCsrf(); iterate configurableFields(); REPLACE INTO field_configs for each valid mode; DELETE rows where mode matches registry default (keeps table clean); MasterAuditLogger; clearCache(); flash; redirect; `deptView(int $deptId)` GET /field-config/{deptId} (institution_admin, dept_admin) — dept_admin scope guard (403 if wrong dept); load institution defaults + dept overrides + effective config; load custom fields for dept; render field-config/dept.php ($editable=inst_admin only); `saveDeptBulk(int $deptId)` POST /field-config/{deptId} (institution_admin) — requireCsrf(); iterate: mode='use_default' → DELETE row; else REPLACE INTO with dept_id; audit; clearCache(); flash; redirect; `resetDept(int $deptId)` POST /field-config/{deptId}/reset (institution_admin) — requireCsrf(); DELETE FROM field_configs WHERE department_id=?; audit; clearCache(); flash; redirect; `myDept()` GET /field-config/my-dept (dept_admin) — redirect to /field-config/Auth::departmentId() | 6 | M10-T05, M10-T06 | P1 | All 6 actions enforce correct roles; dept_admin 403 on wrong dept; REPLACE/DELETE cycle correct; cache cleared on every write; audit logged |
| M10-T08 | `CustomFieldController` (`app/Controllers/CustomFieldController.php`) — 6 actions, all institution_admin only: `index()` GET /field-config/custom — load CustomField::findAll(); render field-config/custom/index.php; `createForm()` GET /field-config/custom/create — load departments + section list; render form; `store()` POST /field-config/custom/create — requireCsrf(); validate label/field_type/section/scope/dept_id/mode/options; CustomField::create(); UPDATE custom_fields SET field_key='custom_'.$id; clearCache(); audit; flash; redirect; `editForm(int $id)` GET /field-config/custom/{id}/edit — load field; render form ($mode='edit'; field_type read-only); `update(int $id)` POST /field-config/custom/{id}/edit — requireCsrf(); validate; CustomField::update() (field_type excluded); clearCache(); audit; flash; redirect; `toggleStatus(int $id)` POST /field-config/custom/{id}/toggle — requireCsrf(); toggle active↔inactive; update(); clearCache(); audit; flash; redirect | 5 | M10-T06 | P1 | All actions institution_admin only; field_type not editable after creation; field_key set to custom_{id} after INSERT; cache cleared on every write |
| M10-T09 | Routes — add `use App\Controllers\FieldConfigController; use App\Controllers\CustomFieldController;` to `public/index.php`; register routes (static paths before {id} wildcards): GET /field-config/my-dept, GET /field-config/custom, GET /field-config/custom/create, POST /field-config/custom/create, GET /field-config/custom/{id}/edit, POST /field-config/custom/{id}/edit, POST /field-config/custom/{id}/toggle, GET /field-config, POST /field-config, GET /field-config/{deptId}, POST /field-config/{deptId}, POST /field-config/{deptId}/reset | 1 | M10-T07, M10-T08 | P1 | All 12 routes resolve; role violations return 403 |

---

## 6. Views

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M10-T10 | `field-config/index.php` — Bootstrap 5; department selector dropdown at top (links to /field-config/{deptId}); one Bootstrap collapse panel per section; table per section: Field Label, Current Default badge, New Setting dropdown (Required/Optional/Hidden); single "Save All" submit button at page bottom; CSRF field | 4 | M10-T07 | P1 | All 6 section panels render; locked fields absent; current mode shown as badge; single form POST |
| M10-T11 | `field-config/dept.php` — same section-panel layout; additional columns: Institution Default badge, Department Setting dropdown (Use Default / Required / Optional / Hidden); "Dept override" pill on rows with explicit dept setting; "Reset to Defaults" button triggers Bootstrap confirm modal → POST /field-config/{deptId}/reset; when $editable=false (dept_admin view): all dropdowns rendered as disabled; Save and Reset buttons absent; "Dept override" / "Institution default" indicators shown as read-only badges | 5 | M10-T07 | P1 | Inst_admin: all controls active; dept_admin: read-only; "Use Default" correctly removes override on save; Reset modal fires correct POST |
| M10-T12 | `field-config/custom/index.php` — Bootstrap 5 table: Label, Type badge, Section, Scope (Institution badge or dept name), Mode badge, Status badge, Actions (Edit / Deactivate or Reactivate); "Add Custom Field" button; empty state | 2 | M10-T08 | P1 | All custom fields listed; scope column shows dept name for dept-scoped; toggle action fires correct POST |
| M10-T13 | `field-config/custom/form.php` — shared create/edit; fields: Label (text input), Field Type (dropdown on create; read-only text on edit), Section (dropdown from FieldRegistry::SECTIONS), Scope (radio: Institution / Department; dept selector appears when Department selected), Mode (dropdown), Options textarea (shown/hidden by JS when type=select; hint "One option per line, minimum 2"); validation error display; cancel link | 4 | M10-T08 | P1 | Type read-only on edit; options textarea hidden for non-select types via JS; dept selector toggled by scope radio; validation errors inline |
| M10-T14 | Nav update — add "Field Config" link in `layouts/app.php` for institution_admin pointing to /field-config; for dept_admin pointing to /field-config/my-dept; active when URI starts with /field-config | 1 | M10-T09 | P2 | Link visible for inst_admin and dept_admin; not shown for other roles |

---

## 7. M5 integration edits

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M10-T15 | Edit `StudentFormController::show()` — after resolving $deptId, call `FieldConfig::resolve($deptId)` → `$fieldConfig` and `FieldConfig::resolveCustomFields($deptId)` → `$customFields`; load `student_custom_data` rows for the student → `$customData [custom_field_id => value]`; pass all three to the form view | 2 | M10-T05 | P1 | Form view receives $fieldConfig, $customFields, $customData; no regression on existing built-in field rendering |
| M10-T16 | Edit `StudentFormController::save()` — after saving built-in fields to student_profiles: extract POST keys matching `custom_\d+`; for each active custom field in $customFields: if mode≠'hidden', REPLACE INTO student_custom_data (student_id, custom_field_id, value, created_at/updated_at); recalculate form_completion_pct to include Required custom fields in denominator | 3 | M10-T05, M10-T15 | P1 | Custom field values persisted to student_custom_data on save; completion % updated; hidden fields skipped |
| M10-T17 | Edit `StudentFormController::submit()` — add custom field Required validation: for each custom field where mode='required' and value blank → collect error; block submission if any errors; existing built-in field validation unchanged | 2 | M10-T16 | P1 | Submission blocked when Required custom field blank; optional custom fields don't block; existing submission flow unaffected |
| M10-T18 | Edit `app/Views/student/form.php` — for each section, after built-in field inputs: iterate `$customFields` where `section === $currentSection`; skip if `$fieldConfig['custom_'.$cf['id']] === 'hidden'`; render appropriate input element by field_type (text→`<input type="text">`, textarea→`<textarea>`, number→`<input type="number">`, date→`<input type="date">`, select→`<select>` with options from `json_decode($cf['options'])`); show Required indicator if mode=required; populate from `$customData[$cf['id']] ?? ''` | 4 | M10-T16 | P1 | Custom fields render at end of their section; correct input type per field_type; pre-populated on reload; hidden fields absent |
| M10-T19 | Edit `app/Views/student/view.php` and `app/Views/student/form_submitted.php` (staff read-only view) — append custom field values per section using same $customFields + $customData pattern; Hidden fields not shown; label from $cf['label'] | 2 | M10-T18 | P1 | Custom field values visible in read-only views; Hidden fields absent |
| M10-T20 | Edit `StudentFormController::staffView()` — call FieldConfig::resolve() + resolveCustomFields(); load student_custom_data for the student; pass to staff view | 1 | M10-T05 | P1 | Staff read-only view receives custom field data |

---

## 8. M6 integration edits

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M10-T21 | Edit `RtcFieldHelper::buildChangeset()` — extend validation: `custom_*` keys pass the locked-key check (never locked); validate against active custom fields for the student's dept (pass `$activeCustomKeys` array as new parameter); current value for custom keys sourced from `$currentProfile['custom_fields']['custom_{id}']`; unknown non-custom, non-built-in key still throws InvalidArgumentException | 3 | M10-T05 | P1 | Custom keys accepted in changeset; inactive custom field keys rejected; built-in locked key rejection unchanged; unit tested |
| M10-T22 | Edit `StudentProfile::applyChangeset()` — detect `custom_*` keys in $data; route them to REPLACE INTO student_custom_data (custom_field_id = extracted int from key suffix); built-in keys continue to UPDATE student_profiles; both operations inside the caller's transaction | 2 | M10-T03 | P1 | RTC approval writes custom field values to student_custom_data; built-in field writes to student_profiles unchanged; all within one transaction |
| M10-T23 | Edit `app/Views/approvals/rtc_form.php` — after built-in field checkbox list: load active custom fields for student's dept; render each as a selectable checkbox row with current value shown; Hidden custom fields not shown | 2 | M10-T05 | P1 | Custom fields appear as selectable options in RTC creation form |
| M10-T24 | Edit `app/Views/approvals/rtc_detail.php` — in comparison table, resolve custom key labels from custom_fields table (pass $customFieldLabels map to view from RtcController::detail()); display `$customFieldLabels[$key] ?? $key` as the field label for custom_* entries | 2 | M10-T06 | P1 | Custom field changes display human-readable label in comparison table |

---

## 9. Tests

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M10-T25 | Unit: `FieldRegistryTest` (`tests/Unit/FieldRegistryTest.php`) — assert configurableFields() contains no locked keys; assert all fields have label + section + default_mode; assert SECTIONS has 6 entries; assert isCustomKey('custom_5') true, isCustomKey('blood_group') false | 2 | M10-T04 | P1 | Green |
| M10-T26 | Unit: `FieldConfigTest` (`tests/Unit/FieldConfigTest.php`) — seed field_configs (institution + dept overrides) and custom_fields; assert resolve() returns: registry default when no config; institution default overrides registry; dept override overrides institution; custom_* keys present in result; clearCache() forces fresh load; resolveCustomFields() returns active fields for dept | 5 | M10-T05 | P1 | Green |
| M10-T27 | Integration: `FieldConfigSaveTest` (`tests/Integration/FieldConfigSaveTest.php`) — call saveBulk() logic: REPLACE institution-wide mode; assert field_configs row exists with dept_id=0; call saveDeptBulk() with use_default: assert row deleted; call resetDept(): assert all dept rows deleted; assert audit log entry on each | 4 | M10-T07 | P1 | Green |
| M10-T28 | Integration: `CustomFieldCrudTest` (`tests/Integration/CustomFieldCrudTest.php`) — create custom field; assert field_key='custom_{id}'; assert findActive returns it for institution scope; deactivate; assert findActive excludes it; reactivate; assert present again; assert field_type not updated by update() | 3 | M10-T06, M10-T08 | P1 | Green |
| M10-T29 | Integration: `StudentFormCustomFieldTest` (`tests/Integration/StudentFormCustomFieldTest.php`) — seed dept + student + active custom field (scope=institution); simulate save() with custom field POST value; assert student_custom_data row created; simulate second save; assert value updated (REPLACE); assert Hidden custom field POST value not saved; assert Required custom field blocks submit when blank | 5 | M10-T16, M10-T17 | P1 | Green |
| M10-T30 | Integration: `RtcCustomFieldTest` (`tests/Integration/RtcCustomFieldTest.php`) — seed custom field + student_custom_data value; build changeset with custom key; assert custom key included; approve RTC; assert student_custom_data updated; assert student_profiles unchanged for custom key; reject RTC; assert student_custom_data unchanged | 4 | M10-T21, M10-T22 | P1 | Green |
| M10-T31 | Update `tests/bootstrap.php` — add CREATE TABLE IF NOT EXISTS for field_configs, custom_fields, student_custom_data (SQLite-compatible DDL; FK constraints omitted for SQLite; ENUM → TEXT) | 1 | — | P1 | All M10 tests run on SQLite without schema errors |

---

## 10. Build order (critical path)

1. **Migrations:** M10-T01, T02, T03 (in order — T03 depends on T02 FK)
2. **Bootstrap:** M10-T31 (alongside migrations)
3. **Helpers:** M10-T04 (FieldRegistry, no deps) → M10-T05 (FieldConfig, depends on T04 + T01/T02)
4. **Model:** M10-T06 (CustomField, depends on T02)
5. **Controllers:** M10-T07 (FieldConfigController) → M10-T08 (CustomFieldController) → M10-T09 (routes)
6. **Views:** M10-T10, T11 (built-in config views) → M10-T12, T13 (custom field views) → M10-T14 (nav)
7. **M5 edits:** M10-T15 → T16 → T17 → T18 → T19 → T20 (in order — each builds on previous)
8. **M6 edits:** M10-T21 → T22 → T23 → T24
9. **Tests:** M10-T25, T26 (unit, alongside helpers) → T27, T28, T29, T30 (integration, after controllers + M5/M6 edits)

---

## 11. Estimate summary

| Group | Hours |
|-------|------:|
| Migrations (T01–T03) | 3 |
| Helpers (T04–T05) | 8 |
| Model (T06) | 2 |
| Controllers & routes (T07–T09) | 12 |
| Views (T10–T14) | 16 |
| M5 integration edits (T15–T20) | 14 |
| M6 integration edits (T21–T24) | 9 |
| Tests (T25–T31) | 24 |
| **Total** | **~88 ideal hours (~11 dev-days)** |

---

## 12. Definition of Done

- All P1 tasks complete; all unit + integration tests green (`./vendor/bin/phpunit`) on PHP 8 / MySQL 5.7.
- `FieldConfig::resolve()` correctly merges registry → institution → dept → custom in priority order; result cached per request.
- Institution Admin can set institution-wide defaults and per-department overrides; changes take effect immediately.
- Department Admin can view (read-only) their effective field configuration.
- Institution Admin can create, edit, and deactivate custom fields of all 5 types; department-scoped and institution-scoped custom fields both render on the student form.
- Student form renders Hidden fields as absent, Required fields as validated on submit, Optional fields as non-blocking.
- Custom field values saved to `student_custom_data`; deactivating a custom field does not delete saved values.
- RTC creation form includes active custom fields; approval writes custom values to `student_custom_data` via `applyChangeset()`.
- All write actions produce `audit_log` entries via `MasterAuditLogger`.
- All POST actions CSRF-protected.
- No regression on existing M5 / M6 built-in field behaviour.
- Commit via `scripts/commit-module.sh "M10 Field Management: implementation complete"`; user pushes from Mac.

---

## 13. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, implement in Claude Code.
