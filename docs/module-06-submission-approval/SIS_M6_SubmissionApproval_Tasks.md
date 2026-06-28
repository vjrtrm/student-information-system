# SIS — Module 6: Submission & Edit Approval (Request-to-Change)
## Stage 3: Tasks (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 6 of 12 — Submission & Edit Approval (Request-to-Change)
**Document stage:** Requirements ✅ → Design ✅ → **Tasks (this document)**
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M6_SubmissionApproval_Design.md`

---

## 1. How to read this

Each task: ID, deliverable, estimate (ideal hours), dependencies, priority, "done when". P1 = required for the module to function; P2 = hardening/polish. Estimates assume M1–M5 codebase in place. Build order in §9.

---

## 2. Data layer

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M6-T01 | Migration `020_create_change_requests.sql` — table: id, student_id, department_id, initiated_by, initiator_type ENUM('student','staff'), reason TEXT, proposed_changes JSON, status ENUM('pending','approved','rejected') DEFAULT 'pending', rejection_reason TEXT NULL, reviewed_by INT NULL, reviewed_at DATETIME NULL, created_at, updated_at; FKs to students, departments, users (initiated_by), users (reviewed_by); indexes on (department_id, status), (student_id, status) | 2 | — | P1 | Table created on MySQL 5.7; all columns and constraints present; JSON column accepted by engine |
| M6-T02 | Migration `021_create_notification_events.sql` — table: id, event_key VARCHAR(50), student_id, actor_id, recipient_type ENUM('student','dept_admin'), recipient_id INT NULL, change_request_id INT NULL, payload JSON, sent_at DATETIME NULL, created_at; FKs to students, users (actor_id), change_requests (change_request_id) NULL; index on (sent_at) for M7 queue polling | 2 | M6-T01 | P1 | Table created; NULL recipient_id accepted (dept admin resolved at send time by M7) |

---

## 3. Helpers

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M6-T03 | `RtcFieldHelper::buildChangeset(array $postedFields, array $currentProfile, array $student): array` — accepts field_key → proposed_value pairs from POST; for each field: looks up current value from $currentProfile; validates proposed value against M5 rules (type, max length; file fields excluded — handled by RtcUploadHandler); builds and returns array of `['field_key', 'label', 'current_value', 'proposed_value', 'is_file' => false]` entries; throws `\InvalidArgumentException` on unknown field key or disallowed key (onboarding-locked fields: first_name, last_name, dob, mobile, student_id); empty changeset (no fields changed) throws `\InvalidArgumentException('No changes specified')` | 4 | — | P1 | Unit tested: valid fields build correct changeset; onboarding-locked key throws; unknown key throws; empty POST throws; whitespace-only proposed value treated as empty |
| M6-T04 | `RtcUploadHandler::storeTemp(string $fieldKey, array $file, int $rtcId): string` — validates via DocumentUploadHandler rules (MIME whitelist, ≤ 2 MB); stores at `storage/uploads/rtc/temp/{rtcId}/{fieldKey}_{time()}.{ext}`; returns relative temp path. `::commit(int $rtcId, int $studentId, array $fileEntries): void` — moves each temp file to `storage/uploads/students/{studentId}/`; deletes old file for same field if exists; both operations in caller's transaction. `::discard(int $rtcId): void` — deletes all files under `storage/uploads/rtc/temp/{rtcId}/` | 4 | M6-T01 | P1 | storeTemp: valid JPEG stored at temp path; bad MIME throws UploadException; commit: file moved to student folder, old file deleted; discard: temp directory removed |

---

## 4. Models

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M6-T05 | `ChangeRequest` model (`app/Models/ChangeRequest.php`) with methods: `create(array $data): int` — INSERT, returns new id; `findById(int $id): ?array` — with JOIN to students (name, enrolment_serial), users (initiator name); auto JSON-decodes proposed_changes; `findPending(int $departmentId): array` — all status='pending' rows for dept, ordered oldest first, with student + initiator name JOIN; `findByStudent(int $studentId): array` — all RTCs for a student, newest first; `hasPending(int $studentId): bool` — returns true if any status='pending' row exists for student; `approve(int $rtcId, int $reviewedBy): void` — transaction: UPDATE status='approved', reviewed_by, reviewed_at; call StudentProfile::applyChangeset(); call RtcUploadHandler::commit() if file entries present; call MasterAuditLogger; call NotificationEvent::record() for each recipient; `reject(int $rtcId, int $reviewedBy, string $reason): void` — transaction: UPDATE status='rejected', rejection_reason, reviewed_by, reviewed_at; call RtcUploadHandler::discard(); call MasterAuditLogger; call NotificationEvent::record() | 8 | M6-T01, M6-T07 | P1 | create + findById round-trip correct; findPending respects dept scope; hasPending true with one open RTC, false after approve/reject; approve writes changeset + updates status in same transaction; reject stores reason, calls discard |
| M6-T06 | `NotificationEvent` model (`app/Models/NotificationEvent.php`) — single static method `record(string $eventKey, int $studentId, int $actorId, string $recipientType, ?int $recipientId, ?int $changeRequestId, array $payload): void` — INSERT into notification_events; payload must contain only non-PII fields (field_keys list, dept_id, enrolment_serial — never names, mobile, Aadhaar); called from ChangeRequest::approve(), ChangeRequest::reject(), ApprovalController::approveSubmission() | 2 | M6-T02 | P1 | Row inserted with correct event_key and recipient_type; sent_at=NULL; JSON payload contains no PII fields |
| M6-T07 | `StudentProfile::applyChangeset(int $studentId, array $changeset): void` — single batch UPDATE: builds SET clause from changeset entries (scalar values directly; JSON qual fields: decode current, merge/replace row, re-encode); sets last_saved_at = `date('Y-m-d H:i:s')`; executes one prepared statement; called inside ChangeRequest::approve() transaction (does not open its own transaction) | 4 | M5-T01 | P1 | All proposed scalar values written in single UPDATE; JSON qual column merged correctly; last_saved_at updated; no partial write on failure (caller's transaction rolls back) |

---

## 5. Controllers & routes

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M6-T08 | `ApprovalController::index()` — `GET /approvals`; roles: dept_staff, dept_admin, institution_admin; dept-scoped (inst_admin: optional dept filter param); loads: (1) students with onboarding_status='form_submitted' for dept (pending approvals), (2) ChangeRequest::findPending($deptId) (pending RTCs); section counts in view; renders `approvals/index.php` with both datasets and optional ?dept filter for inst_admin | 4 | M6-T05 | P1 | Two sections render with correct data; dept_staff/dept_admin see only own dept; inst_admin sees all with dept filter working; empty state message when queue empty |
| M6-T09 | `ApprovalController::approveSubmission(int $studentId)` — `POST /approvals/{studentId}/approve`; roles: dept_staff, dept_admin, institution_admin; CSRF; dept-scope guard; loads student, verifies onboarding_status='form_submitted'; if already approved: flash info + redirect (idempotent); transaction: UPDATE students SET onboarding_status='approved', approval_by=Auth::id(), approval_at=now(); MasterAuditLogger; NotificationEvent::record() for student + dept_admin (event: 'submission_approved'); flash success + redirect to /approvals | 4 | M6-T06 | P1 | Status updated + audit log + notification events in one transaction; already-approved student → info flash, no duplicate audit; wrong-dept → 403; CSRF enforced |
| M6-T10 | `RtcController::createForm(int $studentId)` — `GET /rtc/create?student_id={id}`; roles: student (own record only), dept_staff, dept_admin, institution_admin; checks student form_status IN ('submitted') OR onboarding_status IN ('form_submitted','approved'); checks !ChangeRequest::hasPending($studentId) — if pending: flash info + redirect; loads StudentProfile::findByStudent() for current values; renders `approvals/rtc_form.php` with field list and current values pre-filled | 4 | M6-T05, M5-T08 | P1 | Student sees own form fields populated; hasPending blocks second RTC with info flash; student accessing other student's ID → 403; staff accesses dept-scoped; form renders editable fields (excludes onboarding-locked fields) |
| M6-T11 | `RtcController::store()` — `POST /rtc/create`; roles: student (own), dept_staff, dept_admin, institution_admin; CSRF; re-check hasPending; call RtcFieldHelper::buildChangeset() — validation errors → redirect back with flash; process file fields via RtcUploadHandler::storeTemp() and add is_file=true entries to changeset with temp_path; call ChangeRequest::create(); call NotificationEvent::record() per event key ('rtc_created_by_student' → dept_admin only; 'rtc_created_by_staff' → student + dept_admin); flash + redirect (student → /rtc/history; staff → /approvals) | 6 | M6-T03, M6-T04, M6-T05, M6-T06 | P1 | RTC record created with correct initiator_type; changeset stored as JSON; file temp path stored in changeset entry; notification events created with correct recipient_type; hasPending race condition caught (re-check after lock); CSRF enforced |
| M6-T12 | `RtcController::detail(int $rtcId)` — `GET /rtc/{id}`; roles: dept_staff, dept_admin, institution_admin; dept-scope guard on change_request.department_id; loads RTC with student + initiator JOIN; renders `approvals/rtc_detail.php` showing field comparison table (current vs proposed); shows approve/reject actions only when status='pending' | 3 | M6-T05 | P1 | Comparison table shows all changed fields with current and proposed values; file fields show filename link; actions hidden when status ≠ 'pending'; wrong-dept → 403 |
| M6-T13 | `RtcController::approve(int $rtcId)` — `POST /rtc/{id}/approve`; roles: dept_staff, dept_admin, institution_admin; CSRF; dept-scope guard; loads RTC; verifies status='pending'; call ChangeRequest::approve($rtcId, Auth::id()); flash "Changes applied for [Name]."; redirect to /approvals | 3 | M6-T05 | P1 | Profile updated; status='approved'; audit log and notification events created; non-pending RTC → flash info no-op; CSRF enforced |
| M6-T14 | `RtcController::reject(int $rtcId)` — `POST /rtc/{id}/reject`; roles: dept_staff, dept_admin, institution_admin; CSRF; dept-scope guard; validates rejection_reason present (required); call ChangeRequest::reject($rtcId, Auth::id(), $reason); flash "Change request rejected."; redirect to /approvals | 3 | M6-T05 | P1 | Status='rejected'; rejection_reason stored; no profile changes; temp files discarded; audit log + notification events created; missing rejection_reason → redirect back with validation flash |
| M6-T15 | `RtcController::studentHistory()` — `GET /rtc/history`; student role only; loads ChangeRequest::findByStudent(Auth::studentId()); renders `approvals/rtc_history.php`; newest first | 2 | M6-T05 | P1 | Student sees own RTCs only; status badges correct; rejection reason shown for rejected RTCs; empty state message |
| M6-T16 | Routes — register all M6 routes in `public/index.php`, grouped with comment `# M6 Submission & RTC`; static paths (e.g. `/rtc/history`) placed before parameterised paths (`/rtc/{id}`) to prevent wildcard match | 1 | M6-T08–M6-T15 | P1 | All 8 routes resolve; role violations return 403; CSRF on all POSTs; no route conflict with M5 student form routes |

