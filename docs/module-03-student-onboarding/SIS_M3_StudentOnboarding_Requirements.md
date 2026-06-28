# SIS — Module 3: Student Onboarding
## Stage 1: Requirements (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 3 of 12 — Student Onboarding
**Document stage:** Requirements → _Design_ → _Tasks_ (this is Requirements; design follows after approval)
**Version:** 0.1 (Draft) · June 2026
**Status:** Awaiting review & approval
**Builds on:** Authentication & Access Control (Module 1), Master Data (Module 2)

---

## 1. Purpose & objectives

Student Onboarding is the entry point for every student into the SIS. Before a student can log in, fill in their information form, or receive an enrolment number, a record for them must exist in the system. This module gives Department Staff a governed, auditable way to create those initial records — primarily in bulk at the start of an academic intake, and individually when a student is admitted late.

Objectives:

- Allow Department Staff to **bulk-upload** a cohort of new students from a spreadsheet at the start of each intake.
- Allow **single-record addition** for late admissions without requiring a new upload.
- Validate every incoming record (required fields, format rules, duplicate detection) and report results clearly — row by row — so staff can correct and re-upload without losing progress.
- Give Institution Admin visibility of onboarding activity across all departments.
- Produce clean, audit-logged student records ready for enrolment number generation (Module 4) and student self-service form completion (Module 5).

---

## 2. In scope

- Downloadable **upload template** (Excel .xlsx) with the required column headers and a data-validation reference sheet.
- **Bulk upload** of student records (Department Staff); validation, duplicate detection, and a per-row result report.
- **Single-record add** for individual late-admission students.
- **Duplicate handling** — detect within the file and against existing records; allow override with confirmation.
- Student record **status lifecycle**: `pending_enrolment` → (Module 4) `enrolment_assigned` → (Module 5) `form_submitted` → (Module 6) `approved`.
- **Audit logging** of every create/override action.
- Institution Admin view: onboarding summary across departments (counts by status/department).
- Department Staff / Admin view: their own department's onboarded student list with status.

---

## 3. Out of scope (this module)

