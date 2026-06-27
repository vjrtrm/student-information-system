# SIS — Module 1: Authentication & Access Control
## Stage 3: Tasks (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 1 of 12 — Authentication & Access Control
**Document stage:** Requirements ✅ → Design ✅ → **Tasks (this document)**
**Version:** 0.1 (Draft) · June 2026
**Status:** Awaiting review & approval
**Traces:** `SIS_M1_Authentication_Design.md`

---

## 1. How to read this

Each task has an ID, a clear deliverable, an estimate (ideal hours), dependencies, and "done when" criteria. Estimates assume one developer familiar with the PHP MVC stack. Tasks are grouped by layer; the suggested build order is in §8.

Legend — **Est**: ideal hours · **Dep**: prerequisite task IDs · Priority: P1 = required for module to function, P2 = hardening/nice-to-have.

---

## 2. Foundation & data layer

| ID | Task | Est | Dep | Priority | Done when |
|----|------|----:|-----|:--:|-----------|
| M1-T01 | Create DB migrations for `users` auth columns (role, department_id, status, failed_attempts, locked_until, password_hash) | 3 | — | P1 | Migration runs on MySQL 5.x; columns + `users(email)` unique index present |
| M1-T02 | Create migration for `students` auth subset (mobile, dob, department_id, status, failed_attempts, locked_until) + `students(mobile)` index | 2 | — | P1 | Migration runs; mobile indexed |
| M1-T03 | Create migrations for `password_resets`, `login_otps`, `auth_audit_log` (+ indexes) | 3 | — | P1 | Tables created with FKs/indexes per design §4 |
| M1-T04 | Seed: one institution admin + one dept admin + sample staff/student for testing | 2 | T01–T03 | P1 | Seed script creates working test accounts |

## 3. Helpers (core services)

| ID | Task | Est | Dep | Priority | Done when |
|----|------|----:|-----|:--:|-----------|
| M1-T05 | `Auth` helper: session create/destroy, `current_user()`, password hash/verify (bcrypt), session-id regenerate | 6 | T01 | P1 | Unit-tested login/logout + hash verify |
| M1-T06 | `Lockout` helper: increment/reset attempts, set/check `locked_until` (shared students+users) | 4 | T01,T02 | P1 | 5-fail → lock; success resets; unit-tested |
| M1-T07 | `Otp` helper: generate 6-digit, hash+store, verify, expiry, single-use | 4 | T03 | P1 | Valid within TTL; rejected after expiry/reuse |
| M1-T08 | `Mailer` (PHPMailer) wrapper + PII-safe OTP/reset templates | 4 | — | P1 | Sends test email; templates contain only code/link |
| M1-T09 | `AuditLogger` helper: write `auth_audit_log` for all events | 2 | T03 | P1 | Each auth event creates a record; no secrets stored |
| M1-T10 | Config loader for `auth.*` parameters with defaults (§9 design) | 2 | — | P1 | Toggles read from config; defaults applied |

## 4. Middleware (access control)

| ID | Task | Est | Dep | Priority | Done when |
|----|------|----:|-----|:--:|-----------|
| M1-T11 | `AuthMiddleware`: require valid session, else redirect to login | 3 | T05 | P1 | Unauthenticated request → login redirect |
| M1-T12 | `RoleMiddleware`: allow declared roles, else 403 | 3 | T05 | P1 | Staff hitting admin route → 403 page |
| M1-T13 | `DepartmentScopeMiddleware`: inject + enforce department filter; institution_admin bypass | 4 | T05 | P1 | Scoped user sees only own dept; cross-dept ID refused |

## 5. Controllers & flows