---

## 6. Views

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M6-T17 | `approvals/index.php` — Bootstrap 5 page with two tabs: **Pending Approvals** and **Pending RTCs**; tab headers show pending counts (e.g. "Pending Approvals (3)"); Approvals tab: table with columns — Name, Enrolment No., Programme, Class, Submitted At, Actions (View, Approve button); RTCs tab: table with columns — Student, Initiator, Reason (truncated 60 chars), Raised By, Raised At, Actions (View RTC); inst_admin: dept filter dropdown at top; empty state per tab | 8 | M6-T08 | P1 | Both tabs render; counts accurate; Approve button on Approvals tab triggers POST with CSRF; View RTC links to rtc_detail; dept filter works for inst_admin; empty state message when queue empty |
| M6-T18 | `approvals/rtc_detail.php` — comparison table: Field Label | Current Value | Proposed Value; file fields show download link for current + proposed filename; RTC metadata header: student name, initiator type badge (Student / Staff), reason, raised by, raised at; if status='pending': Approve button (Bootstrap primary, with confirmation modal) + Reject button (Bootstrap danger, opens modal with required textarea for rejection reason); if status ≠ 'pending': status badge + reviewed_by + reviewed_at; back link to /approvals | 7 | M6-T12 | P1 | Comparison table renders all changed fields; file links open correctly; Approve/Reject only for pending; rejection modal requires reason; CSRF on both action forms |
| M6-T19 | `approvals/rtc_form.php` — field selector: checklist of editable profile fields grouped by section; on checkbox tick, an input row appears below (same input types as M5 show.php — text, select, date, file, number); current value shown as read-only next to each proposed-value input; reason textarea (required); submit labelled "Submit Change Request"; student sees own current values; staff sees same form with student name in header | 10 | M6-T10 | P1 | Checkbox-reveal JS works (show input row on tick); current value displayed alongside proposed input; file inputs limited to doc/photo MIME types; reason field required client-side; CSRF present; form method POST to /rtc/create with hidden student_id |
| M6-T20 | `approvals/rtc_history.php` — student-facing list of own RTCs; each row: reason, fields requested (comma-separated labels), status badge (Pending = amber, Approved = green, Rejected = red), Raised At, Reviewed At, rejection reason if status='rejected'; newest first; empty state "No change requests submitted yet." | 4 | M6-T15 | P1 | All RTC statuses display with correct badge colour; rejection reason shown only when rejected; reviewed_at blank for pending; empty state renders |
| M6-T21 | Update `student-form/readonly.php` (M5) — wire **Request a Change** button: if student's form_status='submitted' OR onboarding_status IN ('form_submitted','approved') AND !hasPending: link to `/rtc/create?student_id={id}`; if hasPending: replace button with amber info badge "A change request is already pending review."; if staff view: add **Raise Change Request** button (same link with staff role check) + show **Approve Submission** button when onboarding_status='form_submitted' (POST to /approvals/{studentId}/approve) | 4 | M6-T09, M6-T10 | P1 | Request a Change button appears for eligible student; hasPending shows info badge; staff sees both Raise Change Request and Approve Submission (when applicable); forms have CSRF; buttons hidden at wrong form_status |
| M6-T22 | Nav update (`layouts/app.php`) — add **Approvals** link for dept_staff, dept_admin, institution_admin roles pointing to /approvals; add **My Changes** link for student role pointing to /rtc/history | 1 | M6-T16 | P2 | Links appear for correct roles; active state highlighted on current page |