- Enrolment number generation and the approval workflow for releasing it — that is Module 4.
- The ~95-field student information form that students fill in themselves — that is Module 5.
- Staff account creation — that is Module 9.
- Editing any onboarded field after the record is created (handled by Module 5's request-to-change flow, Module 6).
- Document / photo uploads at onboarding — those happen in Module 5.
- Email notification to the student after onboarding — that is Module 7; onboarding just creates the record.

---

## 4. Roles involved

| Role | Capability in this module |
|------|---------------------------|
| Department Staff | Download template; upload cohort for their own department; add individual records; view their department's onboarding list and upload results |
| Department Admin | All of the above, plus mark duplicate-override decisions |
| Institution Admin | View onboarding summary across all departments; no upload capability (uploads are department-scoped) |
| Student | No access to this module; gains login access only after Module 4 releases an enrolment number |

---

## 5. Assumptions & dependencies

- **Module 1 (Auth)** is in place: route guards ensure only authenticated staff/admins reach onboarding routes.
- **Module 2 (Master Data)** is in place: Department list, Academic Year, Class and Section option lists exist and are used to validate upload data.
- The **student login credential** is mobile number + date of birth (locked decision). Both must be present and valid before a record is created.
- A student record at this stage carries only the **onboarding fields** (§6); the full ~95-field form is completed later by the student (Module 5).
- Mobile number uniqueness is the primary duplicate-detection key. Name + DOB is a secondary signal.
- Bulk uploads are department-scoped: a staff user can only upload students for their own department.

---

## 6. Epics & user stories

### Epic A — Upload template

**A1. Download the upload template**
As a Department Staff, I want to download a pre-formatted Excel template so that I know exactly which columns to fill in and in what format.

Acceptance criteria:

- Given I am on the Student Onboarding page, when I click "Download Template", then I receive an .xlsx file with the correct column headers.
- The template includes a "Reference" sheet listing valid values for Department, Academic Year, Class, Section (drawn from live master data at download time) so staff can use data validation in Excel.
- Column headers match the field names in §6 exactly; no extra columns are required.

---

### Epic B — Bulk upload

**B1. Upload a cohort file**
As a Department Staff, I want to upload a filled-in Excel file containing my department's new students so that their records are created in bulk.

Acceptance criteria:

- Given a valid .xlsx file, when I upload it, then:
  - Rows that pass all validation are created as student records with status `pending_enrolment`.
  - Rows that fail validation are listed with row number, field name, and reason; no partial save for failing rows.
  - Rows flagged as duplicates are held for confirmation (see B2); not auto-rejected.
- The upload result screen shows a summary: **Created / Duplicate-held / Failed / Total** with a downloadable error report.
- Supported file: .xlsx only; maximum 1,000 rows per upload; maximum 5 MB file size.
- The department column in the file must match the uploading staff member's own department; mismatched rows are rejected.

**B2. Duplicate detection and resolution**
As a Department Staff, I want to know when an uploaded student appears to already exist, so that I can decide whether to skip or deliberately override.

Acceptance criteria:

- A duplicate is flagged when: (a) the mobile number already exists in the students table, OR (b) the combination of first name + last name + date of birth already exists.
- For each flagged row, the result report shows the existing record's name, enrolment serial (if assigned), and the reason it was flagged.
- Staff can select duplicate rows individually and choose "Skip" (discard) or "Override — create as new record" (with a mandatory reason note).
- Override requires Department Admin confirmation before the record is saved.
- Override actions are audit-logged with the reason note and both the staff and admin actors.

**B3. Re-upload after correction**
As a Department Staff, I want to fix the rows that failed and re-upload just those rows, so that I don't re-process the entire cohort.

Acceptance criteria:

- The downloadable error report is a valid .xlsx file with the same column structure as the template, pre-populated with the failed rows and an extra "Error" column.
- Staff can correct the file and re-upload it; successfully created rows from the original upload are not duplicated.

---

### Epic C — Single-record addition

**C1. Add an individual student record**
As a Department Staff, I want to add a single student manually when a student is admitted late, so that I don't need to create an upload file for one person.

Acceptance criteria:

- Given I fill in all required onboarding fields for one student, when I save, then the record is created with status `pending_enrolment`.
- The same validation rules (field formats, duplicate detection) apply as for the bulk upload.
- If a duplicate is detected, the same override flow (C is required → Department Admin confirmation) applies.

---

### Epic D — Onboarding field set

**D1. Required onboarding fields**
As the institution, I want a defined minimal field set captured at onboarding so that students can log in and receive an enrolment number without waiting for the full 95-field form.

Onboarding fields (collected by staff at upload / single-add):

| Field | Type | Validation |
|-------|------|------------|
| First Name | Text | Required; max 100 chars |
| Last Name | Text | Required; max 100 chars |
| Date of Birth | Date | Required; format DD/MM/YYYY; student must be ≥ 15 years old |
| Mobile Number | Text | Required; exactly 10 digits; unique across all students |
| Gender | Option | Required; Male / Female / Other |
| Department | Select | Required; from active departments (master data); must match staff's own dept |
| Programme Level | Derived | Derived from Department (UG or PG); not editable |
| Academic Year | Select | Required; from active Academic Year option list |
| Class | Select | Required; from active Class option list |
| Section | Select | Optional; from active Section option list |
| Admission Date | Date | Required; format DD/MM/YYYY; cannot be a future date |

> All other student fields (address, parent details, document uploads, photo, etc.) are captured later by the student via the information form (Module 5).

---

### Epic E — Status lifecycle & visibility

**E1. Student record status**
As a Department Staff, I want to see the current status of each onboarded student so that I know where they are in the onboarding pipeline.

Acceptance criteria:

- Each student record carries a status displayed on the onboarding list: `Pending Enrolment` · `Enrolment Assigned` · `Form Submitted` · `Approved`.
- Staff see their own department's list; Institution Admin sees all departments.
- The list is filterable by status, academic year, and department (admin only); searchable by name or mobile.

**E2. Onboarding summary (Institution Admin)**
As an Institution Admin, I want a summary view of onboarding activity across all departments so that I can monitor intake progress.

Acceptance criteria:

- Summary shows per-department counts: Total onboarded / Pending enrolment / Enrolment assigned / Form submitted / Approved.
- Filterable by academic year.

---

### Epic F — Audit & integrity

**F1. Audit logging**
As an admin, I want every student record creation, override decision, and status change at this stage to be logged so that the intake history is reviewable.

Acceptance criteria:

- Actions logged: record created (bulk or single), duplicate override (with reason and both actors), record skipped.
- Log entries include: action type, actor (user id + name), department, timestamp, and the student's name + mobile (no further PII beyond what staff already hold).

**F2. Data integrity**
As the institution, I want onboarding data to be safely stored and protected from concurrent corruption.

Acceptance criteria:

- Mobile number uniqueness is enforced at the database level (unique index), not only in application code.
- Uploads are processed in a transaction per row; a single-row failure does not roll back the entire upload.
- Uploaded files are not stored permanently; they are processed in memory / temp and then discarded.

---

## 7. Non-functional requirements (module-relevant)

- **Performance:** an upload of 1,000 rows must complete processing within 30 seconds; progress feedback shown (spinner / progress message).
- **Usability:** the result screen distinguishes Created / Held / Failed clearly with colour coding; the error report is immediately downloadable without a second request.
- **Security:** file uploads validated for MIME type and extension (.xlsx only); file size capped at 5 MB; no executable content accepted; all routes are staff/admin-only with CSRF protection.
- **Accessibility:** upload form and result table usable on tablet (≥ 768px); error messages associated with the relevant row and column.

---

## 8. Open questions

1. **Single-add by whom?** Can a Department Staff add individual records, or is single-add restricted to Department Admin only? (Current assumption: any staff in the department.)
2. **Duplicate override approval:** must the Department Admin actively approve in-app, or is an emailed acknowledgement sufficient? (Current assumption: in-app confirmation.)
3. **File format:** should .csv also be accepted in addition to .xlsx, or .xlsx only to allow reference sheets and data validation?
4. **Maximum rows per upload:** is 1,000 rows per file appropriate for the largest expected cohorts, or should this be higher (e.g. 2,000)?
5. **Onboarding fields — Email:** should the student's personal email be captured at onboarding (for Module 7 notifications), or is it left for Module 5? If captured here, it must not appear in any notification email.
6. **Status visibility for students:** once a student has an enrolment serial (Module 4), can they log in and see their `pending_enrolment` status, or is login blocked until enrolment number is fully approved and released?

---

## 9. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval of this Requirements document, the next step is **Stage 2: Design** for Module 3 — covering the database schema for the students table (onboarding fields + status), upload processing logic, duplicate-detection algorithm, result reporting, and screen designs — submitted for your review before any Task breakdown.
