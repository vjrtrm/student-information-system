# SIS — Module 7: Notifications
## Stage 2: Design (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 7 of 12 — Notifications
**Document stage:** Requirements ✅ → **Design (this document)** → _Tasks_
**Version:** 0.1 (Draft) · June 2026 · **Status:** Awaiting review & approval
**Traces:** `SIS_M7_Notifications_Requirements.md`

---

## 1. Design goals

Translate the approved M7 requirements into a buildable design on the SIS stack (PHP 8.x MVC, MySQL 5.7, Bootstrap 5, PHPMailer/SMTP). Key goals:

- A single stateless `NotificationProcessor` class reads unsent `notification_events` rows, resolves recipient emails, sends via PHPMailer, and marks `sent_at` — no PII written outside the system.
- Email bodies contain only a neutral message and a login link — no names, mobile numbers, or other personal data.
- Admin UI at `/notifications` gives dept/institution admins visibility into sent, pending, and failed events; no student PII exposed.
- All new code follows M1–M6 conventions: `Db` for queries, `MasterAuditLogger` for actions, CSRF on POSTs, role guards on every action.

---

## 2. Resolved design decisions (from open questions)

| # | Open question | Decision |
|---|---------------|----------|
| 1 | Student login link in email | Links to `/login` for all emails. Students and staff must authenticate before seeing their data; deep-linking to specific pages is not safe before auth. |
| 2 | Multiple dept admins | All active users with `role = 'dept_admin'` in the student's department receive the email. One PHPMailer send per address. |
| 3 | `config/mail.php` in git | A `config/mail.php.example` file is committed with placeholder values; the real `config/mail.php` is gitignored. The developer copies the example and fills in credentials. |
| 4 | Retry limit | No automatic retry cap in v1. Failed events (sent_at IS NULL) are retried on every subsequent Send Now run. `notification_error_log` records each failure with timestamp so ops can see if an event is repeatedly failing. |
| 5 | In-app notification badge (M8) | M7 does not add any extra column. M8 can query `notification_events WHERE recipient_id = ? AND sent_at IS NOT NULL` for a read indicator, but that design is M8's concern. |

---

## 3. Component architecture (MVC)

```
Config/
  mail.php.example               // committed; dev copies to mail.php
  mail.php                       // gitignored; holds live SMTP credentials

Helpers/
  NotificationProcessor.php      // core: load unsent events, resolve emails,
                                 //   send via PHPMailer, mark sent_at or log error
  EmailTemplate.php              // getSubject(eventKey, recipientType): string
                                 // getBody(eventKey, recipientType, baseUrl): string
                                 //   — no PII; link to /login only

Models/
  NotificationErrorLog.php       // record(eventId, message): void
                                 // findByDept(deptId): array
                                 // countByDept(deptId): int

Controllers/
  NotificationController.php     // index() — log view
                                 // send()  — trigger processor

Views/
  notifications/
    index.php    // paginated event log + failed count badge + Send Now button
    errors.php   // institution_admin error detail list
```

No new table for events — `notification_events` was created in M6.

---

## 4. Data model

### 4.1 New table — `notification_error_log`

```sql
CREATE TABLE notification_error_log (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_event_id INT UNSIGNED NOT NULL,
    error_message         TEXT NOT NULL,
    attempted_at          DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_event (notification_event_id),
    CONSTRAINT fk_nel_event FOREIGN KEY (notification_event_id)
        REFERENCES notification_events(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.2 Existing table — `notification_events` (M6, no changes)

Key columns used by M7:

| Column | Purpose |
|--------|---------|
| `id` | PK |
| `event_key` | Determines template to use |
| `student_id` | Used to resolve dept_id for dept_admin lookup |
| `actor_id` | Actor (not used for email body but logged) |
| `recipient_type` | `'student'` or `'dept_admin'` — drives recipient lookup |
| `recipient_id` | For `'student'`: student's `id`; for `'dept_admin'`: NULL (resolved at send time) |
| `change_request_id` | Optional FK; stored in payload for context |
| `payload` | JSON, no PII — field keys, dept_id, enrolment_serial |
| `sent_at` | NULL until successfully sent |
| `created_at` | Processing order |

### 4.3 `config/mail.php` schema

```php
return [
    'host'         => 'smtp.example.com',
    'port'         => 587,
    'username'     => 'no-reply@college.edu',
    'password'     => 'secret',
    'encryption'   => 'tls',          // 'tls' | 'ssl'
    'from_address' => 'no-reply@college.edu',
    'from_name'    => 'Student Information System',
    'system_name'  => 'College SIS',  // appears in email subjects
    'max_per_run'  => 200,            // max events processed per Send Now call
];
```

---

## 5. Processing flow

### 5.1 `NotificationProcessor::process(?int $deptId): array`

Returns `['sent' => int, 'skipped' => int, 'failed' => int]`.

```
1. Load up to config('mail.max_per_run') rows:
   SELECT * FROM notification_events
   WHERE sent_at IS NULL [AND student.department_id = $deptId if set]
   ORDER BY created_at ASC
   LIMIT $maxPerRun

   Join students ON students.id = notification_events.student_id
   to get department_id.

