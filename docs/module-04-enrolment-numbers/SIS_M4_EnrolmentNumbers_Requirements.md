# SIS — Module 4: Enrolment Number Generation & Approval
## Stage 1: Requirements (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 4 of 12 — Enrolment Number Generation & Approval
**Document stage:** Requirements → _Design_ → _Tasks_ (this is Requirements; design follows after approval)
**Version:** 0.2 (Revised) · June 2026
**Status:** Awaiting review & approval
**Builds on:** Authentication (M1), Master Data (M2), Student Onboarding (M3)

---

## 1. Purpose & objectives

Every student needs a unique, institution-issued enrolment number as their official identifier. Students can log in and begin completing their information form as soon as they are onboarded (M3); the enrolment number is a separate step that assigns them a permanent identifier. This module gives Department Staff the ability to generate enrolment numbers in bulk for a cohort, then routes each generated number through a per-number approval by a Department Admin or Institution Admin. Only after a number is individually approved is it published to the student.

Objectives:

- Generate enrolment numbers automatically from the fixed formula (`<YY><U|P><DeptCode><serial>`) for all eligible students in a batch.
- Batch is a generation container only — approval happens **per enrolment number**, not per batch.
- On approval of an individual number, publish it to the student record and update their status.
- Students see **nothing** about their enrolment number until it is individually approved.
- Give Institution Admin visibility of enrolment batches and per-number approval status across all departments.
- Record every generation and approval action in the audit trail.

---

## 2. Correction to Module 3 (M3 behaviour update)

> **M3 correction:** `login_enabled` must be set to **1** when a student record is created during onboarding (not held until M4 as originally designed). Students log in with their mobile number and date of birth to complete their information form (M5) independently of enrolment number generation. The M3 migration and `Student::create()` should be updated to default `login_enabled = 1`.

---

## 3. In scope

- Enrolment number formula: `<YY><U|P><DeptCode><serial>` — e.g. `24UBCA041`, `26PMCA100`.
  - `YY` = last two digits of the academic year start (e.g. "24" for 2024-25).
  - `U|P` = programme level from the department (UG → U, PG → P).
  - `DeptCode` = department's code (e.g. BCA, MCA).
  - `serial` = zero-padded 3-digit sequential counter **per department per academic year** (001, 002, …).
- Bulk generation of provisional enrolment numbers for all `pending_enrolment` students in a chosen department + academic year. Numbers are stored against each student record immediately on generation, each marked `enrolment_approval_status = 'pending'`.

- **What a batch is — and is not:**
  - A batch is a named grouping created each time staff triggers generation for a specific dept + academic year combination. It records who generated it, when, and which students were included.
  - A batch has **no approval action of its own**. There is no "approve batch" button. The batch is purely an organisational and audit container — a way to view and act on a set of numbers together.
  - The batch's displayed status (`Pending` / `In Progress` / `Approved`) is always **derived** from the approval status of its individual numbers, not stored independently:
    - `Pending` — all numbers in the batch are still `pending`.
    - `In Progress` — at least one number is `approved`, but others remain `pending`.
    - `Approved` — every number in the batch is `approved`.
  - Approvals are recorded against each **enrolment number row** (student record), not against the batch row. The batch row is never directly updated by an approval action.

- **Per-number approval flow**: admin opens a batch, reviews the full list, then approves — either all numbers at once ("Approve All") or a selected subset ("Approve Selected"). Each approval action updates the status of the individual numbers chosen, not the batch as a whole.

- On approval of a number: `enrolment_approval_status` → `approved`; `onboarding_status` → `enrolment_assigned`; the enrolment number becomes visible to the student.

- **Serial continuity**: serials within a dept+year are globally sequential and never reused across batches.

- **One active batch rule**: a new batch can only be generated for a dept+year once the prior batch reaches `Approved` (all numbers approved). If any numbers are still `pending` or `In Progress`, generation is blocked.

- Audit logging of generation and each per-number approval action.

---

## 4. Out of scope (this module)

