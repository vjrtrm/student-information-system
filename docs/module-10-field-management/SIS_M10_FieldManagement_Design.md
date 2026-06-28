# SIS — Module 10: Field Management
## Stage 2: Design (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 10 of 12 — Field Management
**Document stage:** Requirements ✅ → **Design (this document)** → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M10_FieldManagement_Requirements.md`

---

## 1. Design goals

- `FieldConfig::resolve(int $deptId): array` is the single choke-point: every form render and save calls it; result is statically cached for the request lifetime.
- Built-in field overrides and custom field definitions live in separate tables, joined only at resolve-time.
- `department_id = 0` in `field_configs` denotes the institution-wide default (avoids NULL in a unique index).
- Custom field keys are `custom_{id}` — auto-set after INSERT, guaranteed no collision with built-in keys.
- Student custom values stored in an EAV table (`student_custom_data`) — no schema migration per new field.
- M5 StudentFormController and views are edited minimally: pass the resolved config array; views iterate it.
- Bulk POST for config saves (one form submission per page) with REPLACE INTO for efficiency.

---

## 2. Resolved design decisions (from open questions)

| # | Question | Decision |
|---|----------|----------|
| 1 | Document upload fields configurable as Required? | **Yes — all configurable fields including doc uploads can be set to Required, Optional, or Hidden.** Departments that need to enforce document submission (e.g. PG requiring migration certificate) can mark upload fields as Required. The form's upload-present check already exists in M5; mode=Required simply makes it mandatory for submission. |
| 2 | Bulk save vs changed-only? | **Bulk save (REPLACE INTO all fields on the page).** The full page is POSTed; PHP iterates all field keys in the registry and UPSERTs every row. For ~95 built-in fields this is ≤ 95 rows — well within MySQL performance limits. Simpler code, no diff-tracking needed. |
| 3 | Copy config between departments? | **Out of scope for v1.** Manual configuration per department is sufficient. Noted as a future enhancement. |
| 4 | All fields on one page vs paginated by section? | **Single scrollable page grouped by section with sticky section headers.** Gives Institution Admin the full picture in one view; Bootstrap collapse panels per section keep it manageable. |
| 5 | Department-scoped custom fields visible to Institution Admin in all dept views? | **Yes — Institution Admin sees all custom fields (both institution-scoped and all department-scoped) in the custom fields list**, with a "Scope" column showing the target department. On a specific department's config page, Institution Admin sees institution-scoped + that dept's custom fields combined. |
| 6 | Custom fields in the RTC flow? | **Yes — as first-class fields.** RTC creation form (M6) loads active custom fields for the student's department and includes them as selectable change targets. Proposed and current values stored in `change_requests.proposed_changes` JSON under `custom_{id}` keys. `RtcFieldHelper::buildChangeset()` is updated to accept custom field keys (those prefixed `custom_`). |

---

## 3. Component architecture (MVC)

### New helpers

**`app/Helpers/FieldRegistry.php`** — static class, PHP constant data, no DB access:
- `SECTIONS: array` — ordered list of the six section names.
- `ALL_FIELDS: array` — map of `field_key => [label, section, default_mode, locked]` for all ~95 built-in fields.
- `LOCKED_KEYS: array` — identity + always-required keys excluded from config UI.
- `configurableFields(): array` — ALL_FIELDS filtered to non-locked entries.
- `isCustomKey(string $key): bool` — returns `str_starts_with($key, 'custom_')`.

**`app/Helpers/FieldConfig.php`** — static class, DB-backed, request-scoped cache:
- `resolve(int $deptId): array` — returns merged map `[field_key => 'required'|'optional'|'hidden']` covering both built-in and active custom fields. Algorithm:
  1. Start with registry defaults for all built-in configurable fields.
  2. Apply institution-wide overrides (`field_configs WHERE department_id = 0`).
  3. Apply department overrides (`field_configs WHERE department_id = $deptId`).
  4. Append active custom fields for the dept: `custom_fields WHERE status='active' AND (scope='institution' OR department_id = $deptId)`; key = `custom_{id}`, mode = field's own mode (not overridden further per-dept in v1).
  5. Cache result in `private static array $cache[deptId]`.
- `clearCache(): void` — for tests.
- `resolveCustomFields(int $deptId): array` — returns full custom field rows (id, label, field_type, section, options JSON) for a dept; used by form renderer to know type + options.

### New controllers

**`app/Controllers/FieldConfigController.php`** — institution-wide defaults + department overrides:

| Method | Route | Roles | Description |
|--------|-------|-------|-------------|
| `index()` | GET /field-config | institution_admin | Institution-wide defaults page; all configurable fields grouped by section |
| `saveBulk()` | POST /field-config | institution_admin | CSRF; REPLACE INTO field_configs (field_key, department_id=0, mode) for every POSTed key; audit; flash |
| `deptView(int $deptId)` | GET /field-config/{deptId} | institution_admin, dept_admin | Dept override page; inst_admin sees all fields; dept_admin read-only |
| `saveDeptBulk(int $deptId)` | POST /field-config/{deptId} | institution_admin | CSRF; REPLACE INTO with dept overrides; "use_default" removes the row (DELETE) |
| `resetDept(int $deptId)` | POST /field-config/{deptId}/reset | institution_admin | CSRF; DELETE all field_configs WHERE department_id=$deptId; audit; flash |
| `myDept()` | GET /field-config/my-dept | dept_admin | Redirect to /field-config/{Auth::departmentId()} |

**`app/Controllers/CustomFieldController.php`** — custom field CRUD:

| Method | Route | Roles | Description |
|--------|-------|-------|-------------|
| `index()` | GET /field-config/custom | institution_admin | List all custom fields; scope + dept column |
| `createForm()` | GET /field-config/custom/create | institution_admin | Create form |
| `store()` | POST /field-config/custom/create | institution_admin | CSRF; validate; INSERT custom_fields; UPDATE field_key=custom_{id}; audit; flash |
| `editForm(int $id)` | GET /field-config/custom/{id}/edit | institution_admin | Edit form; field_type read-only |
| `update(int $id)` | POST /field-config/custom/{id}/edit | institution_admin | CSRF; update label/mode/options/status; audit; flash |
| `toggleStatus(int $id)` | POST /field-config/custom/{id}/toggle | institution_admin | CSRF; toggle active↔inactive; audit; flash |

### New model

**`app/Models/CustomField.php`** — static helpers:
- `findAll(): array` — all custom fields with dept name JOIN.
- `findActive(int $deptId): array` — active fields for a dept (institution + dept-scoped).
- `findById(int $id): ?array` — single row.
- `create(array $data): int` — INSERT; returns id; caller then UPDATEs field_key=custom_{id}.
- `update(int $id, array $data): void` — UPDATE whitelisted columns (label, mode, options, status).

### Views

| File | Purpose |
|------|---------|
| `field-config/index.php` | Institution-wide defaults; Bootstrap collapse per section; dropdown per field row |
| `field-config/dept.php` | Dept overrides; shared by inst_admin (editable) and dept_admin (read-only); shows Institution Default column |
| `field-config/custom/index.php` | Custom field list; scope badge; toggle status action |
| `field-config/custom/form.php` | Shared create + edit; field_type read-only on edit; options textarea for select type |

### M5 integration edits

**`app/Controllers/StudentFormController.php`** — `show()` and `save()` / `submit()`:
- After resolving `$deptId`, call `FieldConfig::resolve($deptId)` → `$fieldConfig`.
- Call `FieldConfig::resolveCustomFields($deptId)` → `$customFields`.
- Pass both to the view.
- In `save()`: extract `custom_*` keys from POST; for each, REPLACE INTO `student_custom_data` (student_id, custom_field_id, value).
- In `submit()`: validate Required custom fields; block submission if any blank.
- In `staffView()`: also call resolve + resolveCustomFields; pass to staff view.

**`app/Views/student/form.php`** — for each section, after built-in fields: iterate `$customFields` where `section === $currentSection`; render appropriate input type; skip if `$fieldConfig["custom_{id}"] === 'hidden'`.

**`app/Views/student/view.php`** (read-only) — append custom field values per section from `$customData` array.

### M6 integration edits

**`app/Helpers/RtcFieldHelper.php`** — `buildChangeset()`:
- Extend `LOCKED_KEYS` check: custom keys (`custom_*`) are never locked.
- Extend unknown-key validation: a key is valid if it exists in `FieldRegistry::ALL_FIELDS` OR matches `custom_\d+` pattern AND the custom field is active for the student's dept.
- Current value for a custom key comes from `student_custom_data` (passed in via `$currentProfile['custom_fields']` sub-array).

**`app/Views/approvals/rtc_form.php`** — after built-in field checkboxes, render active custom fields as additional selectable rows.

**`app/Views/approvals/rtc_detail.php`** — comparison table resolves custom key labels from `custom_fields` table for display.

---

## 4. Data model

### New tables

**`field_configs`**
```sql
CREATE TABLE field_configs (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    field_key     VARCHAR(60)  NOT NULL,
    department_id INT UNSIGNED NOT NULL DEFAULT 0,  -- 0 = institution-wide default
    mode          ENUM('required','optional','hidden') NOT NULL,
    created_at    DATETIME NOT NULL,
    updated_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_dept (field_key, department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`department_id = 0` is the institution-wide sentinel. The unique index on `(field_key, department_id)` enables efficient REPLACE INTO upserts.

**`custom_fields`**
```sql
CREATE TABLE custom_fields (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    field_key     VARCHAR(20)  NOT NULL DEFAULT '',  -- set to custom_{id} after INSERT
    label         VARCHAR(150) NOT NULL,
    field_type    ENUM('text','textarea','number','date','select') NOT NULL,
    section       VARCHAR(60)  NOT NULL,
    scope         ENUM('institution','department') NOT NULL DEFAULT 'institution',
    department_id INT UNSIGNED NULL,                 -- NULL for institution scope
    mode          ENUM('required','optional','hidden') NOT NULL DEFAULT 'optional',
    options       JSON NULL,                         -- for select type: ["Opt A","Opt B"]
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_by    INT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_key (field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**`student_custom_data`**
```sql
CREATE TABLE student_custom_data (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED NOT NULL,
    custom_field_id INT UNSIGNED NOT NULL,
    value           TEXT NOT NULL DEFAULT '',
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_field (student_id, custom_field_id),
    KEY idx_student (student_id),
    CONSTRAINT fk_scd_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_scd_field   FOREIGN KEY (custom_field_id) REFERENCES custom_fields(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Migrations

- `025_create_field_configs.sql`
- `026_create_custom_fields.sql`
- `027_create_student_custom_data.sql`

---

## 5. Flows

### 5.1 Institution Admin saves institution-wide defaults

```
POST /field-config
  → requireCsrf()
  → iterate FieldRegistry::configurableFields() keys
      $mode = $_POST['mode'][$key] ?? null
      if $mode not in ['required','optional','hidden'] → skip
      REPLACE INTO field_configs (field_key, department_id=0, mode, created_at, updated_at)
  → MasterAuditLogger(actor, 'bulk_save', 'field_config', 0, ['dept'=>'institution','count'=>N])
  → FieldConfig::clearCache()
  → flash success "Field defaults saved." → redirect /field-config
```

### 5.2 Institution Admin saves department overrides

```
POST /field-config/{deptId}
  → requireCsrf(); verify dept exists
  → iterate FieldRegistry::configurableFields() keys
      $mode = $_POST['mode'][$key] ?? 'use_default'
      if $mode === 'use_default':
          DELETE FROM field_configs WHERE field_key=? AND department_id=?
      else:
          REPLACE INTO field_configs (field_key, department_id=$deptId, mode, ...)
  → audit; clearCache(); flash; redirect /field-config/{deptId}
```

### 5.3 Create custom field

```
POST /field-config/custom/create
  → requireCsrf()
  → validate: label required; field_type in whitelist; section in SECTIONS;
              if select: options not empty (≥2 entries)
              if scope=department: department_id must be valid dept
  → INSERT custom_fields (label, field_type, section, scope, department_id, mode,
                           options=json_encode($opts), status='active',
                           created_by=Auth::userId(), created_at)
  → $id = lastInsertId()
  → UPDATE custom_fields SET field_key='custom_'.$id WHERE id=$id
  → MasterAuditLogger(actor, 'create', 'custom_field', $id, ['label', 'type', 'section'])
  → FieldConfig::clearCache()
  → flash "Custom field created." → redirect /field-config/custom
```

### 5.4 Student form save with custom fields

```
POST /student/form/save
  → FieldConfig::resolve($deptId)             → $fieldConfig
  → FieldConfig::resolveCustomFields($deptId) → $customFields (id, field_key, mode, field_type)
  → Extract built-in fields from POST → save to student_profiles (existing M5 flow)
  → Extract custom fields from POST:
      foreach $customFields as $cf:
          $key  = 'custom_' . $cf['id']
          $mode = $fieldConfig[$key] ?? $cf['mode']
          if $mode === 'hidden': skip
          $value = trim($_POST[$key] ?? '')
          REPLACE INTO student_custom_data (student_id, custom_field_id=$cf['id'], value, ...)
  → Recalculate form_completion_pct (include Required custom fields in denominator)
  → flash; redirect
```

### 5.5 RTC with custom fields

```
POST /rtc/create
  → RtcFieldHelper::buildChangeset($postedFields, $currentProfile, $student)
      $currentProfile includes ['custom_fields' => ['custom_42' => 'Old Value', ...]]
      $postedFields may include custom_42, custom_99, etc.
      custom_* keys: validate against active custom fields for dept; not locked
      → changeset includes custom key entries alongside built-in entries
  → ChangeRequest::create(['proposed_changes' => json_encode($changeset)])
  → On approval: applyChangeset() → writes built-in fields to student_profiles;
                                     writes custom fields to student_custom_data
```

`StudentProfile::applyChangeset()` updated to handle `custom_*` keys separately:
- Built-in keys → UPDATE student_profiles.
- `custom_*` keys → REPLACE INTO student_custom_data (custom_field_id = extracted int).

---

## 6. RBAC & department scoping

| Action | Staff | Dept Admin | Institution Admin |
|--------|-------|-----------|------------------|
| View institution-wide defaults | — | — | ✓ |
| Save institution-wide defaults | — | — | ✓ |
| View own dept effective config | — | Read-only | ✓ |
| Save dept overrides | — | ✗ | ✓ |
| Reset dept overrides | — | ✗ | ✓ |
| View all custom fields | — | — | ✓ |
| Create / edit custom field | — | ✗ | ✓ |
| Deactivate custom field | — | ✗ | ✓ |
| Student form renders config | N/A | N/A | N/A (student only) |

`deptView()`: institution_admin passes any `$deptId`; dept_admin may only access their own (`Auth::departmentId()`). Guard: if `Auth::role() === 'dept_admin' && $deptId !== Auth::departmentId()` → 403.

---

## 7. Session / security & validation

| Rule | Implementation |
|------|----------------|
| CSRF | `requireCsrf()` on all POST |
| Mode whitelist | Only `required`, `optional`, `hidden`, `use_default` accepted; anything else skipped |
| Custom field type locked post-creation | `field_type` excluded from `update()` whitelist |
| Custom field key immutable | `field_key` excluded from `update()` whitelist |
| Options sanitised | `json_encode(array_values(array_filter(array_map('trim', explode("\n", $raw)))))` |
| Dept scope guard for deptView | As above; 403 for cross-dept dept_admin access |
| No hard-delete of custom fields | `toggleStatus()` only; no DELETE route |
| Cache invalidation | `FieldConfig::clearCache()` called after every config or custom field write |
| No PII in audit | Audit details: field keys and counts only, no student data |

### Custom field validation rules

| Field | Rule |
|-------|-------|
| label | Required, 2–150 chars |
| field_type | Enum whitelist |
| section | Must be one of FieldRegistry::SECTIONS |
| scope | `institution` or `department` |
| department_id | Required + must exist when scope=department; null when scope=institution |
| mode | Enum whitelist |
| options | Required for select type; ≥ 2 non-empty lines; each option ≤ 100 chars |

---

## 8. Screen behaviour & messages

### Institution-wide defaults (`/field-config`)

- Bootstrap page; department selector dropdown at top linking to `/field-config/{deptId}` for quick navigation.
- One Bootstrap `<details>` / collapse panel per section (6 total); each contains a table of fields.
- Table columns: Field Label, Current Default (badge), New Setting (dropdown: Required / Optional / Hidden).
- Locked fields absent entirely.
- Single "Save All" button at bottom submits all sections as one POST.
- Inline badge shows current effective mode (from DB or registry default if unconfigured).

### Department config (`/field-config/{deptId}`)

- Same layout; additional columns: Institution Default (badge, read-only) and Department Setting (dropdown: Use Default / Required / Optional / Hidden).
- "Use Default" removes the dept row; greyed dropdown border indicates inherited.
- "Reset to Defaults" button: Bootstrap modal confirmation before POST.
- Dept_admin sees same layout but all dropdowns are disabled (`readonly` display only); no Save or Reset buttons.
- "Dept override" pill on rows where department has an explicit setting.

### Custom fields list (`/field-config/custom`)

- Table: Label, Type badge, Section, Scope (Institution / Dept name), Mode badge, Status badge, Actions (Edit / Deactivate or Reactivate).
- "Add Custom Field" button top-right.
- Empty state when no custom fields exist.

### Custom field form (`/field-config/custom/form.php`)

- Options textarea (shown only when type=select): each line = one option; hint "Enter one option per line, minimum 2".
- field_type shown as read-only text on edit mode.
- Department selector shown only when scope=department.

### Flash messages

| Action | Message |
|--------|---------|
| Save defaults | "Institution-wide field defaults saved." |
| Save dept overrides | "Department field configuration saved." |
| Reset dept | "Department overrides cleared. Fields now follow institution defaults." |
| Create custom field | "Custom field '[Label]' created." |
| Edit custom field | "Custom field updated." |
| Deactivate | "Custom field deactivated. Existing student data is preserved." |
| Reactivate | "Custom field reactivated." |

---

## 9. Configuration parameters

No new config keys. The six section names are defined as a constant in `FieldRegistry::SECTIONS` — no DB table needed. Changing the list requires a code deploy (acceptable; sections are structural).

---

## 10. Edge cases

| Scenario | Handling |
|----------|----------|
| Student opens form after a field is Hidden mid-completion | Field hidden from render; saved value untouched in DB; form completion % recalculated without hidden field |
| Required custom field added after some students already submitted | Only affects students whose form is not yet submitted (status ≠ 'submitted'); submitted forms unchanged |
| Custom field deactivated while student has a pending RTC referencing it | RTC proposed_changes JSON retains the key; on approval, `applyChangeset()` silently skips `custom_*` keys whose field is inactive |
| `select` custom field option removed after student saved a value | Old value preserved in `student_custom_data`; displayed as-is on read-only view; not validated against current option list on re-save (value retained) |
| Two admins simultaneously bulk-save defaults | REPLACE INTO is atomic per row; last write wins; no corruption |
| dept_admin navigates to `/field-config/{other_deptId}` | Guard in `deptView()` returns 403 |
| Custom field with scope=institution deactivated | Disappears from ALL department forms; `resolveCustomFields()` filters status=active |
| FieldConfig::resolve() called multiple times per request | Static `$cache` returns memoised array after first call |
| `student_custom_data` row missing for a field (student never saved that field) | `resolveCustomFields` returns empty string default; form renders empty input |

---

## 11. Traceability (requirement → design)

| Requirement | Design element |
|-------------|---------------|
| A1 — Institution-wide defaults | `FieldConfigController::index/saveBulk()` + `field_configs` dept_id=0 |
| A2 — Immediate effect | `FieldConfig::clearCache()` after every write; resolve() fetches fresh on next request |
| B1 — Dept overrides | `deptView/saveDeptBulk/resetDept()` + REPLACE/DELETE in field_configs |
| B2 — Dept admin read-only | `deptView()` with disabled dropdowns when role=dept_admin |
| C1 — Form respects config | `FieldConfig::resolve()` passed to form view; Hidden → not rendered; Required → validated on submit |
| D1 — Create custom field | `CustomFieldController::store()` + INSERT + UPDATE field_key=custom_{id} |
| D2 — Custom field on student form | `resolveCustomFields()` + form view appends custom fields per section; values saved to `student_custom_data` |
| D3 — Edit / deactivate | `CustomFieldController::update/toggleStatus()` + cache clear |
| NFR — Performance | Static cache in `FieldConfig`; single query resolves all built-in + custom |
| NFR — Data preservation | No DELETE on student_custom_data; Hidden/inactive = render skip only |
| NFR — Custom field key stability | field_key set once, excluded from update whitelist |
| NFR — RTC integration | `RtcFieldHelper` accepts `custom_*` keys; `applyChangeset()` routes to student_custom_data |
| Migrations | 025, 026, 027 |

---

## 12. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, proceed to Tasks.