---

## 7. Tests

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M6-T23 | Unit: `RtcFieldHelperTest` — `buildChangeset`: (a) valid fields return correct changeset with current + proposed values; (b) onboarding-locked key (mobile) throws InvalidArgumentException; (c) unknown field_key throws; (d) no fields selected throws with 'No changes specified'; (e) field with same proposed value as current → excluded from changeset (no-op change) | 4 | M6-T03 | P1 | All assertions green |
| M6-T24 | Unit: `HasPendingRtcTest` — `ChangeRequest::hasPending`: false when no RTC for student; true when one pending RTC exists; false after RTC approved; false after RTC rejected | 2 | M6-T05 | P1 | Green |
| M6-T25 | Integration: `SubmissionApprovalTest` — (a) student with onboarding_status='form_submitted' → approve → status='approved'; audit_log row created; two notification_events rows created (student + dept_admin recipient_type); (b) already-approved student → approve again → no duplicate audit row; (c) dept_staff from different dept → 403 | 5 | M6-T09, M6-T06 | P1 | Green; audit_log has exactly one row per approval; notification_events has sent_at=NULL |
| M6-T26 | Integration: `RtcCreateStudentTest` — (a) student with form_status='submitted' creates RTC → change_requests row with initiator_type='student', status='pending', correct proposed_changes JSON; one notification_event for dept_admin; (b) second RTC attempt while one pending → hasPending guard triggers, no second row inserted; (c) student accessing another student's createForm → 403 | 5 | M6-T05, M6-T11 | P1 | Green |
| M6-T27 | Integration: `RtcCreateStaffTest` — staff creates RTC on a student in own dept → initiator_type='staff'; two notification_events (student + dept_admin); staff in dept B creates RTC on student in dept A → 403 | 3 | M6-T05, M6-T11 | P1 | Green |
| M6-T28 | Integration: `RtcApproveTest` — (a) approve RTC with scalar field changes → proposed values written to student_profiles (verify DB read); change_requests.status='approved'; audit_log has changed field keys (not values) in action; two notification_events created; (b) approve RTC on JSON qual field → qual column merged correctly; (c) approve already-approved RTC → no-op (idempotent); (d) wrong-dept approver → 403 | 7 | M6-T05, M6-T07, M6-T06 | P1 | Green; student_profiles updated atomically; audit_log entry has field_keys array without PII values |
| M6-T29 | Integration: `RtcRejectTest` — (a) reject with reason → status='rejected', rejection_reason stored; student_profiles unchanged; audit_log entry created; two notification_events; (b) reject without reason → validation error, status still 'pending'; (c) commit/discard path: RtcUploadHandler::discard called (spy/mock or temp dir cleared) | 5 | M6-T05, M6-T04, M6-T06 | P1 | Green; profile row unchanged after rejection |
| M6-T30 | Integration: `RtcScopeTest` — dept_staff cannot call /rtc/{id}/approve for RTC in another dept (403); dept_admin same; institution_admin can approve any dept's RTC | 3 | M6-T13 | P1 | Green |
| M6-T31 | Update `tests/bootstrap.php` — add `CREATE TABLE IF NOT EXISTS change_requests (...)` and `CREATE TABLE IF NOT EXISTS notification_events (...)` with SQLite-compatible DDL (no ENUM → use TEXT; no FK enforcement in SQLite — drop FK lines); add to `sis_test_schema()` function; also add `approval_by INT NULL, approval_at TEXT NULL` columns to students table for M6 submission approval tracking | 3 | — | P1 | All M6 integration tests run without MySQL; existing M1–M5 tests still green |

