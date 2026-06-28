# SIS — Module 5: Student Information Form
## Stage 1: Requirements (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 5 of 12 — Student Information Form
**Document stage:** **Requirements (this document)** → _Design_ → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Builds on:** Authentication (M1), Master Data (M2), Student Onboarding (M3), Enrolment Numbers (M4)

---

## 1. Purpose & objectives

After a student is onboarded by staff (M3) and assigned an enrolment number (M4), they must complete their full profile by filling out the Student Information Form — a comprehensive, multi-section form of approximately 95 fields. This module gives students a structured, saveable form they can complete in stages and submit when ready. Once submitted, the record is locked and any further changes must go through the Request-to-Change flow (M6).

Objectives:

- Provide a multi-section, partially saveable student information form covering all ~95 required fields.
- Group fields into logical sections (Personal, Contact, Family/Guardian, Academic Background, Documents) with clear visual hierarchy.
- Allow document uploads (PDF or image, ≤ 2 MB) for relevant fields; restrict passport photo to image only.
- Lock the form on submission so the data cannot be edited without an approved Request-to-Change (M6).
- Give staff and admins read-only visibility of each student's submitted form.
- Track form completion progress so students know what remains before they can submit.

---

## 2. In scope

### 2.1 Form sections and fields

The form is divided into six sections. Fields within each section are listed below. Fields marked *(doc)* accept a file upload. Fields marked *(photo)* accept an image only.

