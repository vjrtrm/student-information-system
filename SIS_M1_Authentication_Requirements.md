# SIS — Module 1: Authentication & Access Control
## Stage 1: Requirements (for review & approval)

**Project:** Student Information System (SIS)
**Module:** 1 of 12 — Authentication & Access Control
**Document stage:** Requirements → _Design_ → _Tasks_ (this is Requirements; design follows after approval)
**Version:** 0.1 (Draft) · June 2026
**Status:** Awaiting review & approval

---

## 1. Purpose & objectives

This module establishes how every user signs in to the SIS and what each user is allowed to see and do. It is the foundation that all later modules depend on, because every screen and action is gated by identity (who you are) and authorisation (your role and department).

Objectives:

- Let students sign in with credentials they already know (mobile number + date of birth).
- Let staff and admins sign in securely with email + password, including self-service password reset.
- Enforce role-based access control (RBAC) with department scoping, so users only reach what they are entitled to.
- Protect accounts and sessions against common attacks (brute force, hijacking).

## 2. In scope

- Student login (mobile + date of birth).
- Staff / Admin login (email + password) and password reset.
- Roles, permissions and department scoping (Student, Department Staff, Department Admin, Institution Admin).
- Session management, inactivity timeout, and account lockout.
- Audit logging of authentication events.

## 3. Out of scope (this module)

- Creating the underlying student/staff accounts (handled by Module 3 Onboarding and Module 9 Staff Management).
- Single Sign-On / Active Directory integration (future).
- SMS-based OTP delivery (email OTP only in v1; SMS is a v2 consideration).

## 4. Roles involved

| Role | Signs in with | Default reach |
|------|---------------|---------------|
| Student | Mobile + DOB | Own record only |
| Department Staff | Email + password | Their department's students |
| Department Admin (staff-admin) | Email + password | Full CRUD over their department's data |
| Institution Admin | Email + password | All departments (where configured) |

## 5. Assumptions & dependencies

- Student accounts already exist (created via bulk upload, Module 3) with a valid mobile number and DOB.
- Each staff/admin account is linked to exactly one department.
- The department master exists (Module 2) so accounts can be scoped.
- Email delivery (SMTP) is available for OTP/reset emails, and follows the no-PII-in-email rule.

---

## 6. Epics & user stories

### Epic A — Student login

**A1. Sign in with mobile + DOB**
As a student, I want to log in using my registered mobile number and date of birth so that I can access my profile without managing a password.

Acceptance criteria:

- Given a mobile number and DOB that match an existing student record, when the student submits the login form, then they are authenticated and taken to their dashboard.
- Given a non-matching mobile/DOB pair, when submitted, then login is refused with a generic error ("Invalid login details"), without revealing which value was wrong.
- The mobile field accepts exactly 10 digits; DOB is chosen via a date picker.
- A student can only ever reach their own record after login.

**A2. Optional OTP step (configurable)**
As the institution, I want the option to require a one-time passcode after the mobile+DOB check, so that login can be strengthened when policy demands.

Acceptance criteria:

- Given OTP login is enabled in configuration, when a student passes the mobile+DOB check, then a one-time passcode is sent to their registered email and must be entered to complete login.
- Given OTP login is disabled, then mobile+DOB alone completes login.
- The OTP is valid for 15 minutes and single-use.

### Epic B — Staff & Admin login

**B1. Sign in with email + password**
As a staff or admin user, I want to log in with my college email and password so that I can manage my department's data.

Acceptance criteria:

- Given valid email + password, when submitted, then the user is authenticated and routed to the dashboard appropriate to their role.
- Passwords are stored only as bcrypt hashes; plaintext passwords are never stored or logged.
- Given invalid credentials, then login is refused with a generic error.

**B2. Forgot / reset password**
As a staff or admin user, I want to reset my password if I forget it, so that I can regain access on my own.

Acceptance criteria:

- Given a registered email, when the user requests a reset, then an OTP (or secure link) is emailed, valid for 15 minutes.
- The new password must be at least 8 characters and contain at least one number; the user confirms it twice.
- Reset emails contain no PII beyond the reset link/OTP.
- After a successful reset, old sessions for that account are invalidated.

### Epic C — Roles, permissions & department scoping

**C1. Role-based access control**
As the institution, I want each user's role to determine what they can access, so that users cannot reach functions outside their authority.

Acceptance criteria:

- Every route/action is checked server-side against the user's role on every request; the client is never trusted.
- Given a Department Staff user attempting an Admin-only action, then the request is refused with a 403 (Forbidden).
- Roles available: Student, Department Staff, Department Admin, Institution Admin.

**C2. Department scoping**
As a department staff/admin user, I want to see and act only on my own department's students, so that data stays correctly partitioned.

Acceptance criteria:

- Given a department-scoped user, when they view any student list/report, then only their department's records are returned.
- Given a department-scoped user attempting to open a student from another department (e.g. by guessing an ID), then access is refused.
- Each department has exactly one Department Admin with full CRUD over that department's data.
- An Institution Admin (where configured) sees all departments.

### Epic D — Session & account security

**D1. Account lockout on repeated failures**
As the institution, I want repeated failed logins to lock an account temporarily, so that brute-force attempts are blocked.

Acceptance criteria:

- After 5 consecutive failed attempts for an account, further attempts are refused for 15 minutes.
- The lockout is enforced server-side (stored), not only in the browser.
- A successful login resets the failure counter.

**D2. Secure sessions & inactivity timeout**
As a user, I want my session to be protected and to end after inactivity, so that my account isn't misused on a shared device.

Acceptance criteria:

- Session cookies are set with HttpOnly and Secure flags; the session ID is regenerated on login.
- After 30 minutes of inactivity (configurable) the session expires and the user must sign in again.
- Logout immediately invalidates the session.

**D3. Authentication audit trail**
As an admin, I want authentication events recorded, so that access can be reviewed.

Acceptance criteria:

- Login success, login failure, lockout, password reset and logout are recorded with user reference, timestamp and source.
- Audit records contain no plaintext passwords or OTPs.

---

## 7. Non-functional requirements (module-relevant)

- **Security:** bcrypt password hashing; CSRF token on all state-changing auth forms; generic error messages (no stack traces); HTTPS enforced.
- **Performance:** login responds in under 2 seconds on a standard college LAN.
- **Accessibility/mobile:** login and reset screens fully usable on screens ≥ 320px, tap targets ≥ 44×44px.
- **Privacy:** OTP/reset emails carry no PII beyond the code/link.

## 8. Open questions

1. Should student OTP be ON or OFF by default for v1?
2. Confirm inactivity timeout (proposed 30 min) and lockout threshold (proposed 5 attempts / 15 min).
3. Is there an Institution Admin role at all, or is admin always department-scoped? (Spec currently allows both.)
4. MySQL target version — note says "5.4"; confirm intended (e.g. 5.7).

## 9. Sign-off

| Reviewer | Decision (Approve / Changes requested) | Date | Notes |
|----------|----------------------------------------|------|-------|
|          |                                        |      |       |

> On approval of this Requirements document, the next step is **Stage 2: Design** for Module 1 (authentication flows, data needs, screen behaviour, validation, error handling), which will also be submitted for your review before any Task breakdown.