---

## 8. Database columns on existing tables

Two columns must be added to the `students` table to record submission approval:

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M6-T32 | Migration `022_alter_students_approval.sql` — `ALTER TABLE students ADD COLUMN approval_by INT NULL, ADD COLUMN approval_at DATETIME NULL`; FKs to users; no `IF NOT EXISTS` per convention | 1 | — | P1 | Columns present in MySQL 5.7; existing rows unaffected (NULL default) |

---

## 9. Build order (critical path)

1. **Data layer:** M6-T01 → M6-T02 → M6-T32
2. **Helpers (pure):** M6-T03 (RtcFieldHelper, no DB)
3. **Helpers (file):** M6-T04 (RtcUploadHandler)
4. **Models:** M6-T07 (applyChangeset, depends on M5 table) → M6-T06 (NotificationEvent) → M6-T05 (ChangeRequest — depends on both)
5. **Controllers:** M6-T08 → M6-T09 → M6-T10 → M6-T11 → M6-T12 → M6-T13 → M6-T14 → M6-T15 → M6-T16
6. **Views:** M6-T17 → M6-T18 → M6-T19 → M6-T20 → M6-T21 → M6-T22
7. **Tests:** M6-T23, M6-T24 (unit, alongside helpers); M6-T31 (bootstrap, before integration); M6-T25 → M6-T26 → M6-T27 → M6-T28 → M6-T29 → M6-T30 (integration, after controllers)

