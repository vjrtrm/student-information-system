# SIS — Module 6: Submission & Edit Approval (Request-to-Change)
## Stage 2: Design (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 6 of 12 — Submission & Edit Approval (Request-to-Change)
**Document stage:** Requirements ✅ → **Design (this document)** → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M6_SubmissionApproval_Requirements.md`

---

## 1. Design goals

Translate the approved M6 requirements into a buildable design on the SIS stack (PHP 8.x MVC, MySQL 5.7, Bootstrap 5, PDO). Key goals:

- Submission approval: single POST action, single transaction, no new states.
- RTC: proposed changes stored as structured JSON at creation time; approved changes written directly to `student_profiles` without any form unlock.
- Notification events written to `notification_events` table (M7 will consume); no PII in payload.
- Audit log entry for every state-changing action, recording field keys (not values) for RTC changes.
- Reuse M5 `FormFieldRules`, `DocumentUploadHandler`, and `StudentProfile` patterns throughout.

---

## 2. Resolved design decisions (from open questions)

| # | Open question | Decision |
|---|---------------|----------|
| 1 | Nav badge counts | Pending counts shown as inline badges on the Approvals page section headers. Full nav badge deferred to M8 Dashboards. |
| 2 | RTC file upload temp path | Held at `storage/uploads/rtc/temp/{rtc_id}/{field_key}_{timestamp}.ext` until RTC is approved; on approval moved to `storage/uploads/students/{studentId}/` and old student file deleted. On rejection, temp file deleted. |
| 3 | Staff self-approval | Not enforced in v1; single-approval rule applies — the same staff member who raised a staff-initiated RTC may approve it. |

---

## 3. Component architecture (MVC)

```
Controllers/
  ApprovalController.php     // queue (index), approve submission
  RtcController.php          // create (GET form + POST), detail, approve, reject,
                             //   studentHistory

Models/
  ChangeRequest.php          // create, find, findPending, findByStudent,
                             //   approve (writes proposed values), reject
  NotificationEvent.php      // record — inserts rows; M7 reads + sends

Helpers/
  RtcFieldHelper.php         // buildChangeset(proposed[], profile): array
                             //   validates proposed values, builds
                             //   [{field_key, current_value, proposed_value}]
  RtcUploadHandler.php       // wraps DocumentUploadHandler for temp RTC path;
                             //   commit(rtcId, studentId), discard(rtcId)

Views/
  approvals/
    index.php      // two-tab page: Pending Approvals + Pending RTCs
    rtc_detail.php // side-by-side current vs proposed + approve/reject actions
    rtc_form.php   // RTC creation form (student + staff share same template)
    rtc_history.php // student My Change Requests page
