<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\AuditLogger;
use App\Helpers\Config;
use App\Helpers\Lockout;
use App\Helpers\Otp;
use App\Helpers\Mailer;
use App\Helpers\Validator;
use App\Models\Student;
use App\Models\User;

class AuthController extends Controller
{
    private const GENERIC_ERROR = 'Invalid login details.';

    public function showLogin(): void
    {
        Auth::start();
        if (Auth::check()) { $this->redirect('/dashboard'); }
        $this->render('auth/login', ['title' => 'Sign in', 'tab' => $this->input('tab', 'student')]);
    }

    /** Student login: mobile + DOB (Design §5.1). */
    public function studentLogin(): void
    {
        Auth::start();
        $this->requireCsrf();

        $mobile = (string)$this->input('mobile', '');
        $dob    = (string)$this->input('dob', '');

        if (!Validator::mobile($mobile) || !Validator::date($dob)) {
            $this->fail('student'); return;
        }

        $student = Student::findByMobile($mobile);
        if (!$student || !Student::isActive($student)) {
            AuditLogger::log('login_fail', 'unknown', null);
            $this->fail('student'); return;
        }

        $id = (int)$student['id'];
        if (Lockout::isLocked('students', $id)) {
            $this->locked('student', Lockout::minutesRemaining('students', $id)); return;
        }

        if (!hash_equals((string)$student['dob'], $dob)) {
            $r = Lockout::registerFailure('students', $id);
            AuditLogger::log($r['locked'] ? 'lockout' : 'login_fail', 'student', $id);
            $this->fail('student'); return;
        }

        Lockout::reset('students', $id);
        $deptId = $student['department_id'] !== null ? (int)$student['department_id'] : null;

        // OTP step (Design §5.3) — only when enabled AND an email is resolvable.
        // Student email arrives with the full record in Module 5; until then OTP degrades to direct login.
        if (Config::get('auth.student_otp_enabled', false)) {
            $email = $student['email'] ?? null;
            if ($email) {
                $this->startOtp('student', $id, 'student', $deptId, $email, 'Student');
                return;
            }
        }

        $this->finishLogin('student', $id, 'student', $deptId);
    }

    /** Staff/Admin login: email + password (Design §5.2). */
    public function staffLogin(): void
    {
        Auth::start();
        $this->requireCsrf();

        $email    = (string)$this->input('email', '');
        $password = (string)($_POST['password'] ?? '');

        if (!Validator::email($email) || $password === '') {
            $this->fail('staff'); return;
        }

        $user = User::findByEmail($email);
        if (!$user || !User::isActive($user)) {
            AuditLogger::log('login_fail', 'unknown', null);
            $this->fail('staff'); return;
        }

        $id = (int)$user['id'];
        if (Lockout::isLocked('users', $id)) {
            $this->locked('staff', Lockout::minutesRemaining('users', $id)); return;
        }

        if (!Auth::verifyPassword($password, (string)$user['password_hash'])) {
            $r = Lockout::registerFailure('users', $id);
            AuditLogger::log($r['locked'] ? 'lockout' : 'login_fail', 'user', $id);
            $this->fail('staff'); return;
        }

        Lockout::reset('users', $id);
        $deptId = $user['department_id'] !== null ? (int)$user['department_id'] : null;
        $this->finishLogin('user', $id, (string)$user['role'], $deptId);
    }

    public function showOtp(): void
    {
        Auth::start();
        if (empty($_SESSION['otp_pending'])) { $this->redirect('/login'); }
        $this->render('auth/otp', ['title' => 'Enter code']);
    }

    public function verifyOtp(): void
    {
        Auth::start();
        $this->requireCsrf();
        $pending = $_SESSION['otp_pending'] ?? null;
        if (!$pending) { $this->redirect('/login'); }

        $code = (string)$this->input('code', '');
        if (Otp::verify($pending['type'], (int)$pending['id'], $code)) {
            unset($_SESSION['otp_pending']);
            $this->finishLogin($pending['type'], (int)$pending['id'], $pending['role'], $pending['dept']);
            return;
        }
        $this->render('auth/otp', ['title' => 'Enter code', 'error' => 'Code is invalid or expired.'], 401);
    }

    public function logout(): void
    {
        Auth::start();
        $this->requireCsrf();
        Auth::logout();
        $this->redirect('/login');
    }

    // ---- helpers ----

    private function startOtp(string $type, int $id, string $role, ?int $dept, string $email, string $firstName): void
    {
        $_SESSION['otp_pending'] = ['type' => $type, 'id' => $id, 'role' => $role, 'dept' => $dept];
        $code = Otp::generate($type, $id);
        Mailer::sendLoginOtp($email, $firstName, $code);
        $this->redirect('/login/otp');
    }

    private function finishLogin(string $type, int $id, string $role, ?int $dept): void
    {
        Auth::login($type, $id, $role, $dept);
        AuditLogger::log('login_success', $type, $id);
        $this->redirect('/dashboard');
    }

    private function fail(string $tab): void
    {
        $this->render('auth/login', ['title' => 'Sign in', 'tab' => $tab, 'error' => self::GENERIC_ERROR], 401);
    }

    private function locked(string $tab, int $minutes): void
    {
        $msg = "Account temporarily locked. Try again in {$minutes} minute(s).";
        $this->render('auth/login', ['title' => 'Sign in', 'tab' => $tab, 'error' => $msg], 423);
    }
}
