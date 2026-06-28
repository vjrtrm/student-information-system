# SIS — Module 7: Notifications
## Stage 3: Tasks (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 7 of 12 — Notifications
**Document stage:** Requirements ✅ → Design ✅ → **Tasks (this document)**
**Version:** 0.1 (Draft) · June 2026 · **Status:** Approved — proceed to implementation
**Traces:** `SIS_M7_Notifications_Design.md`

---

## 1. How to read this

Each task: ID, deliverable, estimate (ideal hours), dependencies, priority, "done when". P1 = required for the module to function; P2 = hardening/polish. Estimates assume M1–M6 codebase in place. Build order in §8.

---

## 2. Data layer

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M7-T01 | Migration `023_create_notification_error_log.sql` — table: id, notification_event_id INT UNSIGNED NOT NULL FK→notification_events(id), error_message TEXT NOT NULL, attempted_at DATETIME NOT NULL; KEY idx_event (notification_event_id) | 1 | — | P1 | Table created on MySQL 5.7; FK enforced |
| M7-T02 | Config file `config/mail.php.example` — all keys present with placeholder values: host, port, username, password, encryption, from_address, from_name, system_name, max_per_run (default 200); add `config/mail.php` to `.gitignore` | 1 | — | P1 | `config/mail.php.example` committed; `Config::get('mail.host')` works when developer copies file to `config/mail.php` |

---

## 3. Helpers

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M7-T03 | `EmailTemplate` (`app/Helpers/EmailTemplate.php`) — two static methods: `getSubject(string $eventKey, string $recipientType): string`; `getBody(string $eventKey, string $recipientType, string $baseUrl): string`; covers all 9 event × recipient_type combinations per design §6; `{system_name}` substituted from `Config::get('mail.system_name')`; body link = `$baseUrl . '/login'`; throws `\InvalidArgumentException` for unknown event_key+recipientType pair | 3 | M7-T02 | P1 | Unit tested: all 9 combinations return correct subject and body; body contains no name/mobile/PII tokens; unknown pair throws |
| M7-T04 | `NotificationProcessor` (`app/Helpers/NotificationProcessor.php`) — single public static method `process(?int $deptId = null): array` returning `['sent'=>int,'skipped'=>int,'failed'=>int]`; loads up to `max_per_run` unsent events (sent_at IS NULL) ordered by created_at ASC, joining students for department_id; applies optional dept filter; for each event: (a) resolves recipient email(s) via `resolveEmails(event)` private method; (b) builds PHPMailer instance from `config/mail.php`; (c) calls `EmailTemplate::getSubject/getBody`; (d) sends; (e) on success marks `sent_at`; (f) on failure calls `NotificationErrorLog::record`; counts sent/skipped/failed | 8 | M7-T03, M7-T05 | P1 | Integration tested (PHPMailer stubbed): event sent → sent_at set; no email → skipped + error log; SMTP throws → failed + error log; already sent event excluded; dept scope honoured |

---

## 4. Models

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M7-T05 | `NotificationErrorLog` (`app/Models/NotificationErrorLog.php`) — three static methods: `record(int $eventId, string $message): void` — INSERT row with attempted_at = date(); `findByDept(int $deptId, int $limit = 50, int $offset = 0): array` — SELECT nel JOIN notification_events JOIN students WHERE students.department_id = deptId ORDER BY attempted_at DESC; `countByDept(int $deptId): int` — COUNT for badge | 2 | M7-T01 | P1 | record inserts row; findByDept returns rows filtered to dept; countByDept returns correct integer |

---