```

Request path: `public/index.php` → router → `AuthMiddleware` → `RoleMiddleware` → Controller → view.

---

## 4. Data model

### 4.1 New table: `change_requests`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `student_id` | INT FK→students.id NOT NULL | |
| `department_id` | INT FK→departments.id NOT NULL | denormalised for queue queries |
| `initiated_by` | INT FK→users.id NOT NULL | student or staff user |
| `initiator_type` | ENUM('student','staff') NOT NULL | |
| `reason` | TEXT NOT NULL | |
| `proposed_changes` | JSON NOT NULL | array of `{field_key, label, current_value, proposed_value, is_file}` |
| `status` | ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' | |
| `rejection_reason` | TEXT NULL | populated on reject |
| `reviewed_by` | INT FK→users.id NULL | |
| `reviewed_at` | DATETIME NULL | |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP | |

Indexes:
```sql
KEY idx_cr_student   (student_id)
KEY idx_cr_dept      (department_id)
KEY idx_cr_status    (status)
KEY idx_cr_dept_status (department_id, status)
CONSTRAINT fk_cr_student  FOREIGN KEY (student_id)   REFERENCES students(id)
CONSTRAINT fk_cr_dept     FOREIGN KEY (department_id) REFERENCES departments(id)
CONSTRAINT fk_cr_init     FOREIGN KEY (initiated_by)  REFERENCES users(id)
CONSTRAINT fk_cr_reviewer FOREIGN KEY (reviewed_by)   REFERENCES users(id)
```

**`proposed_changes` JSON schema** (array, one entry per changed field):
```json
[
  {
    "field_key":      "mother_name",
    "label":          "Mother's Name",
    "current_value":  "Rani",
    "proposed_value": "Rani Kumar",
    "is_file":        false
  },
  {
    "field_key":      "community_cert_path",
    "label":          "Community Certificate",
    "current_value":  "storage/uploads/students/12/community_cert_1719500000.pdf",
    "proposed_value": "storage/uploads/rtc/temp/7/community_cert_1719510000.pdf",
    "is_file":        true
  }
]
```

For file fields: `current_value` and `proposed_value` are relative paths. On approval, the temp path is moved and `proposed_value` updated to the final path before writing to `student_profiles`.

### 4.2 New table: `notification_events`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `event_key` | VARCHAR(60) NOT NULL | e.g. `submission_approved`, `rtc_approved` |
| `student_id` | INT FK→students.id NOT NULL | |
| `actor_id` | INT FK→users.id NULL | who triggered the event |
| `recipient_type` | ENUM('student','dept_admin') NOT NULL | one row per recipient type |
| `recipient_id` | INT FK→users.id NULL | specific admin user; NULL = any dept admin |
| `change_request_id` | INT FK→change_requests.id NULL | linked RTC if applicable |
| `payload` | JSON NULL | non-PII context: field_keys changed, dept_id, enrolment number |
| `sent_at` | DATETIME NULL | NULL = pending M7 delivery |
| `created_at` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP | |

Indexes:
```sql
KEY idx_ne_student  (student_id)
KEY idx_ne_sent_at  (sent_at)
KEY idx_ne_event    (event_key)
CONSTRAINT fk_ne_student FOREIGN KEY (student_id)        REFERENCES students(id)
CONSTRAINT fk_ne_actor   FOREIGN KEY (actor_id)           REFERENCES users(id)
CONSTRAINT fk_ne_cr      FOREIGN KEY (change_request_id)  REFERENCES change_requests(id)
```

### 4.3 No changes to existing tables

`students.onboarding_status` and `student_profiles.form_status` are **not** modified by any RTC action. Submission approval updates `onboarding_status` (already in scope — the ENUM value `approved` exists from M3).

---

## 5. Processing flows

### 5.1 Submission approval

```
Staff → POST /approvals/{studentId}/approve
  ApprovalController::approve($studentId)
    1. requireCsrf(); RoleMiddleware(['staff','dept_admin','institution_admin']).
    2. Student::find($studentId); assertDepartment($student['department_id']).
    3. Guard: if onboarding_status ≠ 'form_submitted' → flash info + redirect (idempotent).
    4. BEGIN TRANSACTION:
         UPDATE students SET onboarding_status='approved' WHERE id=?
         MasterAuditLogger::log('submission_approved','student',$studentId,[actor_id])
         NotificationEvent::record('submission_approved', $studentId, Auth::id(), [
           ['recipient_type'=>'student'],
           ['recipient_type'=>'dept_admin'],
         ])
    5. COMMIT.
    6. Flash success: "Submission approved for {Name}."
    7. Redirect → GET /approvals
```

### 5.2 RTC creation (student or staff)

```
User → GET /rtc/create?student_id={id}
  RtcController::createForm($studentId)
    1. Role check: student (own ID only) or staff/admin.
    2. StudentProfile::findByStudent($studentId) → $profile.
    3. Guard: onboarding_status must be 'form_submitted' or 'approved'; else 404/403.
    4. Guard: ChangeRequest::hasPending($studentId) → if true, flash danger + redirect.
    5. FormFieldRules::getApplicableFields($profile, $student) → $rules
       Filter to editable (non-readonly) fields only.
    6. render('approvals/rtc_form', [profile, rules, student])