2. For each event row:
   a. Resolve recipient email(s):
      - recipient_type = 'student':
          email = SELECT student_email FROM student_profiles WHERE student_id = event.student_id
          → if null/empty: NotificationErrorLog::record(event.id, 'No student email on file')
            skipped++; continue
          → emails = [email]
      - recipient_type = 'dept_admin':
          emails = SELECT email FROM users WHERE department_id = student.department_id
                   AND role = 'dept_admin' AND status = 'active'
          → if empty: NotificationErrorLog::record(event.id, 'No active dept admin found')
            skipped++; continue

   b. For each email address in emails:
      i.  Build PHPMailer instance from config/mail.php
      ii. Set Subject = EmailTemplate::getSubject(event.event_key, event.recipient_type)
      iii.Set Body    = EmailTemplate::getBody(event.event_key, event.recipient_type, baseUrl)
      iv. Set To      = email address
      v.  Try mailer->send()
          - Success: (continue to next address)
          - Exception: NotificationErrorLog::record(event.id, $e->getMessage())
            failed++; mark this event failed but do not break loop

   c. If at least one address succeeded AND no failure:
      UPDATE notification_events SET sent_at = date() WHERE id = event.id
      sent++
      [Note: if even one address fails, leave sent_at = NULL for retry.
       Partial sends to multiple dept admins are re-attempted in full on the next run.
       Accepted in v1 as the simple, safe choice.]
```

### 5.2 Recipient resolution queries

**Student email:**
```sql
SELECT student_email
FROM student_profiles
WHERE student_id = :studentId
```
Returns null if no profile row or `student_email` is blank.

**Dept admin emails:**
```sql
SELECT email
FROM users
WHERE department_id = :deptId
  AND role = 'dept_admin'
  AND status = 'active'