---

## 10. Estimate summary

| Group | Hours |
|-------|------:|
| Data layer (T01, T02, T32) | 5 |
| Helpers (T03, T04) | 8 |
| Models (T05, T06, T07) | 14 |
| Controllers & routes (T08–T16) | 30 |
| Views (T17–T22) | 34 |
| Tests (T23–T31) | 37 |
| **Total** | **~128 ideal hours (~16 dev-days)** |

---

## 11. Definition of Done

- All P1 tasks complete; all unit + integration tests green (`./vendor/bin/phpunit`) on PHP 8 / MySQL 5.7.
- Pending Approval queue shows `form_submitted` students; approving one sets `onboarding_status='approved'`, creates audit log entry, creates two notification_events (student + dept_admin).
- Student (submitted/approved) can create one RTC from their read-only view; a second RTC is blocked while one is pending.
- Staff can create an RTC from the staff read-only view; the RTC enters the pending RTCs queue.
- Approving an RTC writes all proposed_changes to `student_profiles` in one transaction; form_status and onboarding_status are not altered.
- Rejecting an RTC stores rejection_reason; `student_profiles` is unchanged; temp files are discarded.
- Notification events are created (sent_at=NULL) for all five event types; no PII in payload JSON.
- Audit log records field keys (not values) for RTC approval entries.
- All routes are dept-scoped; institution_admin bypasses dept filter; wrong-dept returns 403.
- CSRF enforced on all POSTs.
- Commit via `scripts/commit-module.sh "M6 Submission & Edit Approval: implementation complete"`; user pushes from Mac.

---

## 12. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, Module 6 is fully specified and ready for implementation in Claude Code.