## 5. Controllers & routes

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M7-T06 | `NotificationController::index()` — `GET /notifications`; roles: dept_admin, institution_admin; loads paginated notification_events (50/page) JOIN students for dept scope; applies filters (?event_key=, ?recipient_type=, ?status= sent/pending/all); passes failedCount from `NotificationErrorLog::countByDept`; for institution_admin: all depts + ?department_id= filter; renders `notifications/index.php` | 4 | M7-T05 | P1 | Dept_admin sees only own dept events; institution_admin sees all with dept filter; filters narrow results correctly; sent_at=NULL rows show "Pending"; no student name in query results |
| M7-T07 | `NotificationController::send()` — `POST /notifications/send`; roles: dept_admin, institution_admin; CSRF; dept_admin calls `NotificationProcessor::process(Auth::departmentId())`; institution_admin calls `process(null)` or `process($filterDeptId)` from POST param; flash: "Sent {N} notifications. {M} skipped (no email). {K} failed (SMTP error)."; redirect to /notifications | 3 | M7-T04 | P1 | Processor called with correct scope; flash shows accurate counts; CSRF enforced; wrong role → 403 |
| M7-T08 | `NotificationController::errors()` — `GET /notifications/errors`; institution_admin only; loads `NotificationErrorLog::findByDept(null)` (all depts); paginated 50/page; renders `notifications/errors.php` | 2 | M7-T05 | P1 | Only institution_admin can access; dept_admin → 403; error rows shown newest first |
| M7-T09 | Routes — register 3 M7 routes in `public/index.php`; add `use App\Controllers\NotificationController;`; all static paths | 1 | M7-T06–M7-T08 | P1 | All 3 routes resolve; role violations return 403; CSRF on POST |

---

## 6. Views

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M7-T10 | `notifications/index.php` — Bootstrap 5 page; toolbar: Send Now button (POST form + CSRF), dept filter for institution_admin, three filter dropdowns (event_key, recipient_type, status); table columns: ID, Event Key, Recipient Type, Enrolment Serial (no name), Status badge (Sent green with datetime / Pending amber), Created At; failed-count badge when failedCount > 0 with link to /notifications/errors (institution_admin only); Bootstrap pagination | 6 | M7-T06 | P1 | All columns render; no student name shown; filters submit via GET; Send Now POSTs with CSRF; Pending/Sent badges correct; pagination controls render |
| M7-T11 | `notifications/errors.php` — Bootstrap 5 page; table: Event ID, Event Key, Error Message (truncated 200 chars), Attempted At; back link to /notifications; empty state message | 3 | M7-T08 | P1 | Error rows render; long messages truncated; empty state shows when no errors |
| M7-T12 | Nav update (`layouts/app.php`) — add "Notifications" link for dept_admin and institution_admin roles pointing to /notifications | 1 | M7-T09 | P2 | Link visible for correct roles; active state highlighted |

---

## 7. Tests

| ID | Task | Est | Dep | Pri | Done when |
|----|------|----:|-----|:--:|-----------|
| M7-T13 | Unit: `EmailTemplateTest` — assert `getSubject` and `getBody` for all 9 event × recipient_type combos; assert body does not contain any PII tokens (names, mobile, aadhaar); assert body contains '/login'; assert unknown pair throws `InvalidArgumentException` | 4 | M7-T03 | P1 | All assertions green |
| M7-T14 | Unit: `RecipientResolutionTest` — test private resolver via `process()` with mocked DB or direct Db calls: student with email → email resolved; student with no profile or blank student_email → skipped; dept_admin present → emails resolved; no active dept_admin → skipped | 3 | M7-T04 | P1 | Green |
| M7-T15 | Integration: `ProcessorSentTest` — seed notification_event + student_profile with email + dept_admin user; call `NotificationProcessor::process()` with PHPMailer stubbed to succeed; assert sent_at set on event row; no error_log row; return['sent']=1 | 4 | M7-T04 | P1 | Green |
| M7-T16 | Integration: `ProcessorSkipNoEmailTest` — seed event; student_profile has blank student_email; call process(); assert sent_at still null; error_log row created with 'No student email on file'; return['skipped']=1 | 3 | M7-T04 | P1 | Green |
| M7-T17 | Integration: `ProcessorFailedSmtpTest` — seed event; valid recipient email; PHPMailer stub throws; assert sent_at null; error_log row created with exception message; return['failed']=1 | 3 | M7-T04 | P1 | Green |
| M7-T18 | Integration: `ProcessorIdempotentTest` — seed event with sent_at already set; call process(); assert no second send; return['sent']=0 | 2 | M7-T04 | P1 | Green |
| M7-T19 | Integration: `ProcessorDeptScopeTest` — seed events for two departments; call process(deptId=deptA); assert only deptA events processed; deptB events remain pending | 3 | M7-T04 | P1 | Green |
| M7-T20 | Integration: `ProcessorMaxPerRunTest` — seed 5 events; set max_per_run=3 via config override; call process(); assert sent_at set on exactly 3 events; 2 remain pending | 3 | M7-T04 | P1 | Green |
| M7-T21 | Update `tests/bootstrap.php` — add `CREATE TABLE IF NOT EXISTS notification_error_log (id INTEGER PRIMARY KEY AUTOINCREMENT, notification_event_id INTEGER NOT NULL, error_message TEXT NOT NULL, attempted_at TEXT)` to `sis_test_schema()` | 1 | — | P1 | All M7 integration tests run on SQLite without MySQL |

