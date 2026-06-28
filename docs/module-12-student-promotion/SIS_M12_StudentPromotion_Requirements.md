# SIS — Module 12: Student Promotion
## Stage 1: Requirements (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 12 of 12 — Student Promotion
**Document stage:** **Requirements (this document)** → _Design_ → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Builds on:** Authentication (M1), Master Data (M2), Student Onboarding (M3), Enrolment Numbers (M4), Student Information Form (M5), Student Data Grid (M11)

---

## 1. Purpose & objectives

At the end of each academic year (typically June), students who have successfully completed the year must be promoted — their academic year, class, and section updated to the next year's values, and their student form reset to allow re-submission for the new year. Module 12 provides a controlled, bulk promotion workflow that Department Staff can initiate and Department Admin (or Institution Admin) must approve before any records are changed.

Objectives:

- Allow Department Staff to identify all **eligible** students for promotion and initiate a bulk promotion run for their department.
- Require **Department Admin or Institution Admin approval** before any student record is updated (consistent with the single-approval model used in M4 and M6).
- On approval, update each promoted student's `academic_year_id`, `class_id`, `section_id` to the target values and reset their form status so they can re-submit updated information for the new year.
- Allow students to be **individually excluded** from a promotion batch before it is approved (e.g. detained students, students on leave, dropouts).
- Maintain a full **audit trail** of every promotion batch and each student's inclusion or exclusion.
- Restrict promotion operations to a configurable **window** (June by default; Institution Admin can open or close the window manually).

---

## 2. In scope

### 2.1 Promotion eligibility

A student is eligible for promotion if:
- `onboarding_status` = `active` (has completed onboarding and is not a dropout or transfer-out).
- `enrolment_approval_status` = `approved` (has an approved enrolment number).
- Not already promoted in the **current promotion batch cycle** (a student cannot be promoted twice in the same window).

Students who do not meet all three criteria appear in a separate "ineligible" list, shown for visibility but excluded from the batch automatically.

### 2.2 Promotion window

- Promotion is only available when the promotion window is **open**.
- Institution Admin can **open** or **close** the window manually from a settings page (no automatic date trigger in v1).
- When the window is closed, the "Create Promotion Batch" button is hidden and the route returns a 403-equivalent message.
- The window state is stored in a simple `settings` table (key-value) as `promotion_window_open = 1|0`.

### 2.3 Target academic year, class, and section

- When creating a promotion batch, the initiating staff member selects:
  - **Target academic year** — from the existing `option_values` list (academic_year list key).
  - **Target class** — from the existing `option_values` list (class list key).
  - **Target section** — from the existing `option_values` list (section list key).
- All students in the batch receive the same target academic year, class, and section. (Per-student target values are out of scope in v1.)
- The target values must be different from the students' current values (prevents accidental same-year promotion).

### 2.4 Promotion batch workflow

1. **Initiate** — Staff selects target year/class/section; system identifies eligible students for their department; staff can exclude individual students (with an exclusion reason); staff submits the batch for approval.
2. **Pending** — Batch is in `pending_approval` status; Admin is notified (via existing notification system if wired, or visible in the Promotion module).
3. **Approve** — Dept Admin or Institution Admin reviews the batch, sees the list of students and excluded students, and approves or rejects.
4. **Execute** — On approval, the system updates each included student's `academic_year_id`, `class_id`, `section_id`; resets `form_status` to `incomplete` and `form_completion_pct` to 0; sets a `promoted_at` timestamp; logs to audit_log.
5. **Reject** — On rejection, the batch is marked `rejected` with a reason; no student records are changed; staff may create a new batch.

Only one batch per department may be in `pending_approval` at a time.

### 2.5 Exclusions

- On the batch creation form, staff see the list of eligible students with a checkbox per student (checked = include; unchecked = exclude).
- A free-text **exclusion reason** field appears when a student is unchecked.
- Exclusion reason is stored per-student in the `promotion_exclusions` table.
- Excluded students are shown on the batch detail page with their reason.
- Excluded students are not updated on approval; their records remain unchanged.

### 2.6 Post-promotion student state

On approval and execution, for each **included** student:
- `students.academic_year_id` → target_academic_year_id
- `students.class_id` → target_class_id
- `students.section_id` → target_section_id
- `student_profiles.form_status` → `'incomplete'`
- `student_profiles.form_completion_pct` → `0`
- `student_profiles.form_submitted_at` → `NULL`
- `student_profiles.last_saved_at` → `NULL`
- `students.onboarding_status` remains `active`
- Enrolment number and all existing profile data (personal, family, etc.) are **preserved** — only the form status and academic year/class/section are updated.

### 2.7 Promotion batch management UI

- **Promotion index** (`/promotion`) — lists all promotion batches for the dept (staff/dept_admin) or all departments (institution_admin); shows status, target year, student count, initiated by, date.
- **Create batch** (`/promotion/create`) — staff-only; form to select target year/class/section; eligible student checklist with exclusion reasons; submit for approval.
- **Batch detail** (`/promotion/{id}`) — read-only view for all roles; shows included students, excluded students with reasons, batch status, initiator, approver.
- **Approve/Reject** (`POST /promotion/{id}/approve`, `POST /promotion/{id}/reject`) — dept_admin and institution_admin only; reject requires a reason.
- **Promotion window toggle** (`POST /promotion/window/toggle`) — institution_admin only; opens or closes the promotion window.

### 2.8 Notifications

- On batch submission: a notification event is created for dept_admin (and institution_admin) that a promotion batch is pending approval (uses M7 NotificationProcessor).
- On approval/rejection: a notification event is created for the initiating staff member.
- Notification payload: batch ID, department, target year — no PII.

