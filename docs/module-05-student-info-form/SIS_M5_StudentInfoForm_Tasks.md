# SIS — Module 5: Student Information Form
## Stage 3: Tasks (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 5 of 12 — Student Information Form
**Document stage:** Requirements ✅ → Design ✅ → **Tasks (this document)**
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M5_StudentInfoForm_Design.md`

---

## 1. How to read this

Each task: ID, deliverable, estimate (ideal hours), dependencies, priority, "done when". P1 = required for the module to function; P2 = hardening/nice-to-have. Estimates assume M1–M4 codebase in place. Build order in §8.

---

## 2. Data layer

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M5-T01 | Migration `019_create_student_profiles.sql` — full table per design §4.1: all personal, address, parent/guardian, academic JSON, entrance/admission, bank/scholarship, and tracking columns; UNIQUE on student_id; FK to students, taluks, districts, states; indexes on form_status | 3 | — | P1 | Table created on MySQL 5.7; all columns + constraints present; existing students unaffected |
| M5-T02 | Config file `config/form.php` — upload_max_bytes, upload_allowed_doc_mimes, upload_allowed_photo_mimes, upload_path | 0.5 | — | P1 | `Config::get('form.upload_max_bytes')` returns 2097152 |

---

## 3. Helpers

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M5-T03 | `FormFieldRules::getApplicableFields(array $profile, array $student): array` — returns array of field descriptors `[key, label, section, required, visible]`; required/visible flags vary by: `programme_level` (UG/PG controls qual_ug row), `family_situation` (controls which parent/guardian fields are required), `admission_type` (controls qual_diploma row); father_name always required | 5 | — | P1 | Unit tested: all 4 family_situation × 2 programme_level × 2 admission_type combinations produce correct required/visible flags; father_name required in all cases |
| M5-T04 | `FormFieldRules::computeCompletion(array $profile, array $rules): int` — counts filled required-and-visible fields vs total; file fields checked by non-null path; JSON qual fields checked for non-empty `{exam, board, institution, year, percentage}`; returns 0–100 | 3 | M5-T03 | P1 | Unit tested: empty profile → 0; fully filled profile → 100; partial profile → correct intermediate value |
| M5-T05 | `DocumentUploadHandler::handle(string $fieldKey, array $file, int $studentId, bool $photoOnly): string` — validates MIME from whitelist and size ≤ upload_max_bytes server-side; generates filename `{fieldKey}_{time()}.{ext}`; stores at `storage/uploads/students/{studentId}/`; deletes previous file for same field if exists; returns relative path | 4 | M5-T02 | P1 | Valid PDF/image stored and path returned; oversized file throws `UploadException`; wrong MIME throws `UploadException`; non-image uploaded to photo field throws `UploadException` |
| M5-T06 | `DocumentUploadHandler::guardSubmitted(array $profile): void` — throws `\RuntimeException` if `form_status = 'submitted'`; called before any upload replacement | 1 | M5-T05 | P1 | Throws on submitted profile; passes on incomplete profile |
| M5-T07 | View helper `maskAadhaar(string $n): string` — `'XXXX-XXXX-' . substr($n, -4)` — added to `app/Helpers/View.php` as static method | 0.5 | — | P1 | `maskAadhaar('123456789012')` returns `'XXXX-XXXX-9012'` |

---

## 4. Models

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M5-T08 | `StudentProfile::findByStudent(int $studentId): ?array` — SELECT with JOIN to taluks/districts/states for display names | 2 | M5-T01 | P1 | Returns full profile array; null when no row exists |
| M5-T09 | `StudentProfile::upsert(int $studentId, array $data): void` — `INSERT ... ON DUPLICATE KEY UPDATE`; only updates columns present in $data (does not overwrite unposted fields); sets last_saved_at = NOW() | 4 | M5-T01 | P1 | New student → INSERT; subsequent call → UPDATE; columns absent from $data remain unchanged |
| M5-T10 | `StudentProfile::submit(int $studentId): void` — in a transaction: UPDATE student_profiles SET form_status='submitted', form_submitted_at=NOW(), form_completion_pct=100; UPDATE students SET onboarding_status='form_submitted'; calls MasterAuditLogger | 3 | M5-T01 | P1 | Both rows updated in one transaction; audit_log entry created; idempotent (no error if already submitted) |
| M5-T11 | `StudentProfile::getCompletionSummary(int $studentId, array $student): array` — returns `['pct' => int, 'sections' => [section => bool], 'missing' => [field_key => label]]` | 3 | M5-T08, M5-T03, M5-T04 | P1 | Returns correct pct and per-section filled flag; missing list matches unfilled required fields |

---

## 5. Controllers & routes

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M5-T12 | `StudentFormController::show()` — `GET /student/form`; student role only; loads profile (or empty); calls `getApplicableFields`; loads all dropdown data (option lists, states, districts, taluks); redirects to `/student/form/view` if already submitted; renders `show.php` | 4 | M5-T08, M5-T03 | P1 | Pre-filled M3 fields shown read-only; dropdowns populated; submitted student redirected to readonly; incomplete student sees form with current saved data |
| M5-T13 | `StudentFormController::save()` — `POST /student/form/save`; student role; CSRF; guards submitted form (403); sanitises input fields; processes file uploads via `DocumentUploadHandler`; calls `StudentProfile::upsert`; recomputes completion; flash + redirect | 6 | M5-T09, M5-T05, M5-T04 | P1 | Partial save with blanks accepted; file stored; completion % updates; submitted form returns 403; CSRF enforced |
| M5-T14 | `StudentFormController::submit()` — `POST /student/form/submit`; student role; CSRF; runs full required-field validation via `getApplicableFields` + profile; returns to form with missing-field list on failure; calls `StudentProfile::submit()` on success; flash + redirect to `/student/form/view` | 5 | M5-T10, M5-T03 | P1 | All required fields present → submitted + locked; any missing → flash danger with field list; already-submitted → redirect with info flash |
| M5-T15 | `StudentFormController::view()` — `GET /student/form/view`; student role; renders `readonly.php` with own profile; shows "Not yet started" gracefully when no profile row | 2 | M5-T08 | P1 | Submitted student sees full read-only summary; incomplete student sees partial data with amber "Incomplete" badge |
| M5-T16 | `StudentFormController::staffView(int $studentId)` — `GET /student/form/{studentId}/view`; staff/dept_admin/institution_admin; dept-scope guard; fetches student + profile; renders `readonly.php` | 3 | M5-T08 | P1 | Staff sees student form; wrong-dept → 403; inst_admin sees any dept; no-profile row → "Not yet started" |
| M5-T17 | Routes — register all M5 routes in `public/index.php`; `/student/form/{studentId}/view` placed before generic student routes to avoid match conflicts | 1 | M5-T12–M5-T16 | P1 | All 5 routes resolve; 403 on role violation; CSRF on POSTs |

---

## 6. Views

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M5-T18 | `student-form/show.php` — Bootstrap accordion with 6 sections; single `<form>` tag wrapping all sections, POST to `/student/form/save`; CSRF field; per-section Save button (all submit the same form); progress bar at top (`form_completion_pct`%, colour-coded); required fields marked `*`; pre-filled read-only fields rendered as `<input readonly>`; conditional field show/hide via JS (family_situation, comm_same_as_perm, disability, scholarship, qual_ug, qual_diploma); Submit button disabled when pct < 100, with Bootstrap modal confirmation | 12 | M5-T12 | P1 | All 6 sections render correctly; JS conditionals work (show/hide fields on selector change); Submit disabled below 100%; modal confirms before POST; CSRF present |
| M5-T19 | `student-form/readonly.php` — shared by student (post-submit) and staff view; sections as Bootstrap cards with label/value rows; passport photo as 100×100 thumbnail; document fields as download links (new tab); Aadhaar masked via `View::maskAadhaar()`; status badge (Submitted green / Incomplete amber); "Request a Change" placeholder button; completion % shown in header | 6 | M5-T15, M5-T16 | P1 | All fields display correctly; Aadhaar masked; documents open in new tab; status badge correct; staff header shows student name + enrolment number |
| M5-T20 | Section checklist partial `student-form/_section_status.php` — included at top of `show.php`; shows tick/cross per section based on `getCompletionSummary` result | 2 | M5-T11 | P2 | Each section shows correct tick/cross; updates after save |
| M5-T21 | Student dashboard enrolment widget update — add form completion status card to existing student dashboard: "Form: 72% complete" link → `/student/form`; or "Form: Submitted ✓" when submitted | 2 | M5-T11 | P1 | Dashboard shows correct pct or Submitted; link navigates to form |
| M5-T22 | Nav update (`layouts/app.php`) — add "My Form" link for student role; add "Students" → sub-link "View Forms" for staff/dept_admin/inst_admin (linking to student list where staff can click through to staffView) | 1 | M5-T17 | P2 | Link appears for correct roles |

---

## 7. Tests

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M5-T23 | Unit: `FormFieldRulesTest` — `getApplicableFields` returns correct required/visible flags for: (a) Both Parents + UG + Management, (b) Single Parent Father + PG + Lateral Entry, (c) Single Parent Mother + UG, (d) Guardian + PG; assert father_name required in all cases; assert qual_ug hidden for UG, visible for PG; assert qual_diploma visible only for Lateral Entry | 5 | M5-T03 | P1 | All assertion sets green |
| M5-T24 | Unit: `CompletionPercentageTest` — `computeCompletion`: empty profile → 0; profile with all required fields for Both Parents + UG → 100; profile missing passport_photo → not 100; profile with Guardian (mother fields optional) fills only guardian fields → correct % | 4 | M5-T04 | P1 | Green |
| M5-T25 | Unit: `MaskAadhaarTest` — `maskAadhaar('123456789012')` → `'XXXX-XXXX-9012'`; 11-digit input handled gracefully | 1 | M5-T07 | P1 | Green |
| M5-T26 | Unit: `DocumentUploadHandlerTest` — valid JPEG ≤ 2 MB → stored, path returned; valid PDF → stored; PDF to photo-only field → `UploadException`; file > 2 MB → `UploadException`; second upload for same field deletes old file | 5 | M5-T05 | P1 | Green |
| M5-T27 | Integration: `StudentProfileUpsertTest` — first save creates row; second save updates only posted fields; fields absent from second POST retain original values | 3 | M5-T09 | P1 | Green |
| M5-T28 | Integration: `PartialSaveValidationTest` — POST with required fields blank → save succeeds (no validation error); completion % reflects actual fill | 2 | M5-T09, M5-T04 | P1 | Green |
| M5-T29 | Integration: `SubmitValidationTest` — POST to submit with missing required fields → returns list of missing field keys; profile form_status remains 'incomplete' | 3 | M5-T14, M5-T03 | P1 | Green |
| M5-T30 | Integration: `SubmitLockTest` — submit with all required fields → `form_status='submitted'`; `onboarding_status='form_submitted'`; subsequent save POST → 403; audit_log row created | 4 | M5-T10 | P1 | Green |
| M5-T31 | Integration: `ConditionalFieldSubmitTest` — profile with family_situation='guardian' (mother fields unpopulated); submit → mother fields not in missing list; submit succeeds | 3 | M5-T14, M5-T03 | P1 | Green |
| M5-T32 | Integration: `CommSameAsPermTest` — save with comm_same_as_perm=1 → communication columns copied from permanent values on server; subsequent save with comm_same_as_perm=0 + new comm values → comm columns updated | 2 | M5-T09 | P1 | Green |
| M5-T33 | Integration: `StaffViewScopeTest` — staff in dept A can GET `/student/form/{idA}/view`; staff in dept A gets 403 for student in dept B; institution_admin accesses any dept | 3 | M5-T16 | P1 | Green |
| M5-T34 | Integration: `AuditLogTest` — submit action produces correct audit_log row: action='student_form_submitted', entity='student_profile', entity_id=profile.id | 2 | M5-T10 | P2 | Green |
| M5-T35 | Update `tests/bootstrap.php` — add `student_profiles` SQLite CREATE TABLE with all columns; add taluks/districts/states tables if not present | 2 | — | P1 | All M5 integration tests can run without MySQL |

---

## 8. Build order (critical path)

1. **Data layer:** M5-T01 → M5-T02
2. **Helpers (pure):** M5-T07 (maskAadhaar, no DB), M5-T03 (FormFieldRules, no DB), M5-T04 (computeCompletion, no DB)
3. **Helpers (file):** M5-T05 → M5-T06
4. **Models:** M5-T08 → M5-T09 → M5-T10 → M5-T11
5. **Controllers:** M5-T12 → M5-T13 → M5-T14 → M5-T15 → M5-T16 → M5-T17
6. **Views:** M5-T18 → M5-T19 → M5-T20 → M5-T21 → M5-T22
7. **Tests:** M5-T23–M5-T26 (unit, alongside helpers); M5-T27–M5-T34 (integration, after controller); M5-T35 (bootstrap, before integration tests)

---

## 9. Estimate summary

| Group | Hours |
|-------|------:|
| Data layer (T01–T02) | 3.5 |
| Helpers (T03–T07) | 13.5 |
| Models (T08–T11) | 12 |
| Controllers & routes (T12–T17) | 21 |
| Views (T18–T22) | 23 |
| Tests (T23–T35) | 37 |
| **Total** | **~110 ideal hours (~14 dev-days)** |

---

## 10. Definition of Done

- All P1 tasks complete; all unit + integration tests green (`./vendor/bin/phpunit`) on PHP 8 / MySQL 5.7.
- Student can log in, fill form in stages (partial save), see completion %, and submit when 100% complete.
- Form locks on submission; subsequent save POSTs return 403.
- `onboarding_status` advances to `form_submitted` on submission; audit_log entry created.
- Conditional fields (family situation, comm address, disability, qual_ug/diploma, scholarship) show/hide correctly in UI and are excluded from required-field validation on the server when hidden.
- Father's Name validated as required in all family_situation combinations.
- Document uploads validated server-side for MIME type and size; passport photo restricted to image only.
- Aadhaar displayed masked in all views.
- Staff/admin read-only view is dept-scoped; institution_admin can view any department.
- Commit via `scripts/commit-module.sh "M5 Student Information Form: implementation complete"`; user pushes from Mac.

---

## 11. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, Module 5 is fully specified and ready for implementation in Claude Code. After confirming "Module 5 done", the Module 6 spec cycle (Submission & Edit Approval — Request-to-Change) will begin.
