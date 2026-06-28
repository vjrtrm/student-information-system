# SIS — Module 7: Notifications
## Stage 1: Requirements (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 7 of 12 — Notifications
**Document stage:** **Requirements (this document)** → _Design_ → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Builds on:** Authentication (M1), Master Data (M2), Student Information Form (M5), Submission & Edit Approval (M6)

---

## 1. Purpose & objectives

Module 6 creates rows in `notification_events` for every significant system action (submission approved, RTC created, RTC approved, RTC rejected) but does not send any messages. Module 7 reads those queued events and delivers them to the intended recipients by email using PHPMailer/SMTP.

Objectives:

- Deliver email notifications to students and department administrators for all five M6-defined event types.
- Resolve recipient email addresses from existing data (student from `student_profiles.student_email`; dept admin from `users` table).
- Send emails with no PII in the message body (link/code only — locked product decision).
- Mark each event's `sent_at` after successful delivery so it is not processed again.
- Handle missing email addresses gracefully (log and skip, do not crash the batch).
- Provide an admin-accessible page to view the notification log (sent and pending events).
- Allow an Institution Admin or Department Admin to manually trigger a send-all for their queue.

---

## 2. In scope

### 2.1 Notification event processing

- A processor reads all rows from `notification_events` where `sent_at IS NULL` and `created_at` is older than a configurable delay (default 0 seconds — send immediately).
- For each event, the processor resolves the recipient email:
  - `recipient_type = 'student'`: look up `student_profiles.student_email` for the student. If blank, skip and log.
  - `recipient_type = 'dept_admin'`: look up all active users with `role IN ('dept_admin')` for the student's `department_id`. Send to each. If none found, skip and log.
- Sends email via PHPMailer using SMTP credentials from `config/mail.php`.
- On successful send: `UPDATE notification_events SET sent_at = now() WHERE id = ?`.
- On failed send (SMTP exception): log the error to `notification_error_log` table; leave `sent_at = NULL` so the next run retries.
- Processing order: oldest `created_at` first (FIFO).

### 2.2 Email content (one template per event type)

All emails must comply with the locked PII rule: **no student name, mobile number, Aadhaar, or any personal data in the message body**. Bodies contain only links, event codes, or neutral text directing the recipient to the system.

| Event key | Recipient | Subject | Body content |
|-----------|-----------|---------|-------------|
| `submission_approved` | Student | "Your form has been approved — [SystemName]" | "Your information form submission has been approved. Log in to view your record: [link]" |
| `submission_approved` | Dept Admin | "Student submission approved — [SystemName]" | "A student submission has been approved in your department. Log in to view: [link]" |
| `rtc_created_by_student` | Dept Admin | "New change request pending review — [SystemName]" | "A student has submitted a change request that requires your review. Log in to action it: [link]" |
| `rtc_created_by_staff` | Student | "A change request has been raised on your record — [SystemName]" | "A staff member has submitted a change request on your information form. Log in to view: [link]" |
| `rtc_created_by_staff` | Dept Admin | "New staff-initiated change request pending — [SystemName]" | "A change request has been raised by a staff member and requires review. Log in to action it: [link]" |
| `rtc_approved` | Student | "Your change request has been approved — [SystemName]" | "Your requested changes have been applied to your information form. Log in to verify: [link]" |
| `rtc_approved` | Dept Admin | "Change request approved — [SystemName]" | "A change request in your department has been approved and changes applied. Log in to view: [link]" |
| `rtc_rejected` | Student | "Your change request was not approved — [SystemName]" | "Your change request could not be approved at this time. Log in to view the details and raise a new request if needed: [link]" |
| `rtc_rejected` | Dept Admin | "Change request rejected — [SystemName]" | "A change request in your department has been rejected. Log in to view: [link]" |

Links:
- Student recipient → `/student/form/view` (for form-related events) or `/rtc/history`
- Dept Admin recipient → `/approvals`
- Links are constructed using a configurable `app.base_url` from `config/app.php`.

### 2.3 SMTP configuration

A new `config/mail.php` provides:
- `host`, `port`, `username`, `password`, `encryption` (tls/ssl), `from_address`, `from_name`, `system_name`
- These must never be committed to version control (developer sets them locally; `.gitignore` already covers `config/mail.php` or the file ships as `config/mail.php.example`).

### 2.4 Notification log view

- Institution Admin and Department Admin can view `/notifications` — a paginated table of `notification_events` for the relevant departments.
- Columns: Event, Student (serial number only — no name), Recipient Type, Sent At (or "Pending"), Created At.
- Filterable by: event type, recipient type, status (sent / pending / all).
- No PII displayed in the log (student name not shown — enrolment serial only).

### 2.5 Manual trigger

- A **Send Now** button on `/notifications` triggers processing of all unsent events for the admin's department (dept admin) or all departments (institution admin).
- Implemented as a POST to `/notifications/send` with CSRF guard.
- Returns a flash message: "Sent N notifications. M skipped (no email on file)."

### 2.6 Notification error log

- A `notification_error_log` table records failed send attempts: event_id, error_message, attempted_at.
- Errors are visible on the notifications page as a count badge ("N failed"). Institution Admin can view the error list.
- A failed event is retried on the next run. No automatic back-off in v1.

---

## 3. Out of scope (this module)