---

## 8. PHPMailer stub strategy

PHPMailer is injected into `NotificationProcessor` via a factory callable stored in a static property so tests can swap it:

```php
// In NotificationProcessor:
private static ?\Closure $mailerFactory = null;

public static function setMailerFactory(\Closure $factory): void
{
    self::$mailerFactory = $factory;
}

private static function makeMailer(): \PHPMailer\PHPMailer\PHPMailer
{
    if (self::$mailerFactory) {
        return (self::$mailerFactory)();
    }
    // Default: real PHPMailer configured from config/mail.php
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = Config::get('mail.host');
    $mail->SMTPAuth   = true;
    $mail->Username   = Config::get('mail.username');
    $mail->Password   = Config::get('mail.password');
    $mail->SMTPSecure = Config::get('mail.encryption', 'tls');
    $mail->Port       = (int)Config::get('mail.port', 587);
    $mail->setFrom(Config::get('mail.from_address'), Config::get('mail.from_name'));
    return $mail;
}
```

Tests call `NotificationProcessor::setMailerFactory(fn() => $stub)` in `setUp` and reset in `tearDown`.

---

## 9. Build order (critical path)

1. **Data layer:** M7-T01 → M7-T02
2. **Model:** M7-T05 (NotificationErrorLog, no other deps)
3. **Helpers:** M7-T03 (EmailTemplate) → M7-T04 (NotificationProcessor, depends on both)
4. **Controller:** M7-T06 → M7-T07 → M7-T08 → M7-T09
5. **Views:** M7-T10 → M7-T11 → M7-T12
6. **Tests:** M7-T13, M7-T14 (unit, alongside helpers); M7-T21 (bootstrap, before integration); M7-T15 → M7-T16 → M7-T17 → M7-T18 → M7-T19 → M7-T20 (integration, after processor)

---

## 10. Estimate summary

| Group | Hours |
|-------|------:|
| Data layer (T01–T02) | 2 |
| Helpers (T03–T04) | 11 |
| Models (T05) | 2 |
| Controllers & routes (T06–T09) | 10 |
| Views (T10–T12) | 10 |
| Tests (T13–T21) | 26 |
| **Total** | **~61 ideal hours (~8 dev-days)** |

---

## 11. Definition of Done

- All P1 tasks complete; all unit + integration tests green (`./vendor/bin/phpunit`) on PHP 8 / MySQL 5.7.
- `NotificationProcessor::process()` correctly sends emails for all 9 event × recipient_type combinations when PHPMailer is configured.
- Skipped events (no email on file) and failed events (SMTP error) are recorded in `notification_error_log`; `sent_at` remains NULL for retry.
- Already-sent events are not re-processed (idempotent).
- `Send Now` on `/notifications` triggers the processor for the correct dept scope and shows accurate flash counts.
- Email bodies contain no PII — verified by `EmailTemplateTest`.
- `/notifications` is dept-scoped; institution_admin sees all departments with filter.
- `/notifications/errors` accessible only to institution_admin.
- `config/mail.php.example` committed; `config/mail.php` gitignored.
- Commit via `scripts/commit-module.sh "M7 Notifications: implementation complete"`; user pushes from Mac.

---

## 12. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
| Approved | Proceed to implementation | June 2026 | |

> Module 7 is fully specified and ready for implementation in Claude Code.