```
Returns zero or more rows.

---

## 6. Email templates (`EmailTemplate`)

### 6.1 `getSubject(string $eventKey, string $recipientType): string`

| event_key | recipient_type | Subject |
|-----------|---------------|---------|
| `submission_approved` | `student` | "Your form submission has been approved — {system_name}" |
| `submission_approved` | `dept_admin` | "Student submission approved — {system_name}" |
| `rtc_created_by_student` | `dept_admin` | "New change request pending review — {system_name}" |
| `rtc_created_by_staff` | `student` | "A change request has been raised on your record — {system_name}" |
| `rtc_created_by_staff` | `dept_admin` | "New staff-initiated change request pending — {system_name}" |
| `rtc_approved` | `student` | "Your change request has been approved — {system_name}" |
| `rtc_approved` | `dept_admin` | "Change request approved — {system_name}" |
| `rtc_rejected` | `student` | "Your change request could not be approved — {system_name}" |
| `rtc_rejected` | `dept_admin` | "Change request rejected — {system_name}" |

### 6.2 `getBody(string $eventKey, string $recipientType, string $baseUrl): string`

Plain-text body only (no HTML in v1). `{link}` = `$baseUrl . '/login'`. No PII.

| event_key | recipient_type | Body |
|-----------|---------------|------|
| `submission_approved` | `student` | "Your information form submission has been approved by the department.\n\nLog in to view your approved record:\n{link}\n\nThis is an automated message. Please do not reply." |
| `submission_approved` | `dept_admin` | "A student submission in your department has been approved.\n\nLog in to view the approvals queue:\n{link}\n\nThis is an automated message. Please do not reply." |
| `rtc_created_by_student` | `dept_admin` | "A student has submitted a change request that requires your review.\n\nLog in to view pending requests:\n{link}\n\nThis is an automated message. Please do not reply." |
| `rtc_created_by_staff` | `student` | "A staff member has submitted a change request on your information record. The request is pending review.\n\nLog in to view your change request history:\n{link}\n\nThis is an automated message. Please do not reply." |
| `rtc_created_by_staff` | `dept_admin` | "A staff-initiated change request has been submitted in your department and requires review.\n\nLog in to view pending requests:\n{link}\n\nThis is an automated message. Please do not reply." |
| `rtc_approved` | `student` | "Your change request has been reviewed and the requested changes have been applied to your information form.\n\nLog in to verify your updated record:\n{link}\n\nThis is an automated message. Please do not reply." |
| `rtc_approved` | `dept_admin` | "A change request in your department has been approved and the changes have been applied.\n\nLog in to view the record:\n{link}\n\nThis is an automated message. Please do not reply." |
| `rtc_rejected` | `student` | "Your change request has been reviewed and could not be approved at this time. Log in to view the reason and submit a new request if needed:\n{link}\n\nThis is an automated message. Please do not reply." |
| `rtc_rejected` | `dept_admin` | "A change request in your department has been rejected.\n\nLog in to view the details:\n{link}\n\nThis is an automated message. Please do not reply." |

`{system_name}` and `{link}` are substituted at render time; no other variables.

---

## 7. Controller design

### `NotificationController`

#### `GET /notifications` — `index()`
- Roles: `dept_admin`, `institution_admin`
- For `dept_admin`: loads `notification_events` JOIN `students` WHERE `students.department_id = deptId`, ordered by `created_at DESC`, paginated (50/page)
- For `institution_admin`: same query across all depts; optional `?department_id=` filter
- Passes to view:
  - `$events` — paginated event rows (no student name — enrolment_serial only)
  - `$failedCount` — `NotificationErrorLog::countByDept(deptId)`
  - `$departments` — for inst_admin dept filter
  - Filter state: `?event_key=`, `?recipient_type=`, `?status=` (sent / pending / all)

#### `POST /notifications/send` — `send()`
- Roles: `dept_admin`, `institution_admin`
- CSRF required
- `dept_admin`: calls `NotificationProcessor::process(Auth::departmentId())`
- `institution_admin`: calls `NotificationProcessor::process(null)` (all depts), optionally scoped by `?department_id=` POST param
- Flash: "Sent {N} notifications. {M} skipped (no email on file). {K} failed (SMTP error)."
- Redirect to `/notifications`

#### `GET /notifications/errors` — `errors()`
- Roles: `institution_admin` only
- Lists `notification_error_log` JOIN `notification_events`, newest first, paginated 50/page
- Columns: event ID, event key, error message (truncated 200 chars), attempted_at, current status (sent/pending)

---

## 8. View designs

### 8.1 `notifications/index.php`

```
┌────────────────────────────────────────────────────────────────────┐
│  Notifications Log                               [Send Now ▶]       │
│  inst_admin: [ Department ▼ ]                                       │
│  Filter: [ Event Type ▼ ] [ Recipient ▼ ] [ Status ▼ ]            │
├──────┬───────────────────────────┬───────────┬────────┬────────────┤
│  ID  │ Event                     │ Recipient │ Status │ Created At │
├──────┼───────────────────────────┼───────────┼────────┼────────────┤
│  42  │ submission_approved       │ student   │ Sent   │ 28 Jun...  │
│  43  │ submission_approved       │ dept_admin│ Pending│ 28 Jun...  │
│  ...                                                               │
└────────────────────────────────────────────────────────────────────┘
⚠ 3 failed events  [View Errors →]          « Prev  Page 1 / 3  Next »
```

- "Send Now" button renders as a Bootstrap form with POST to `/notifications/send` + CSRF
- Status column: green "Sent" badge + sent_at datetime, or amber "Pending" badge
- Student column: enrolment_serial (not name)
- "View Errors" link only appears when `failedCount > 0`; links to `/notifications/errors` (inst_admin only)

### 8.2 `notifications/errors.php`

```
┌──────────────────────────────────────────────────────────────────┐
│  Failed Notification Attempts                   ← Back           │
├──────┬──────────────────────┬──────────────────────┬────────────┤
│ Evt  │ Event Key            │ Error (truncated)     │ Attempted  │
├──────┼──────────────────────┼──────────────────────┼────────────┤
│  43  │ submission_approved  │ SMTP Error: conn...   │ 28 Jun...  │
└──────────────────────────────────────────────────────────────────┘
```

---

## 9. RBAC summary

| Action | student | staff | dept_admin | institution_admin |
|--------|:-------:|:-----:|:----------:|:-----------------:|
| Receive email notifications | ✓ | — | ✓ | — |
| View `/notifications` | ✗ | ✗ | ✓ (own dept) | ✓ (all) |
| POST `/notifications/send` | ✗ | ✗ | ✓ (own dept) | ✓ (all) |
| View `/notifications/errors` | ✗ | ✗ | ✗ | ✓ |

---

## 10. `NotificationProcessor` — `process()` behaviour edge cases

| Scenario | Behaviour |
|----------|-----------|
| Student has no `student_email` | Skip; `NotificationErrorLog::record`; `skipped++` |
| No active dept_admin in dept | Skip; `NotificationErrorLog::record`; `skipped++` |
| PHPMailer throws (SMTP timeout) | `NotificationErrorLog::record` error msg; `failed++`; `sent_at` left NULL |
| Event already has `sent_at` set | Excluded from load query (not processed at all) |
| Multiple dept_admin emails | All sent; if any fails, `sent_at` not set; all retried next run |
| `max_per_run` reached mid-batch | Remaining events stay queued; next Send Now continues |
| Same event row processed twice concurrently | Second processor hits an already-set `sent_at` (set by the first) so no duplicate send; race window is small but acceptable in v1 |

---

## 11. Routes

```
# M7 Notifications
GET  /notifications          NotificationController::index()
POST /notifications/send     NotificationController::send()
GET  /notifications/errors   NotificationController::errors()
```

Static paths only — no `{param}` wildcards needed.

---

## 12. Migrations

| File | Change |
|------|--------|
| `023_create_notification_error_log.sql` | Create `notification_error_log` table |

---

## 13. Tests (outline — detailed in Tasks)

| Test | Type | What it verifies |
|------|------|-----------------|
| `EmailTemplateTest` | Unit | All 9 subject+body combinations; no PII strings in output |
| `RecipientResolutionTest` | Unit | student with email → resolved; no email → null; dept_admin → array of emails; no dept_admin → empty |
| `ProcessorSentTest` | Integration | Event sent → `sent_at` set; `notification_error_log` not written |
| `ProcessorSkipNoEmailTest` | Integration | Student with no email → `sent_at` null; error log row created; skipped count incremented |
| `ProcessorFailedSmtpTest` | Integration | PHPMailer throws → error log row; `sent_at` null; failed count incremented |
| `ProcessorIdempotentTest` | Integration | Already-sent event (`sent_at` set) → not re-processed |
| `ProcessorDeptScopeTest` | Integration | `process(deptId)` only processes events for that dept |
| `ProcessorMaxPerRunTest` | Integration | Only `max_per_run` events processed per call |

PHPMailer is tested via a stub (injected transport) — no live SMTP in unit/integration tests.

---

## 14. Traceability

| Req ID | Requirement | Design element |
|--------|-------------|----------------|
| A1 | Notify student on submission_approved | `NotificationProcessor` + `EmailTemplate::getSubject/getBody` + recipient resolver |
| A2 | Notify dept_admin on submission_approved | Same; recipient_type = 'dept_admin'; query users by dept |
| A3–A9 | All other event × recipient combinations | Same processor; 9 template pairs in `EmailTemplate` |
| B1 | View notification log | `NotificationController::index()` + `notifications/index.php` |
| B2 | Send Now trigger | `NotificationController::send()` + `NotificationProcessor::process()` |
| B3 | View failed notifications | `NotificationController::errors()` + `notifications/errors.php` |
| §2.2 | No PII in email body | `EmailTemplate` — bodies contain only static text and `$baseUrl/login` |
| §2.3 | SMTP config | `config/mail.php` + `config/mail.php.example` |
| §2.6 | Error log | `notification_error_log` table + `NotificationErrorLog` model |
| §7 NFR | Atomicity per event | Try/catch per event; PHPMailer send; `sent_at` update only on success |
| §7 NFR | max_per_run | `Config::get('mail.max_per_run')` LIMIT in SELECT |

---

## 15. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, the next step is **Stage 3: Tasks** — a full, prioritised, estimated task list for implementing M7 in Claude Code.
