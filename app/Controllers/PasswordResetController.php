<?php
namespace App\Controllers;

use App\Helpers\AuditLogger;
use App\Helpers\Auth;
use App\Helpers\Config;
use App\Helpers\Mailer;
use App\Helpers\ResetToken;
use App\Helpers\Validator;
use App\Models\User;

/** Forgot/reset password for staff & admin (Design §5.4). Email = users only. */
class PasswordResetController extends Controller
{
    private const NEUTRAL = 'If that email is registered, a reset link has been sent.';

    public function showForgot(): void
    {
        Auth::start();
        $this->render('auth/forgot-password', ['title' => 'Forgot password']);
    }

    public function sendReset(): void
    {
        Auth::start();
        $this->requireCsrf();
        $email = (string)$this->input('email', '');

        // Always respond neutrally — no account enumeration (Design §5.4).
        if (Validator::email($email)) {
            $user = User::findByEmail($email);
            if ($user && User::isActive($user)) {
                $token = ResetToken::create((int)$user['id']);
                $base  = Config::get('app.base_url', '');
                $link  = $base . '/reset-password?uid=' . (int)$user['id'] . '&token=' . $token;
                Mailer::sendPasswordReset($user['email'], User::firstName($user), $link);
                AuditLogger::log('reset_request', 'user', (int)$user['id']);
            }
        }
        $this->render('auth/forgot-password', ['title' => 'Forgot password', 'notice' => self::NEUTRAL]);
    }

    public function showReset(): void
    {
        Auth::start();
        $this->render('auth/reset-password', [
            'title' => 'Reset password',
            'uid'   => (int)$this->input('uid', 0),
            'token' => (string)$this->input('token', ''),
        ]);
    }

    public function submitReset(): void
    {
        Auth::start();
        $this->requireCsrf();

        $uid      = (int)$this->input('uid', 0);
        $token    = (string)$this->input('token', '');
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['password_confirm'] ?? '');

        $err = null;
        if (!Validator::password($password)) {
            $err = 'Password must be at least ' . (int)Config::get('auth.password_min_length', 8) . ' characters and include a number.';
        } elseif ($password !== $confirm) {
            $err = 'Passwords do not match.';
        } elseif (!ResetToken::consume($uid, $token)) {
            $err = 'This reset link is invalid or has expired.';
        }

        if ($err) {
            $this->render('auth/reset-password', [
                'title' => 'Reset password', 'uid' => $uid, 'token' => $token, 'error' => $err,
            ], 422);
            return;
        }

        User::updatePassword($uid, Auth::hashPassword($password));
        AuditLogger::log('reset_success', 'user', $uid);
        // Invalidate the current session so the user re-authenticates with the new password.
        Auth::logout();
        $this->render('auth/login', ['title' => 'Sign in', 'tab' => 'staff', 'notice' => 'Password updated — please sign in.']);
    }
}