User → POST /rtc/create
  RtcController::store()
    1. requireCsrf(); role + ownership check.
    2. $studentId = (int)$_POST['student_id'].
    3. Guard: hasPending again (race-condition re-check).
    4. $selectedKeys = $_POST['field_keys'] (array of field_key strings).
       Must be non-empty; validate each key exists in applicable rules.
    5. RtcFieldHelper::buildChangeset($selectedKeys, $_POST, $_FILES, $profile, $rules)
         a. For each selected field:
              - Validate proposed value (same rules as M5 save for type/length/MIME/size).
              - For file fields: RtcUploadHandler::storeTemp($fieldKey, $_FILES[$fieldKey])
                → returns temp path; stored as proposed_value.
              - Build entry: {field_key, label, current_value, proposed_value, is_file}.
         b. Returns array of entries (changeset) or throws ValidationException on error.
    6. ChangeRequest::create([
           student_id, department_id, initiated_by=Auth::id(),
           initiator_type=('student'|'staff'), reason, proposed_changes=json_encode($changeset)
       ]) → $rtcId
    7. NotificationEvent::record('rtc_created_by_{type}', $studentId, Auth::id(), recipients)
       - student-initiated: notify dept_admin only.
       - staff-initiated: notify student + dept_admin.
    8. MasterAuditLogger::log('rtc_created','change_request',$rtcId,[field_keys only]).
    9. Flash success: "Your change request has been submitted for staff review."
   10. Redirect: student → /rtc/history; staff → /approvals.
```

### 5.3 RTC approval (staff)

```
Staff → POST /rtc/{id}/approve
  RtcController::approve($rtcId)
    1. requireCsrf(); RoleMiddleware(['staff','dept_admin','institution_admin']).
    2. ChangeRequest::find($rtcId) → $rtc. Guard: status='pending'; else flash + redirect.
    3. assertDepartment($rtc['department_id']).
    4. $changeset = json_decode($rtc['proposed_changes'], true).
    5. BEGIN TRANSACTION:
         a. For each entry in $changeset:
              - If is_file=true:
                  RtcUploadHandler::commit(temp_path, $studentId, $fieldKey)
                    → moves file; deletes old student file; returns final path.
                  Update entry proposed_value to final path.
              - StudentProfile::setField($studentId, $fieldKey, $proposedValue).
                  (single UPDATE per field, or batch UPDATE — see §5.5)
         b. UPDATE change_requests SET status='approved', reviewed_by=?, reviewed_at=NOW()
         c. MasterAuditLogger::log('rtc_approved','change_request',$rtcId,
                [actor_id, field_keys_changed])
         d. NotificationEvent::record('rtc_approved', $studentId, Auth::id(), [
                ['recipient_type'=>'student'],
                ['recipient_type'=>'dept_admin'],
            ], $rtcId)
    6. COMMIT.
    7. Flash: "Changes applied for {Name}."
    8. Redirect → GET /approvals
```

### 5.4 RTC rejection (staff)

```
Staff → POST /rtc/{id}/reject
  RtcController::reject($rtcId)
    1. requireCsrf(); RoleMiddleware(['staff','dept_admin','institution_admin']).
    2. ChangeRequest::find($rtcId). Guard: status='pending'.
    3. assertDepartment.
    4. $rejectionReason = trim($_POST['rejection_reason']). Guard: non-empty.
    5. BEGIN TRANSACTION:
         a. RtcUploadHandler::discard($rtcId)  ← deletes any temp files for this RTC.
         b. UPDATE change_requests SET status='rejected', rejection_reason=?,
                  reviewed_by=?, reviewed_at=NOW()
         c. MasterAuditLogger::log('rtc_rejected','change_request',$rtcId,[actor_id])
         d. NotificationEvent::record('rtc_rejected', $studentId, Auth::id(), [
                ['recipient_type'=>'student'],
                ['recipient_type'=>'dept_admin'],
            ], $rtcId)
    6. COMMIT.
    7. Flash: "Change request rejected."
    8. Redirect → GET /approvals