- **SMS / WhatsApp delivery** — email only in M7.
- **Real-time / push notifications** — no WebSocket or browser push.
- **In-app notification bell** — deferred to M8 (Dashboards), which may read `notification_events` for a badge count.
- **Scheduled auto-send** — no cron job or PHP scheduler in M7; processing is triggered manually from the admin UI (or the developer can set up a cron themselves).
- **Per-user notification preferences** — all recipients are notified; opt-out is out of scope for v1.
- **Rich HTML email templates** — plain-text emails with a link; no HTML styling in v1.

---

## 4. Roles involved

| Role | Capability |
|------|-----------|
| Student | Receives email notifications; no SIS UI for notification management |
| Department Staff | No notification management access |
| Department Admin | Views `/notifications` for own department; triggers Send Now for own department |
| Institution Admin | Views `/notifications` across all departments (with dept filter); triggers Send Now globally |

---

## 5. Assumptions & dependencies

- **M6 complete:** `notification_events` table exists with all M6 events queued correctly.
- **Student email field:** `student_profiles.student_email` (M5 field) is the source. If a student has not filled this field, no email is sent to them; the event is skipped and logged.
- **Dept admin email:** Taken from `users.email` for all active users with `role = 'dept_admin'` in the student's department. A department with no dept_admin user will have those events skipped (logged).
- **PHPMailer** already in composer dependencies (established in M1 or earlier).
- **`app.base_url`** already in `config/app.php` (established in M1).
- The processor is stateless and idempotent: running it twice processes only events still with `sent_at = NULL`.

---

## 6. Epics & user stories

### Epic A — Email delivery

**A1. Notify student on submission approved**
As a student, I want to receive an email when my form is approved so I know the outcome without having to log in repeatedly.

Acceptance criteria:
- `notification_events` row with `event_key = 'submission_approved'` and `recipient_type = 'student'` → email sent to `student_profiles.student_email`.
- Email subject: "Your form has been approved — [SystemName]".
- Email body contains a login link only; no name, mobile, or other PII.
- `sent_at` updated after successful send.
- If no student email on file: row logged in `notification_error_log`; event left unsent for retry; flash shows "skipped".

**A2. Notify dept admin on submission approved**
As a Department Admin, I want an email when a submission in my department is approved so I'm aware of throughput.

Acceptance criteria:
- `recipient_type = 'dept_admin'` → email sent to all active dept_admin users in the student's department.
- Link points to `/approvals`.
- If no dept_admin exists: logged and skipped.

**A3–A9.** Corresponding stories for the remaining eight event × recipient combinations (follow same pattern as A1/A2 — omitting for brevity; confirmed in scope via §2.2 table).

---

### Epic B — Admin notification management

**B1. View notification log**
As a Department Admin, I want to see what notifications have been sent so I can verify delivery and spot missing ones.

Acceptance criteria:
- `/notifications` accessible to dept_admin and institution_admin; dept-scoped.
- Table shows event key, recipient type, sent_at (or "Pending" badge), created_at; no student name or PII.
- Filter dropdowns for event type and status (All / Sent / Pending).
- Paginated at 50 rows per page.
- Institution Admin sees a department filter.

**B2. Trigger Send Now**
As a Department Admin, I want to send all queued notifications for my department at once.

Acceptance criteria:
- POST `/notifications/send`; CSRF protected.
- Processes all `sent_at IS NULL` events for dept.
- Flash: "Sent N notifications. M skipped (no email on file). K failed (SMTP error)."
- Already-sent events are not re-processed.

**B3. View failed notifications**
As an Institution Admin, I want to see which notifications failed so I can investigate SMTP issues.

Acceptance criteria:
- A "Failed" count badge on `/notifications` shows count of `notification_error_log` rows for the relevant dept.
- Clicking the badge opens a list: event ID, error message (truncated to 200 chars), attempted_at.

---

## 7. Non-functional requirements (module-relevant)

- **Atomicity per event:** send attempt + `sent_at` update in a single try/catch; on exception, `notification_error_log` row inserted; `sent_at` left NULL.
- **PII rule:** email subject and body must pass a review: no student name, mobile, Aadhaar, account number, address. Links only.
- **Security:** CSRF on Send Now POST; role guard on all routes; dept-scope enforced.
- **Performance:** Send Now processes up to 200 events per request; larger queues should be run in batches (configurable `max_per_run` in `config/mail.php`).

---

## 8. Open questions

1. **Student login link in email:** should the link go to `/login` or directly to `/student/form/view`? The student must log in first — so probably `/login`. Confirm.
2. **Multiple dept admins:** confirmed in scope — send to all active dept_admin users in the dept. If a dept has two dept admins, both receive the email.
3. **`config/mail.php` in git:** should the example file be committed as `config/mail.php.example` so new developers have the structure? Or added to setup documentation only? Recommend example file approach.
4. **Retry limit:** should failed events stop retrying after N attempts? In v1, no limit (retry forever). Confirm or cap at e.g. 3 attempts.
5. **In-app notification badge:** M8 Dashboards can count `notification_events` where `recipient_type = 'student'` and `recipient_id = auth_user_id` for a badge. Confirm M7 does not need to add any extra column for this.

---

## 9. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, the next step is **Stage 2: Design** — data model (`notification_error_log` table, `config/mail.php` schema), processor class design, email template rendering, controller and view structure, and traceability matrix.