**Section 1 — Personal Details**
1. First Name *(pre-filled from onboarding, read-only)*
2. Last Name *(pre-filled, read-only)*
3. Date of Birth *(pre-filled, read-only)*
4. Gender *(pre-filled, read-only)*
5. Blood Group (dropdown: A+, A−, B+, B−, O+, O−, AB+, AB−)
6. Mother Tongue (text)
7. Religion (dropdown — option list)
8. Caste (text)
9. Caste Category (dropdown: OC, OBC, BC, MBC, SC, ST, Others)
10. Sub-caste (text, optional)
11. Nationality (text, default: Indian)
12. Place of Birth (text)
13. Aadhaar Number (12-digit numeric, masked display)
14. Passport Photo *(photo)* *(required)*
15. Student Email (email)
16. Student Mobile *(pre-filled, read-only)*
17. Alternate Mobile (optional)
18. Marital Status (dropdown: Single, Married, Other; default: Single)
19. Physically Challenged (Yes / No; default: No)
20. If Physically Challenged — Nature of Disability (text, conditional on #19)
21. First Graduate (Yes / No)
22. Annual Family Income (numeric, in ₹)

**Section 2 — Address Details**
23. Permanent Address Line 1
24. Permanent Address Line 2 (optional)
25. Permanent City / Town
26. Permanent Taluk (dropdown — master data)
27. Permanent District (dropdown — master data)
28. Permanent State (dropdown — master data)
29. Permanent PIN Code (6-digit)
30. Communication Address Same as Permanent? (checkbox — auto-fills §31–37)
31. Communication Address Line 1 (conditional)
32. Communication Address Line 2 (optional, conditional)
33. Communication City / Town (conditional)
34. Communication Taluk (dropdown, conditional)
35. Communication District (dropdown, conditional)
36. Communication State (dropdown, conditional)
37. Communication PIN Code (conditional)

**Section 3 — Parent / Guardian Details**

The section opens with a **Family Situation** selector followed (for Single Parent) by a **Which Parent** sub-selector. Field visibility and mandatory status adjust silently based on these selections.

**Visibility & mandatory rules (internal logic — not displayed to the student):**

| Field | Both Parents | Single Parent — Father | Single Parent — Mother | Guardian |
|-------|:---:|:---:|:---:|:---:|
| Father's Name | Required | Required | Required | Required |
| Father's Occupation / Qualification / Income / Mobile | Required | Required | Optional | Optional |
| Father's Email | Optional | Optional | Optional | Optional |
| Mother's Name | Required | Optional | Required | Optional |
| Mother's Occupation / Qualification / Income / Mobile | Required | Optional | Required | Optional |
| Mother's Email | Optional | Optional | Optional | Optional |
| Guardian Name | Hidden | Optional | Optional | Required |
| Guardian Relationship / Mobile / Address | Hidden | Optional | Optional | Required |
| Guardian Email | Hidden | Optional | Optional | Optional |

38. Family Situation (selector: Both Parents / Single Parent / Guardian; default: Both Parents)
39. Which Parent *(sub-selector: Father / Mother; shown only when Family Situation = Single Parent)*
40. Father's Name *(always required)*
41. Father's Occupation (text)
42. Father's Qualification (text)
43. Father's Annual Income (numeric, in ₹)
44. Father's Mobile
45. Father's Email (optional)
46. Mother's Name
47. Mother's Occupation (text)
48. Mother's Qualification (text)
49. Mother's Annual Income (numeric, in ₹)
50. Mother's Mobile
51. Mother's Email (optional)
52. Guardian Name
53. Guardian Relationship (text)
54. Guardian Mobile
55. Guardian Address (text)
56. Guardian Email (optional)

**Section 4 — Academic Background (Previous Education)**

Each qualification row captures: Exam / Qualification Name, Board / University, School / Institution Name, Year of Passing, Percentage / CGPA, Subject / Stream, Medium of Instruction, State of Study.

54. SSLC / 10th Standard *(doc — mark sheet)*
55. HSC / 12th Standard / Diploma *(doc — mark sheet)*  
56. UG Degree *(doc — mark sheet, only for PG students; hidden for UG)*
57. Other Qualifications (up to 2 additional rows, optional)

**Section 5 — Entrance & Admission Details**
58. Admission Type (dropdown: Management Quota, Government Quota, NRI, Lateral Entry)
59. Entrance Exam Name (text, optional)
60. Entrance Exam Hall Ticket No. (text, optional)
61. Entrance Exam Rank / Score (text, optional)
62. Admission Number / Application Number (text)
63. Community Certificate Number (text, optional)
64. Community Certificate *(doc, optional)*
65. Transfer Certificate Number (text, optional)
66. Transfer Certificate *(doc, optional)*
67. Conduct Certificate *(doc, optional)*
68. Migration Certificate *(doc, optional)* *(for PG students)*
69. Income Certificate *(doc, optional)*
70. Nativity Certificate *(doc, optional)*
71. Aadhaar Card Copy *(doc)*

**Section 6 — Bank & Scholarship Details** *(optional section)*
72. Bank Account Holder Name
73. Bank Name
74. Branch Name
75. Account Number
76. IFSC Code
77. Bank Passbook / Account Statement *(doc, optional)*
78. Scholarship Applied? (Yes / No)
79. Scholarship Scheme Name (text, conditional on #78)
80. Scholarship Application Number (text, conditional on #78)

**Additional fields tracked internally (not student-editable):**

- `form_submitted_at` — timestamp when student clicks Submit
- `form_completion_percentage` — computed on each save
- `last_saved_at` — timestamp of last partial save

---

### 2.2 Partial save

- Students can save any section at any time without completing the entire form.
- The system saves the entered data and displays a progress indicator (% complete) after each save.
- Students can return to any previously saved section and continue editing until they submit.

### 2.3 Submit & lock

- A "Submit Form" button is shown only when all required fields are complete.
- On submit, the student confirms via a modal ("Once submitted, you cannot edit your form without requesting a change. Are you sure?").
- On confirmation, `onboarding_status` advances to `form_submitted`, the form is locked (read-only for the student), and `form_submitted_at` is recorded.
- After submission, the student sees a read-only summary view of their form.

### 2.4 Staff / admin view

- Department Staff and Department Admin can view any student's form in read-only mode.
- They can see completion percentage and submission status.
- They cannot edit on the student's behalf via this module (edits go through M6 Request-to-Change).

### 2.5 Document uploads

- Accepted formats: PDF and image (JPEG, PNG, WebP) for document fields; image only (JPEG, PNG) for passport photo.
- Maximum file size: 2 MB per document.
- Files stored under `storage/uploads/students/{student_id}/`.
- Uploaded files are displayed as a thumbnail (images) or a PDF icon link (PDFs) after upload.
- A file can be replaced before submission; once submitted it is immutable.

### 2.6 Parent/Guardian field colour coding

- All fields in Section 3 (Parent / Guardian Details) are rendered with a blue left-border or blue label to visually distinguish them from student fields.
- This is a visual-only convention; it does not affect validation or storage.

### 2.7 Conditional / dynamic field visibility

- Communication address fields are hidden when "same as permanent" is checked.
- Disability detail field is shown only when Physically Challenged = Yes.
- UG Degree row is hidden for UG students; shown for PG students.
- Section 3 parent/guardian fields are shown or hidden based on the Family Situation selector and (for Single Parent) the Which Parent sub-selector. Father's Name is always required. Mandatory/optional status of all other parent/guardian fields adjusts silently per the internal rules table in §2.1.
- Scholarship fields are shown when Scholarship Applied = Yes.
- Conditional fields are validated only when they are visible.

---

## 3. Out of scope (this module)

- **Field Management (M10)** — configuring which fields are mandatory / optional / hidden per department is out of scope for M5; all fields use fixed mandatory/optional rules defined here.
- **Request-to-Change (M6)** — editing a submitted form is handled in M6; M5 only handles first submission.
- **Notifications (M7)** — email/SMS on form submission is M7.
- **Staff editing on student's behalf** — not supported; all student data edits go through M6.
- **Section-by-section approval by staff** — form is reviewed as a whole in M6.
- **Offline or Excel-based form fill** — students must use the web form.

---

## 4. Roles involved

| Role | Capability in this module |
|------|--------------------------|
| Student | Fill form (partial save), view progress, submit form, view read-only summary after submission |
| Department Staff | View any student's form (read-only), view completion % and submission status |
| Department Admin | Same as Department Staff |
| Institution Admin | View any student's form across departments (read-only) |

---

## 5. Assumptions & dependencies

- **M1 (Auth):** Student login (mobile + DOB) is in place; `login_enabled = 1` set at M3 onboarding.
- **M3 (Onboarding):** Core fields (first_name, last_name, dob, mobile, gender, department_id, programme_level, academic_year_id, class_id, section_id, admission_date) are already stored in `students` and pre-filled into the form as read-only.
- **M2 (Master Data):** Taluk, District, State dropdowns, and option lists (religion, blood group, caste category, etc.) are populated.
- **M4 (Enrolment Numbers):** Student's enrolment number is displayed on their dashboard; the form does not depend on enrolment number status — students can start filling the form at any time after M3 onboarding regardless of enrolment number assignment.
- The `students` table will be extended with ~75 new nullable columns (or a `student_profiles` linked table) — design decision left for Stage 2.
- File storage is local under `storage/uploads/` (existing pattern from M3 bulk upload).
- `form_completion_percentage` is computed server-side on each save, not stored permanently (or cached — design decision for Stage 2).

---

## 6. Epics & user stories

### Epic A — Form fill & partial save

**A1. Fill and save the student information form in stages**
As a student, I want to fill my information form section by section and save my progress, so that I can complete it over multiple sessions without losing data.

Acceptance criteria:

- Given I am logged in and my form has not been submitted, when I navigate to "My Form", I see a multi-section form with my onboarded fields pre-filled as read-only.
- I can enter data in any section and click "Save" to store partial progress.
- On save, a progress bar or percentage shows how much of the required form is complete.
- If I log out and return, my previously saved data is still there.
- Required fields are highlighted when empty; the form does not block saving partial data.

**A2. View completion progress**
As a student, I want to see how much of my form I have completed, so that I know what is left before I can submit.

Acceptance criteria:

- A progress indicator (e.g. "72% complete") is visible on the form page and on my dashboard.
- The indicator updates each time I save.
- A checklist or section-by-section status (e.g. "Personal Details ✓", "Documents ✗") is shown.

---

### Epic B — Document uploads

**B1. Upload documents against relevant fields**
As a student, I want to upload my certificates and documents directly on the form, so that I don't need to submit physical copies separately.

Acceptance criteria:

- Each document field shows an "Upload" button accepting PDF or image (≤ 2 MB).
- The passport photo field accepts images only (JPEG, PNG).
- On successful upload, the field shows the filename (documents) or thumbnail (images).
- Uploading replaces any previously uploaded file (before submission).
- Oversized or wrong-format files produce a clear validation error without losing other form data.

---

### Epic C — Submit & lock

**C1. Submit the completed form**
As a student, I want to submit my form when I have filled all required fields, so that it can be reviewed by the department.

Acceptance criteria:

- The "Submit Form" button is active only when all required fields (including required uploads) are complete.
- Clicking Submit shows a confirmation modal: "Once submitted, you cannot edit your form without requesting a change. Proceed?"
- On confirmation, the form is locked and `onboarding_status` = `form_submitted`.
- A success message is shown: "Your form has been submitted successfully."
- After submission, the student sees a read-only view of all their data.

**C2. Read-only view after submission**
As a student, after submitting my form I want to see a clean summary of my submitted information, so that I have a record of what I submitted.

Acceptance criteria:

- All submitted fields are displayed in a formatted, non-editable view.
- Uploaded documents are accessible as download/preview links.
- A "Request a Change" button is shown (links to M6 flow — greyed out if M6 is not yet implemented).

---

### Epic D — Staff / admin read-only view

**D1. Staff view of a student's form**
As a Department Staff or Admin, I want to view any student's information form, so that I can verify their details and track completion.

Acceptance criteria:

- From the student list, I can open a student's form in read-only mode.
- I see the completion percentage and whether the form is submitted.
- All entered fields and uploaded document links are visible.
- No edit capability is present (edits are in M6).

---

## 7. Non-functional requirements (module-relevant)

- **Partial save performance:** save of any section must complete within 3 seconds for forms with up to 10 document uploads.
- **File storage:** uploads stored in `storage/uploads/students/{student_id}/`; filename pattern `{field_key}_{timestamp}.{ext}`.
- **File validation:** size ≤ 2 MB; type whitelist enforced server-side (not just client-side).
- **Security:** all form routes require authenticated student session; students can only view/edit their own form; CSRF on all writes.
- **Data integrity:** partial saves use `UPDATE` on existing row(s); no duplicate student profile rows.
- **Accessibility:** form labels associated with inputs; error messages inline; required fields marked with `*`.

---

## 8. Open questions

1. **Storage strategy:** should the ~75 new fields be added as columns to the `students` table (single wide table) or stored in a separate `student_profiles` table (1:1)? The wide-table approach is simpler; the linked-table approach keeps `students` lean for M6/M10.
2. **Completion percentage formula:** is it `(non-null required fields) / (total required fields)` × 100, or does each section have equal weight regardless of field count?
3. **Optional sections:** is Section 6 (Bank & Scholarship) entirely optional, or are some fields required for scholarship-eligible students?
4. **Field list final count:** the above lists 80 student-editable fields + 3 computed fields. Are there additional institution-specific fields to include, or fields to remove?
5. **UG Degree row for UG students:** confirmed hidden? Some UG students may be lateral-entry (have a diploma), so should there be a separate lateral-entry academic row?
6. **Aadhaar display:** should Aadhaar Number be masked (e.g. `XXXX-XXXX-3456`) in the view after entry, or stored and displayed in full?
7. **Guardian section:** resolved — shown/hidden via the Family Situation selector (Both Parents / Single Parent / Guardian). Father's Name always required.

---

## 9. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, the next step is **Stage 2: Design** — covering the data model (wide-table vs linked-table decision, column list, indexes), the save/submit state machine, file storage layout, screen designs, and component architecture, submitted for your review before the Task breakdown.