- Batch-level rejection — there is no reject-batch action.
- Student login — students already have login access from M3 onboarding; this module does not change `login_enabled`.
- Email notifications to students on enrolment number approval — Module 7.
- Manual one-by-one enrolment number entry.
- Editing an approved enrolment number — approved numbers are immutable.
- Enrolment numbers for staff — staff use email login (M1).

---

## 5. Roles involved

| Role | Capability in this module |
|------|---------------------------|
| Department Staff | Generate a batch for their own department; view batch list and per-number approval status |
| Department Admin | All of the above; approve individual numbers (or all in a batch) for their department |
| Institution Admin | View all batches and per-number status across all departments; approve any pending number |
| Student | View their own full enrolment number on their dashboard **only after it is individually approved**; nothing shown before approval |

---

## 6. Assumptions & dependencies

- **M1 (Auth)** in place; student login uses mobile + DOB; `login_enabled = 1` is set at M3 onboarding.
- **M2 (Master Data)** in place; department code, programme level, and Academic Year option list are populated.
- **M3 (Onboarding)** in place; students with status `pending_enrolment` exist; `login_enabled = 1` (corrected from M3 original).
- The serial counter for a dept+year is derived from the highest serial already issued in any batch for that dept+year.
- Academic year format is "YYYY-YY" (e.g. "2024-25"); `YY` prefix is the 2nd and 3rd characters of the string (i.e. `substr('2024-25', 2, 2)` = "24").

---

## 7. Epics & user stories

### Epic A — Enrolment number generation

**A1. Generate a batch of enrolment numbers**
As a Department Staff, I want to generate enrolment numbers for all eligible students in my department and academic year in one operation, so that they are queued for admin approval.

Acceptance criteria:

- Given students exist with status `pending_enrolment` in my department for the chosen academic year, and no pending batch exists for that dept+year, when I trigger generation:
  - Sequential serials are assigned starting from `max(existing serials for this dept+year) + 1`, or 001 if none exist.
  - Each provisional enrolment number is stored on the student record with `enrolment_approval_status = 'pending'`.
  - A batch record is created linking all affected students.
  - The student's `onboarding_status` does **not** change yet (remains `pending_enrolment`).
- Given a pending batch already exists for the same dept+year, generation is blocked: "A batch is already pending — approve the existing numbers before generating new ones."
- Given no eligible students exist, generation is blocked: "No students with pending enrolment status found for this academic year."
- After generation, I am taken to the batch detail screen showing all generated numbers for review.

**A2. Serial continuity**
As the institution, serials within a dept+year are permanently sequential with no reuse, so that no two students ever share a serial.

Acceptance criteria:

- Serial counter for a dept+year increments from the highest serial previously issued, regardless of the approval status of prior batches.
- No two student records in the same dept+year ever have the same serial.
- Uniqueness is enforced at the DB level (unique index on `enrolment_number`).

---

### Epic B — Per-number approval

**B1. Review the complete generated list before approving**
As a Department Admin or Institution Admin, I want to see the full list of generated enrolment numbers for a batch before taking any approval action, so that I can verify the numbers are correct.

Acceptance criteria:

- Given I open a pending batch, I see a paginated table of all students in the batch with columns: student name, mobile, class, section, provisional enrolment number, approval status.
- The list is fully visible before I take any action — no numbers are auto-approved on generation.
- I can search and filter the list by name, class, or section within the batch.
- A summary header shows total numbers in the batch and how many are pending vs approved.

**B2. Bulk approval of all numbers in a batch**
As a Department Admin or Institution Admin, I want to approve all students in a batch in one action after reviewing the complete list, so that I don't have to select them one by one.

Acceptance criteria:

- "Approve All" is equivalent to ticking every student's checkbox in the batch list and clicking Approve — it selects and approves the entire cohort in that batch.
- Given I have reviewed the batch list, when I click "Approve All", a confirmation modal shows the total count: "Approve all 45 enrolment numbers? This will publish them to students." — requiring explicit confirmation before proceeding.
- On confirming, all students in the batch are approved in a single transaction; no student is skipped.
- On completion, each student's `enrolment_approval_status` → `approved` and `onboarding_status` → `enrolment_assigned`.
- I am shown a success banner: "45 enrolment numbers approved. Students can now view their enrolment numbers."
- The batch status transitions automatically to `Approved`.