```

### 5.5 `StudentProfile::setField` (batch write on RTC approval)

Rather than one UPDATE per field, build a single UPDATE from the approved changeset:

```php
// Pseudocode — implemented in StudentProfile model
public static function applyChangeset(int $studentId, array $changeset): void
{
    $setClauses = [];
    $params     = [];
    foreach ($changeset as $entry) {
        $key = $entry['field_key'];
        // JSON qual fields: store as JSON string
        $val = in_array($key, JSON_COLUMNS) && is_array($entry['proposed_value'])
             ? json_encode($entry['proposed_value'])
             : $entry['proposed_value'];
        $setClauses[] = "`{$key}` = ?";
        $params[]     = $val;
    }
    $params[] = $studentId;
    Db::execute(
        "UPDATE student_profiles SET " . implode(', ', $setClauses) . " WHERE student_id = ?",
        $params
    );
}
```

---

## 6. RBAC & department scoping

| Route | Allowed roles | Dept scope |
|-------|--------------|------------|
| `GET /approvals` | staff, dept_admin, institution_admin | own dept; inst_admin sees all |
| `POST /approvals/{studentId}/approve` | staff, dept_admin, institution_admin | dept-scoped |
| `GET /rtc/create?student_id={id}` | student (own), staff, dept_admin, institution_admin | student: own; staff: dept-scoped |
| `POST /rtc/create` | student (own), staff, dept_admin, institution_admin | same |
| `GET /rtc/{id}` | staff, dept_admin, institution_admin | dept-scoped |
| `POST /rtc/{id}/approve` | staff, dept_admin, institution_admin | dept-scoped |
| `POST /rtc/{id}/reject` | staff, dept_admin, institution_admin | dept-scoped |
| `GET /rtc/history` | student | own records only |

Student ownership: `$_POST['student_id']` must equal `Auth::id()` when role is `student`.

---

## 7. Validation & security

| Concern | Handling |
|---------|---------|
| CSRF | `requireCsrf()` on all POSTs |
| Proposed value validation | Same rules as M5 `sanitiseInput` — type, max length, MIME/size for files |
| One-pending-RTC rule | `ChangeRequest::hasPending($studentId)` checked on form load AND on POST (race-condition guard) |
| Student accessing another student's form | `Auth::id() === $studentId` enforced server-side |
| File field in RTC | Same MIME whitelist + 2 MB cap as M5; stored in temp path until approval |
| Temp file cleanup on rejection | `RtcUploadHandler::discard()` deletes all temp files for the RTC in the rejection transaction |
| Audit log PII | Field keys logged, not values; notification payload contains only field_keys, dept_id, enrolment number — no names, mobile, Aadhaar |

---

## 8. Screen behaviour & messages

### 8.1 Approvals index (`approvals/index.php`)

Two Bootstrap tabs on one page:

**Tab 1 — Pending Approvals** `(N)`
- Table: Student Name, Enrolment No., Programme, Class, Submitted At, [View & Approve] button.
- Filter bar: Academic Year, Programme Level.
- "View & Approve" opens the M5 `staffView` read-only form; Approve Submission button shown at top of that page.
- Empty state: "No submissions pending approval."

**Tab 2 — Pending RTCs** `(N)`
- Table: Student Name, Initiator (Student / Staff), Reason (truncated 80 chars), Raised By, Raised At, [View] button.
- "View" opens `rtc_detail.php`.
- Empty state: "No change requests pending."

Institution Admin sees a Department filter above both tabs.

### 8.2 RTC detail view (`approvals/rtc_detail.php`)

- Header: student name, enrolment number, RTC reason, initiator type badge, raised at.
- Comparison table:

| Field | Current Value | Proposed Value |
|-------|--------------|----------------|
| Mother's Name | Rani | Rani Kumar |
| Community Certificate | [View current] | [View proposed] |

- File fields: both current and proposed shown as "View Document" links (open in new tab).
- **Approve Changes** button (green) → Bootstrap confirmation modal: "Apply these changes to {Name}'s profile?" with field count.
- **Reject** button (red) → Bootstrap modal with rejection reason textarea (required).
- Staff-initiated RTCs show initiator name in header.

### 8.3 RTC creation form (`approvals/rtc_form.php`)

- Shared by student and staff (controller passes `$isStaff` flag).
- Step 1: Reason textarea (required).
- Step 2: Field selector — a list of all editable profile fields (grouped by section), each with a checkbox. Checking a field reveals its current value (read-only) and an editable input (same type as M5 form) for the proposed value.
- File fields: show current filename + an Upload button for the replacement.
- Submit button disabled until at least one field is selected and reason filled.
- Student view heading: "Request a Change". Staff view heading: "Raise Change Request for {Student Name}".

### 8.4 Student RTC history (`approvals/rtc_history.php`)

- Card list: each RTC shows — reason, fields changed (key labels), status badge (Pending amber / Approved green / Rejected red), raised at, reviewed at.
- Rejected cards show rejection reason.
- Approved cards show a summary of which fields were changed.
- "Request a Change" button at top (links to rtc_form; hidden if pending RTC exists).

### 8.5 Flash messages

| Event | Type | Message |
|-------|------|---------|
| Submission approved | success | "Submission approved for {Name}." |
| Already approved (idempotent) | info | "{Name}'s submission is already approved." |
| RTC created | success | "Your change request has been submitted for staff review." |
| RTC already pending | danger | "A change request is already pending review for this student." |
| RTC approved | success | "Changes applied for {Name}." |
| RTC rejected | success | "Change request rejected." |
| No fields selected in RTC | danger | "Please select at least one field to change." |

---

## 9. Configuration parameters

No new config keys. Reuses `form.upload_max_bytes`, `form.upload_allowed_doc_mimes`, `form.upload_allowed_photo_mimes` from `config/form.php`. RTC temp path: `storage/uploads/rtc/temp/{rtcId}/` (derived at runtime, no config needed).

---

## 10. Edge cases

| Scenario | Handling |
|----------|---------|
| RTC submitted for a field not in the student's applicable rule set (e.g. qual_ug for a UG student) | `RtcFieldHelper::buildChangeset` validates each field_key against `FormFieldRules::getApplicableFields`; invalid keys rejected with validation error. |
| Student raises RTC immediately after another is approved (race) | `hasPending` only checks `status='pending'`; approved/rejected RTCs don't block a new one. Multiple sequential RTCs are allowed. |
| File in approved RTC temp path missing on disk | `RtcUploadHandler::commit` checks file exists before moving; if missing, logs warning and skips file move (scalar value still written). Acceptable for v1. |
| Concurrent approve + reject on same RTC | Transaction + status guard (`WHERE status='pending'`) ensures only one wins; second gets a flash info "This request has already been reviewed." |
| Student changes family_situation in RTC (affects which other fields are required) | `proposed_changes` stores the explicit field changes only; no re-validation of downstream required fields. The profile is updated directly with specified fields. Acceptable — full re-validation would require a complete profile re-submit. |
| Dept admin receiving notification — which admin? | `recipient_id = NULL` in `notification_events` with `recipient_type = 'dept_admin'`; M7 queries the department's admin user(s) at send time. |

---

## 11. Traceability

| Requirement | Design element |
|-------------|---------------|
| A1 — Pending approval queue | §8.1 Tab 1; `ApprovalController::index()` queries `onboarding_status='form_submitted'` |
| A2 — Approve submission | §5.1 `ApprovalController::approve()`; single transaction; audit log |
| B1 — Student raises RTC with proposed corrections | §5.2 `RtcController::store()`; `RtcFieldHelper::buildChangeset`; JSON `proposed_changes` |
| B2 — Staff raises RTC | §5.2 same flow; `initiator_type='staff'`; notification to student + admin |
| B3 — Staff approves RTC, changes applied directly | §5.3 `RtcController::approve()`; `StudentProfile::applyChangeset()`; no form unlock |
| B3 — Staff rejects RTC | §5.4 `RtcController::reject()`; temp file discard; rejection reason stored |
| B4 — No re-submission cycle | §4.3 — no changes to `form_status` or `onboarding_status` on any RTC action |
| B5 — Student RTC history | §8.4 `rtc_history.php`; `ChangeRequest::findByStudent()` |
| §2.5 — Notification events | §5.1–5.4 each call `NotificationEvent::record()`; `notification_events` table §4.2 |
| §2.6 — Audit log | `MasterAuditLogger::log()` in every flow; field keys only, no values |
| C1 — Dept queue view | §8.1 two-tab page; pending counts in tab labels |
| C2 — Institution Admin | role guard bypass on dept scope; dept filter on index |
| One-open-RTC rule | `ChangeRequest::hasPending()` checked on form load + POST |
| File in RTC | `RtcUploadHandler` (temp → commit/discard); §7 validation |
| No PII in audit/notifications | §7; notification payload stores field_keys + dept_id only |

---

## 12. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, the next step is **Stage 3: Tasks** — a task-by-task breakdown with estimates, dependencies, and done-when criteria, submitted for your review before implementation.