---

## 3. Out of scope (this module)

- Per-student target academic year/class/section — all students in a batch share the same target values.
- Automatic window opening by date — manual toggle only in v1.
- Re-promotion (promoting a student who was already promoted this cycle) — blocked.
- Programme completion / graduation marking — out of scope.
- Bulk exclusion (exclude all and pick individuals) — include-by-default with per-student opt-out.
- Rollback of an approved promotion — no undo in v1.
- Integration with exam results or marks systems — eligibility is purely onboarding + enrolment status.

---

## 4. Roles involved

| Role | Access |
|------|--------|
| Student | None |
| Staff | Create promotion batch; view own department's batches |
| Department Admin | Approve/Reject batch; view own department's batches |
| Institution Admin | Approve/Reject any batch; view all batches; toggle promotion window |

---

## 5. Assumptions & dependencies

- `option_values` for academic_year, class, and section already exist from M2.
- M7 notification events and `NotificationProcessor` are available for batch-submit and approve/reject events.
- A `settings` table (key TEXT PK, value TEXT) will be created by this module's migration; it is simple enough to not require a separate Master Data module.
- The promotion batch execution (updating student records) runs synchronously on the web request for the expected dataset size (hundreds of students per dept); no background job needed in v1.
- `student_profiles` rows always exist for students being promoted (they were submitted and approved at least once, so a profile row exists).
- Class and section values stored in `students.class_id` and `students.section_id` are option_value IDs (integers), consistent with how M3 onboarding stores them.

---

## 6. Epics & user stories

### Epic A — Promotion batch creation

**A1. Staff initiates a promotion batch**
As a department staff member, I want to create a promotion batch for my department so that eligible students can be moved to the next academic year after admin approval.

Acceptance criteria:
- Given the promotion window is open and I visit `/promotion/create`, then I see the target year/class/section selectors and a checklist of eligible students.
- Given I submit the form with all students included, then a batch is created in `pending_approval` status and I see a confirmation.
- Given I try to create a batch while another is already pending approval for my department, then the system shows an error and blocks the second batch.
- Given the promotion window is closed, then the Create Batch page shows a "Promotion window is currently closed" message and I cannot submit.

**A2. Staff excludes individual students**
As a staff member, I want to mark individual students as excluded from the batch so that detained or withdrawn students are not promoted.

Acceptance criteria:
- Given I uncheck a student on the create batch form, then a reason field appears for that student.
- Given I submit with a student excluded and no reason provided, then the form shows a validation error.
- Given I submit with a valid exclusion reason, then that student is recorded in `promotion_exclusions` and not updated on approval.

### Epic B — Approval workflow

**B1. Admin reviews and approves a promotion batch**
As a department admin, I want to review the list of students in a pending promotion batch and approve it so that their records are updated.

Acceptance criteria:
- Given a batch is pending approval, when I visit `/promotion/{id}`, then I see included and excluded students with all relevant details.
- Given I approve the batch, then all included students' academic_year, class, and section are updated; their form_status is reset to incomplete.
- Given I approve, then the batch status changes to `approved` and the initiating staff member is notified.
- Given I reject with a reason, then no student records are changed and the batch status changes to `rejected`.

**B2. Institution Admin approves across departments**
As an institution admin, I want to see and approve promotion batches for any department so that I can act when a dept admin is unavailable.

Acceptance criteria:
- Given I visit `/promotion`, then I see batches from all departments.
- Given I approve a batch for Department B, then only Department B's students are updated.

### Epic C — Window management

**C1. Institution Admin opens and closes the promotion window**
As an institution admin, I want to open and close the promotion window so that promotion batches can only be created during the designated period.

Acceptance criteria:
- Given the window is closed and I toggle it open, then staff can immediately create promotion batches.
- Given the window is open and I toggle it closed, then the Create Batch button disappears for staff.
- Given I toggle the window, then the change is logged in the audit log.

---

## 7. Non-functional requirements (module-relevant)

- **Atomicity** — Batch execution (updating all included students' records) must run inside a single database transaction; if any UPDATE fails, all changes are rolled back.
- **Idempotency guard** — Once a batch is `approved`, it cannot be approved again; attempting to do so returns 403.
- **Concurrency** — Only one batch per department in `pending_approval` at a time; enforced by a unique constraint or application-level check.
- **Audit** — Every batch creation, exclusion, approval, rejection, and window toggle logged via `MasterAuditLogger`.
- **Notification** — Batch submit and approve/reject trigger notification events via M7.
- **CSRF** — All POST actions CSRF-protected.
- **Dept scoping** — Staff and dept_admin can only see and act on their own department's batches.

---

## 8. Open questions

| # | Question | Owner | Resolution needed by |
|---|----------|-------|---------------------|
| 1 | Should excluded students be automatically marked as a specific `onboarding_status` (e.g. `detained`) or remain `active`? | Product | Before Design |
| 2 | Should the approval execute the promotion immediately, or should there be a separate "Execute" step after approval? | Product | Before Design |
| 3 | When a student's form is reset post-promotion, should their existing profile data (blood group, address, family, etc.) be preserved as-is for the new year, or cleared? | Product | Before Design |
| 4 | Should the batch creation page show **all** students or only eligible ones, with ineligible students greyed out? | Product | Before Design |
| 5 | Should staff be able to re-open a rejected batch (edit and resubmit), or must they create a fresh batch? | Product | Before Design |

---

## 9. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| | | | |

> **Next step:** On approval, proceed to Design.