**B3. Selective approval (subset of a batch)**
As a Department Admin or Institution Admin, I want to approve a selected subset of numbers within a batch, so that I can hold back specific records if needed.

Acceptance criteria:

- Given a batch list, I can tick individual checkboxes and click "Approve Selected" to approve only those records.
- Partial approval across multiple sessions is allowed — a batch may have a mix of `approved` and `pending` numbers at any point.
- A batch status of `In Progress` is shown when at least one number is approved but others remain pending.
- A batch status of `Approved` is set automatically when every number in the batch is approved.

**B4. Single-approval rule**
As the institution, any one authorised approver — Department Admin (for their dept) or Institution Admin — can approve; no countersignature is needed.

Acceptance criteria:

- Either a Department Admin (dept-scoped) or an Institution Admin can perform bulk or selective approval.
- Once a number is approved it cannot be reversed via the UI.

---

### Epic C — Student visibility

**C1. Student sees enrolment number only after approval**
As a student, I want to see my full enrolment number on my dashboard once it has been approved, so that I can use it officially.

Acceptance criteria:

- Given my enrolment number is **not yet approved** (or not yet generated), my dashboard shows: "Your enrolment number has not been assigned yet."
- Given my enrolment number **is approved**, my dashboard shows the full enrolment number (e.g. `24UBCA041`) prominently.
- At no point does the student see their serial number alone, a provisional number, or any intermediate state.

---

### Epic D — Visibility & audit

**D1. Batch list for staff/admin**
As a Department Staff or Admin, I want to see all enrolment batches for my department with approval progress, so that I can track where each cohort stands.

Acceptance criteria:

- Batch list shows: academic year, student count, approved count, pending count, batch status (pending/approved), generated by, generated at.
- Clicking a batch shows the full student list with provisional enrolment numbers and per-number approval status.
- Filterable by academic year and batch status.

**D2. Institution-wide overview**
As an Institution Admin, I want to see all batches across all departments, filterable by department, academic year, and status.

**D3. Audit trail**
As an admin, every generation and approval is logged in the `audit_log` table (M2 convention): actor, action (`enrolment_batch_generated`, `enrolment_number_approved`), entity, entity_id, timestamp.

---

## 8. Non-functional requirements (module-relevant)

- **Atomicity:** the per-number approval write (status update + onboarding_status update) is a single transaction per number; bulk approval wraps all selected numbers in one transaction.
- **Serial uniqueness:** DB-level unique index on `students.enrolment_number` (non-null values only — sparse unique index or application-level guard).
- **Performance:** generation of up to 1,000 numbers must complete within 10 seconds.
- **Security:** generation and approval routes are staff/admin-only; CSRF on all writes; dept-scope enforced.
- **Immutability:** once `enrolment_approval_status = 'approved'`, the number cannot be changed via any UI action.

---

## 9. Open questions

1. **Batch cap:** should a single generation batch be capped (e.g. 1,000 students per batch), or unlimited (all `pending_enrolment` in the dept+year)?
2. **Serial padding:** the example `041` implies 3 digits. Confirm: always 3 digits? Or configurable for larger institutions?
3. **Who can generate?** Any Department Staff, or only Department Admin? (Current assumption: any staff in the department.)
4. **Partial approval UX:** when some numbers in a batch are approved and some are still pending, what does the batch-level status show? (Proposed: "In Progress" as a third status alongside "Pending" and "Approved".)
5. **Student dashboard source:** does the student's dashboard (M8) read `enrolment_number` directly from the students table, or from a separate view? (Current assumption: directly from students table, shown only when `enrolment_approval_status = 'approved'`.)

---

## 10. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, the next step is **Stage 2: Design** for Module 4 — covering the data model (`enrolment_batches` table, new columns on `students`), the generation algorithm, serial continuity logic, the per-number approval flow, and screen designs — submitted for your review before any Task breakdown.
