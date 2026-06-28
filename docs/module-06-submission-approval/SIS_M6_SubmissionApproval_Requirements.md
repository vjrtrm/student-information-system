# SIS — Module 6: Submission & Edit Approval (Request-to-Change)
## Stage 1: Requirements (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 6 of 12 — Submission & Edit Approval (Request-to-Change)
**Document stage:** **Requirements (this document)** → _Design_ → _Tasks_
**Version:** 0.2 (Revised) · June 2026 · **Status:** Awaiting review & approval
**Builds on:** Authentication (M1), Master Data (M2), Student Onboarding (M3), Enrolment Numbers (M4), Student Information Form (M5)

---

## 1. Purpose & objectives

Once a student submits their information form (M5), the record enters a staff review queue. A Department Staff member or Department Admin reviews the submitted form and approves it (marking the student's record as fully verified). If corrections are needed — either discovered by the student or noticed by staff — a Request-to-Change (RTC) is raised. The RTC itself carries the proposed field changes; on approval the changes are applied directly, with no form unlocking or re-submission cycle.

Objectives:

- Give Department Staff a queue of submitted student forms awaiting their review and approval.
- Allow a single authorised approver (any Dept Staff or Dept Admin) to approve a submitted form — no countersignature needed.
- Allow students or staff to raise an RTC that specifies the exact field changes required.
- On RTC approval, apply the proposed changes directly to the student's profile — no form unlock, no re-submission, no second approval cycle.
- On RTC rejection, close the request with a reason; the form remains unchanged.
- Fire notification events on every significant action (submission approved, RTC created, RTC approved, RTC rejected) — notifying both the student and the department admin; actual email/SMS delivery is M7, but M6 defines who is notified and records the event.
- Track every approval, RTC, and notification event in the audit log.

---

## 2. In scope

### 2.1 Submission approval

- After a student submits their form (`onboarding_status = form_submitted`), their record appears in the department's **Pending Approval** queue.
- Any Dept Staff or Dept Admin in the same department can open the read-only form view and click **Approve Submission**.
- On approval: `onboarding_status` → `approved`; approval details (who, when) recorded in the audit log.
- Institution Admin can approve submissions from any department.
- Once approved, the student's record shows as **Approved** in all staff views.
- No rejection action on submissions — if the submission has issues, staff raises an RTC (§2.2) which carries the corrected values.

### 2.2 Request-to-Change (RTC)

An RTC is a self-contained change proposal. The requester specifies both **which fields** need to change and **what the new values should be** at the time of raising the request. Staff review current values alongside proposed values and either apply or reject the change — no further round-trip to the student is needed.

#### Student-initiated RTC
- A student whose form is `form_submitted` or `onboarding_status = approved` can raise an RTC from their read-only form view.
- The RTC form presents the student's own field values from `student_profiles`. The student selects one or more fields, enters the corrected values, and provides a reason.
- On submission the RTC record stores: reason, initiator type (`student`), and the proposed changes as a structured list of `{field_key, current_value, proposed_value}` entries.
- The form remains locked; no changes are applied until staff approves.

#### Staff-initiated RTC
- Dept Staff, Dept Admin, or Institution Admin can raise an RTC from the staff read-only view of any `form_submitted` or `approved` student.
- Same RTC form — staff selects fields, enters corrected values, and provides a reason. Initiator type stored as `staff`.
- The RTC enters the Pending RTCs queue for a colleague's approval (single-approval rule applies).

#### RTC review and decision (by staff)
- Pending RTCs appear in the department's **Pending RTCs** queue.
- Any Dept Staff or Dept Admin (other than the initiator of a staff-raised RTC where practical — not enforced in v1) can approve or reject.
- **Approve RTC:** the proposed field values in the RTC are written directly to `student_profiles`; `change_requests.status` → `approved`; `onboarding_status` remains unchanged (stays `form_submitted` or `approved`); audit log entry created. The student's form stays in its locked state throughout.
- **Reject RTC:** opens a modal requesting a rejection reason (required); `change_requests.status` → `rejected`; no changes applied to `student_profiles`; audit log entry created.
- Only one RTC can be open (pending) per student at a time.

#### Key principle — no multi-hop
The RTC carries the complete correction at submission time. There is no intermediate unlock-edit-resubmit cycle. The state machine for `student_profiles.form_status` and `students.onboarding_status` does not change as a result of any RTC action.

### 2.3 RTC field scope

- The RTC form allows changes to any student-editable field in `student_profiles` (all fields from M5 sections 1–6).
- Pre-filled read-only fields from onboarding (first_name, last_name, dob, mobile) are **not** editable via RTC; those are onboarding records.
- Document/file fields: the RTC can include a new file upload for a document field; on approval the new file replaces the existing one.
- For JSON qualification fields (qual_sslc, qual_hsc, etc.), the entire qualification row is replaced on approval if included in the RTC.

### 2.4 Queue views

- **Pending Approval queue** (staff/admin): students with `onboarding_status = form_submitted`, sorted by submission date ascending. Columns: name, enrolment number (if assigned), programme, class, submitted at, actions (View, Approve).
- **Pending RTCs queue** (staff/admin): open RTCs for the department. Columns: student name, initiator type, reason summary, raised by, raised at, actions (View RTC detail, Approve, Reject).
- **RTC detail view**: shows the student's current profile values alongside the proposed values for each changed field, plus reason. Staff approves or rejects from this view.
- **My Change Requests** (student): own RTC history — reason, status (Pending / Approved / Rejected), raised at, reviewed at, rejection reason if applicable.
- Institution Admin sees all departments' queues with a department filter.

### 2.5 Notification events

M6 records a notification event for each action below. M7 reads these events and sends the actual emails (no PII in message body — code/link only, per locked decision). Notifications go to both the **student** and the **department admin**.

| Trigger | Notified parties | Event key |
|---------|-----------------|-----------|
| Submission approved | Student + Dept Admin | `submission_approved` |
| RTC created (student-initiated) | Dept Admin | `rtc_created_by_student` |
| RTC created (staff-initiated) | Student + Dept Admin | `rtc_created_by_staff` |
| RTC approved (changes applied) | Student + Dept Admin | `rtc_approved` |
| RTC rejected | Student + Dept Admin | `rtc_rejected` |

Events are written to a `notification_events` table (created in M6) with: event key, student_id, actor_id, payload (JSON — field change summary for RTC events, no PII), created_at, sent_at (NULL until M7 processes it).

### 2.6 Audit trail

Every submission approval, RTC creation, RTC approval, RTC rejection, and notification event creation is logged in `audit_log` via `MasterAuditLogger` with actor, action, entity, entity_id, and timestamp. Approved RTC audit entries record the list of changed field keys (not values, to avoid PII in logs).

---

## 3. Out of scope (this module)

- **Email/SMS delivery** — M7 reads the `notification_events` table and sends messages; M6 writes the events and defines recipients but does not send.
- **Countersignature** — single approval only.
- **Bulk submission approval** — per-student only in M6.
- **Onboarding field corrections** (first_name, last_name, dob, mobile) — not correctable via RTC; would require a separate onboarding correction flow outside this module.
- **Form unlock / re-submission cycle** — explicitly removed. RTCs apply changes directly on approval.
- **Per-field locking post-approval** — not implemented; all fields remain changeable via RTC after approval.

---

## 4. Roles involved

| Role | Capability |
|------|-----------|
| Student | Raise RTC on own submitted/approved form; view own RTC history |
| Department Staff | View pending approval queue; approve submissions; view and approve/reject RTCs; raise staff-initiated RTC |
| Department Admin | All of the above |
| Institution Admin | All of the above across all departments (with dept filter) |

---

## 5. Assumptions & dependencies

- **M5 (Student Information Form):** `student_profiles` and `students.onboarding_status` in place. No new ENUM values needed — RTC approval does not change form_status or onboarding_status.
- **Single-approval rule** (locked): any one authorised approver is sufficient.
- Only one pending RTC per student at a time; enforced server-side.
- RTC proposed values are validated on submission (same rules as M5 field validation) so invalid data is caught before the RTC enters the queue.
- Document uploads in an RTC are held in a temp path until the RTC is approved; on approval they are moved to the student's upload directory and the old file deleted.

---

## 6. Epics & user stories

### Epic A — Submission approval

**A1. View pending approval queue**
As a Department Staff member, I want to see all students whose forms are awaiting approval.

Acceptance criteria:

- Students with `onboarding_status = form_submitted` appear, sorted oldest submission first.
- Each row shows: name, enrolment number (if any), programme, class, submitted at.
- Clicking a row opens the read-only form (M5 staffView).
- Students already `approved` are not shown.

**A2. Approve a submitted form**
As a Department Staff member, I want to approve a student's submitted form so their record is marked as verified.

Acceptance criteria:

- An **Approve Submission** button is shown on the staff read-only form view when `onboarding_status = form_submitted`.
- Clicking shows a confirmation modal ("Approve [Name]'s submission?").
- On confirm: `onboarding_status` → `approved`; audit log entry created; student removed from queue.
- Flash: "Submission approved for [Name]."
- Already-approved student: button hidden; no duplicate audit entry.

---

### Epic B — Request-to-Change

**B1. Student raises an RTC with proposed corrections**
As a student, I want to propose specific corrections to my submitted form so staff can apply them directly.

Acceptance criteria:

- Given my form is `form_submitted` or `approved`, I see a **Request a Change** button on my read-only view.
- Clicking opens an RTC form: I select which fields to change from a list of my form's editable fields, enter the new values (same input types as the original field), and provide a reason (required).
- On submission: RTC record created with status `pending`, storing `{field_key, current_value, proposed_value}` for each changed field.
- Confirmation: "Your change request has been submitted for staff review."
- If a pending RTC already exists: button replaced by "A change request is already pending review."
- If form not yet submitted: button not shown.

**B2. Staff raises an RTC with proposed corrections**
As a Department Staff member, I want to raise an RTC on a student's record with the corrected values so it can be applied after one approval.

Acceptance criteria:

- From the staff read-only view of a `form_submitted` or `approved` student, I can click **Raise Change Request**.
- Same RTC form as B1; initiator type = `staff`.
- RTC enters the Pending RTCs queue for a colleague to approve.

**B3. Staff reviews RTC detail and approves or rejects**
As a Department Staff member, I want to review the proposed changes side-by-side with current values and apply or reject them.

Acceptance criteria:

- The RTC detail view shows: reason, initiator, raised at, and a comparison table: Field | Current Value | Proposed Value.
- **Approve:** proposed values written to `student_profiles`; RTC status → `approved`; audit log records changed fields; flash: "Changes applied for [Name]."; no change to form_status or onboarding_status.
- **Reject:** modal asks for rejection reason (required); RTC status → `rejected`; no profile changes; audit log entry; flash: "Change request rejected."
- After either action, RTC removed from Pending RTCs queue.

**B4. Student views RTC history**
As a student, I want to see my change requests and their outcomes.

Acceptance criteria:

- **My Change Requests** section shows all RTCs: reason, fields requested, status badge (Pending / Approved / Rejected), raised at, reviewed at, rejection reason if applicable.
- Sorted most-recent first.
- For approved RTCs, the changed fields and their new values are shown.

---

### Epic C — Staff visibility

**C1. Department-level queue views**
As a Department Staff member, I want a combined view of pending approvals and pending RTCs for my department.

Acceptance criteria:

- Two tabs or sections on one page: Pending Approvals and Pending RTCs.
- Filterable by academic year and programme.
- Section headers show pending counts.

**C2. Institution Admin cross-department view**
As an Institution Admin, I want to see pending approvals and RTCs across all departments.

Acceptance criteria:

- Department column and filter present.
- Can approve submissions and act on RTCs from any department.

---

## 7. Non-functional requirements (module-relevant)

- **Atomicity:** applying an approved RTC (writing proposed values + updating RTC status + audit_log) is a single transaction. Submission approval (onboarding_status + audit_log) is a single transaction.
- **Validation on RTC submission:** proposed values validated against the same rules as M5 (type, max length, MIME/size for files) before the RTC is accepted.
- **Security:** CSRF on all POSTs; role guard on all routes; dept-scope enforced.
- **Idempotency:** approving an already-approved submission shows a flash info message without creating a duplicate audit entry.
- **One-open-RTC rule:** enforced server-side; new RTC blocked if one is already pending for the student.

---

## 8. Open questions

1. **Queue counts in nav badge:** combined count or separate badges for approvals vs RTCs? Defer to M8 Dashboards?
2. **File uploads in RTC:** confirmed in scope (§2.3). Temp storage path for unapproved uploads: use `storage/uploads/rtc/{rtc_id}/` until approved, then move to student's folder?
3. **Staff self-approval:** not enforced in v1 — a staff member who raises a staff-initiated RTC may approve it themselves (single-approval rule; no prohibition).
4. **RTC on incomplete form:** out of scope — RTC only for `form_submitted` or `approved` students. *(resolved)*
5. **Unlimited RTC rounds:** yes, unlimited sequential RTCs allowed. *(resolved)*

---

## 9. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, the next step is **Stage 2: Design** — data model (`change_requests` table, proposed-change storage, state transitions), processing flows, RBAC, screen behaviour, and traceability.