| ID | Task | Est | Dep | Priority | Done when |
|----|------|----:|-----|:--:|-----------|
| M1-T14 | `AuthController` student login (mobile+DOB), lockout + audit, route to dashboard | 5 | T05,T06,T09 | P1 | Flow §5.1 works incl. generic errors + lockout |
| M1-T15 | `AuthController` staff/admin login (email+password) + role-based routing | 4 | T05,T06,T09 | P1 | Flow §5.2 works |
| M1-T16 | OTP step wiring (conditional on `student_otp_enabled`) | 4 | T07,T08,T14 | P1 | OTP enforced when enabled; bypassed when off |
| M1-T17 | `PasswordResetController`: forgot (neutral response) + token email | 4 | T03,T08,T09 | P1 | Flow §5.4 steps 1–2; no account enumeration |
| M1-T18 | `PasswordResetController`: verify token + set new password + invalidate sessions | 4 | T17,T05 | P1 | Steps 3–4; old sessions killed |
| M1-T19 | Logout: destroy session + audit | 1 | T05,T09 | P1 | Session invalidated immediately |

## 6. Views / UI

| ID | Task | Est | Dep | Priority | Done when |
|----|------|----:|-----|:--:|-----------|
| M1-T20 | Login page (tabs: Student · Staff/Admin), Bootstrap 5, responsive ≥320px, date picker | 5 | T14,T15 | P1 | Both tabs functional + validated; tap targets ≥44px |
| M1-T21 | OTP entry screen | 2 | T16 | P1 | Accepts 6-digit code; error states shown |
| M1-T22 | Forgot + Reset password screens (with client validation) | 4 | T17,T18 | P1 | Reset round-trip works on mobile + desktop |
| M1-T23 | CSRF tokens on all auth forms (generate + verify) | 3 | T20–T22 | P1 | Missing/invalid token → request rejected |
| M1-T24 | 403 / generic error pages | 2 | T12 | P2 | Friendly 403 + generic auth error pages |

## 7. Testing & hardening

| ID | Task | Est | Dep | Priority | Done when |
|----|------|----:|-----|:--:|-----------|
| M1-T25 | Unit tests: Auth, Lockout, Otp helpers (incl. edge cases) | 5 | T05–T07 | P1 | All branches covered; green |
| M1-T26 | Integration tests: student login, staff login, OTP on/off, reset round-trip | 5 | T14–T18 | P1 | End-to-end flows pass |
| M1-T27 | Security tests: brute-force lockout, CSRF rejection, privilege escalation, account enumeration, SQLi on auth inputs | 5 | T11–T23 | P1 | All mitigations verified |
| M1-T28 | RBAC/scoping tests: each role × route matrix (design §6) | 4 | T11–T13 | P1 | Matrix enforced; cross-dept blocked |
| M1-T29 | UI/mobile pass: login/OTP/reset on iOS Safari + Android Chrome | 3 | T20–T22 | P2 | Usable ≥320px; no layout breakage |
| M1-T30 | Audit-log verification: every event recorded, no secrets | 2 | T09,T14–T19 | P2 | Spot-check confirms records + redaction |

## 8. Suggested build order (critical path)

1. **Data layer:** T01 → T02 → T03 → T04
2. **Core services:** T05 → T06, T07, T08, T09, T10 (parallelisable after T05)
3. **Access control:** T11 → T12 → T13
4. **Flows:** T14, T15 → T16 → T17 → T18 → T19
5. **UI:** T20 → T21, T22 → T23 → T24
6. **Testing:** T25–T30 (write unit tests alongside; run integration/security at the end)

## 9. Estimate summary

| Group | Hours |
|-------|------:|
| Foundation & data (T01–T04) | 10 |
| Helpers (T05–T10) | 22 |
| Middleware (T11–T13) | 10 |
| Controllers (T14–T19) | 22 |
| Views (T20–T24) | 16 |
| Testing (T25–T30) | 24 |
| **Total** | **~104 ideal hours (~13–15 dev-days)** |

## 10. Definition of Done (module)

- All P1 tasks complete; unit/integration/security/RBAC tests green.
- All four open-question defaults (design §2) either confirmed or adjusted and reflected.
- No plaintext secrets in DB or logs; emails verified PII-safe.
- Login, OTP (toggle), reset, logout, lockout, RBAC and department scoping demonstrably working on mobile + desktop.
- Traceability holds: every Epic A–D acceptance criterion has a passing test.

## 11. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval, Module 1 is fully specified (Requirements ✅ Design ✅ Tasks ✅). The next step would be to begin the same three-stage cycle for **Module 2 — Master Data & Department Management**, unless you'd like to start implementation of Module 1 first.
