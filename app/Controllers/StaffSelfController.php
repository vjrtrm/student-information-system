<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\MasterAuditLogger;
use App\Middleware\RoleMiddleware;
use App\Models\StaffUser;

class StaffSelfController extends Controller
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function profileForm(): void
    {
        RoleMiddleware::handle(['staff', 'dept_admin', 'institution_admin']);
        $user = StaffUser::findById((int)Auth::id());

        // Fetch department name separately
        $deptName = null;
        if (!empty($user['department_id'])) {
            $dept = Db::selectOne("SELECT name FROM departments WHERE id = ?", [(int)$user['department_id']]);
            $deptName = $dept['name'] ?? null;
        }

        $this->render('staff/profile', [
            'title'     => 'My Profile',
            'user'      => $user,
            'dept_name' => $deptName,
            'errors'    => [],
        ]);
    }

    public function profileUpdate(): void
    {
        RoleMiddleware::handle(['staff', 'dept_admin', 'institution_admin']);
        $this->requireCsrf();

        $userId = (int)Auth::id();
        $name   = $this->input('name', '');
        $errors = [];

        if (empty($name)) $errors['name'] = 'Name is required.';

        if (!empty($errors)) {
            $user = StaffUser::findById($userId);
            $deptName = null;
            if (!empty($user['department_id'])) {
                $dept = Db::selectOne("SELECT name FROM departments WHERE id = ?", [(int)$user['department_id']]);
                $deptName = $dept['name'] ?? null;
            }
            $this->render('staff/profile', [
                'title'     => 'My Profile',
                'user'      => $user,
                'dept_name' => $deptName,
                'errors'    => $errors,
            ]);
            return;
        }

        Db::execute(
            "UPDATE users SET name = ?, staff_code = ?, updated_at = ? WHERE id = ?",
            [$name, $this->input('staff_code', '') ?: null, date('Y-m-d H:i:s'), $userId]
        );

        MasterAuditLogger::log('update_profile', 'staff_user', $userId, ['name' => $name]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated.'];
        $this->redirect('/staff/profile');
    }

    public function changePasswordForm(): void
    {
        // No role guard — accessible even before dashboard when must_change_password=1
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }

        $user       = StaffUser::findById((int)Auth::id());
        $mustChange = $user ? (bool)(int)($user['must_change_password'] ?? 0) : false;

        $this->render('staff/change_password', [
            'title'       => 'Change Password',
            'must_change' => $mustChange,
            'errors'      => [],
        ]);
    }

    public function changePassword(): void
    {
        // No role guard — accessible in must_change_password state
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }
        $this->requireCsrf();

        $userId = (int)Auth::id();
        $user   = StaffUser::findById($userId);
        if (!$user) {
            $this->redirect('/login');
            return;
        }

        $mustChange      = (bool)(int)($user['must_change_password'] ?? 0);
        $newPassword     = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $errors          = [];

        if (!$mustChange) {
            if (!password_verify($currentPassword, (string)$user['password_hash'])) {
                $errors['current_password'] = 'Current password is incorrect.';
            }
        }

        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            $errors['new_password'] = 'New password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            $this->render('staff/change_password', [
                'title'       => 'Change Password',
                'must_change' => $mustChange,
                'errors'      => $errors,
            ]);
            return;
        }

        Db::execute(
            "UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = ? WHERE id = ?",
            [password_hash($newPassword, PASSWORD_BCRYPT), date('Y-m-d H:i:s'), $userId]
        );

        MasterAuditLogger::log('change_password', 'staff_user', $userId);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Password changed successfully.'];
        $this->redirect('/dashboard');
    }
}
