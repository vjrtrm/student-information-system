<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\MasterAuditLogger;
use App\Middleware\RoleMiddleware;
use App\Models\StaffUser;

class StaffController extends Controller
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const ALLOWED_ROLES       = ['staff', 'dept_admin'];

    public function index(): void
    {
        RoleMiddleware::handle(['dept_admin', 'institution_admin']);

        $deptId      = null;
        $departments = [];
        if (Auth::role() === 'institution_admin') {
            $deptId = isset($_GET['department_id']) && (int)$_GET['department_id'] > 0
                ? (int)$_GET['department_id'] : null;
            $departments = Db::selectAll(
                "SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC"
            );
            $staff = StaffUser::findAll($deptId);
        } else {
            $staff = StaffUser::findByDept((int)Auth::departmentId());
        }

        $this->render('staff/index', [
            'title'       => 'Staff Management',
            'staff'       => $staff,
            'departments' => $departments,
            'dept_filter' => $deptId,
        ]);
    }

    public function createForm(): void
    {
        RoleMiddleware::handle(['dept_admin', 'institution_admin']);

        $departments = Auth::role() === 'institution_admin'
            ? Db::selectAll("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")
            : [];

        $this->render('staff/form', [
            'title'       => 'Add Staff Member',
            'mode'        => 'create',
            'user'        => null,
            'departments' => $departments,
            'errors'      => [],
        ]);
    }

    public function store(): void
    {
        RoleMiddleware::handle(['dept_admin', 'institution_admin']);
        $this->requireCsrf();

        $errors = [];

        $name  = $this->input('name', '');
        $email = strtolower((string)$this->input('email', ''));
        $password = (string)($_POST['password'] ?? '');

        if (empty($name)) $errors['name'] = 'Name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        }
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors['password'] = 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
        }

        // Check unique email
        if (empty($errors['email'])) {
            $existing = Db::selectOne("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existing) $errors['email'] = 'A user with this email already exists.';
        }

        if (Auth::role() === 'dept_admin') {
            $role   = 'staff';
            $deptId = (int)Auth::departmentId();
        } else {
            // institution_admin
            $role   = $this->input('role', 'staff');
            $deptId = (int)$this->input('department_id', 0);
            if (!in_array($role, self::ALLOWED_ROLES, true)) {
                $errors['role'] = 'Invalid role selected.';
            }
            if ($deptId <= 0) {
                $errors['department_id'] = 'Department is required.';
            }
        }

        if ($role === 'dept_admin' && empty($errors)) {
            if (StaffUser::hasDeptAdmin($deptId)) {
                $errors['role'] = 'This department already has a Department Admin.';
            }
        }

        if (!empty($errors)) {
            $departments = Auth::role() === 'institution_admin'
                ? Db::selectAll("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")
                : [];
            $this->render('staff/form', [
                'title'       => 'Add Staff Member',
                'mode'        => 'create',
                'user'        => null,
                'departments' => $departments,
                'errors'      => $errors,
                'old'         => ['name' => $name, 'email' => $email, 'role' => $role, 'department_id' => $deptId, 'staff_code' => $this->input('staff_code', '')],
            ]);
            return;
        }

        $newId = StaffUser::create([
            'name'                => $name,
            'email'               => $email,
            'password_hash'       => password_hash($password, PASSWORD_BCRYPT),
            'role'                => $role,
            'department_id'       => $deptId,
            'staff_code'          => $this->input('staff_code', '') ?: null,
            'status'              => 'active',
            'must_change_password' => 1,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        MasterAuditLogger::log('create', 'staff_user', $newId, ['email' => $email, 'role' => $role]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Staff member created successfully.'];
        $this->redirect('/staff');
    }

    public function editForm(int $id): void
    {
        RoleMiddleware::handle(['dept_admin', 'institution_admin']);
        $userId = (int)$id;
        $user   = $this->loadAndScope($userId);

        $departments = Auth::role() === 'institution_admin'
            ? Db::selectAll("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")
            : [];

        $this->render('staff/form', [
            'title'       => 'Edit Staff Member',
            'mode'        => 'edit',
            'user'        => $user,
            'departments' => $departments,
            'errors'      => [],
            'old'         => $user,
        ]);
    }

    public function update(int $id): void
    {
        RoleMiddleware::handle(['dept_admin', 'institution_admin']);
        $this->requireCsrf();
        $userId = (int)$id;

        if ($userId === (int)Auth::id()) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'You cannot edit your own account here.'];
            $this->redirect('/staff');
            return;
        }

        $user = $this->loadAndScope($userId);

        $errors = [];

        if (Auth::role() === 'dept_admin') {
            $data = [];
            $name = $this->input('name', '');
            if (empty($name)) { $errors['name'] = 'Name is required.'; }
            $data['name']       = $name;
            $data['staff_code'] = $this->input('staff_code', '') ?: null;
            $data['status']     = $this->input('status', 'active');
        } else {
            // institution_admin
            $data  = [];
            $name  = $this->input('name', '');
            $email = strtolower((string)$this->input('email', ''));
            $role  = $this->input('role', 'staff');
            $deptId = (int)$this->input('department_id', 0);

            if (empty($name)) $errors['name'] = 'Name is required.';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'A valid email address is required.';
            } else {
                $existing = Db::selectOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
                if ($existing) $errors['email'] = 'A user with this email already exists.';
            }
            if (!in_array($role, self::ALLOWED_ROLES, true)) $errors['role'] = 'Invalid role.';
            if ($deptId <= 0) $errors['department_id'] = 'Department is required.';

            $data = [
                'name'        => $name,
                'email'       => $email,
                'staff_code'  => $this->input('staff_code', '') ?: null,
                'role'        => $role,
                'department_id' => $deptId,
                'status'      => $this->input('status', 'active'),
            ];

            if ($role === 'dept_admin' && empty($errors)) {
                if (StaffUser::hasDeptAdmin($deptId, $userId)) {
                    $errors['role'] = 'This department already has a Department Admin.';
                }
            }
        }

        if (!empty($errors)) {
            $departments = Auth::role() === 'institution_admin'
                ? Db::selectAll("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")
                : [];
            $this->render('staff/form', [
                'title'       => 'Edit Staff Member',
                'mode'        => 'edit',
                'user'        => $user,
                'departments' => $departments,
                'errors'      => $errors,
                'old'         => array_merge($user, $data),
            ]);
            return;
        }

        StaffUser::update($userId, $data);
        MasterAuditLogger::log('update', 'staff_user', $userId, ['changed_fields' => array_keys($data)]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Staff member updated.'];
        $this->redirect('/staff');
    }

    public function toggleStatus(int $id): void
    {
        RoleMiddleware::handle(['dept_admin', 'institution_admin']);
        $this->requireCsrf();
        $userId = (int)$id;

        if ($userId === (int)Auth::id()) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'You cannot deactivate your own account.'];
            $this->redirect('/staff');
            return;
        }

        $user = $this->loadAndScope($userId);

        if (Auth::role() === 'dept_admin' && $user['role'] === 'dept_admin') {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Department admins cannot modify another admin account.'];
            $this->redirect('/staff');
            return;
        }

        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        $warning   = '';

        if ($newStatus === 'inactive') {
            $pendingCount = StaffUser::pendingItemsCount((int)$user['department_id']);
            if ($pendingCount > 0) {
                $warning = " Warning: this staff member has {$pendingCount} pending change request(s).";
            }
        }

        StaffUser::update($userId, ['status' => $newStatus]);
        MasterAuditLogger::log($newStatus === 'active' ? 'reactivate' : 'deactivate', 'staff_user', $userId);

        $message = $newStatus === 'active' ? 'Staff member reactivated.' : 'Staff member deactivated.';
        $_SESSION['flash'] = ['type' => 'success', 'message' => $message . $warning];
        $this->redirect('/staff');
    }

    public function resetPasswordForm(int $id): void
    {
        RoleMiddleware::handle(['dept_admin', 'institution_admin']);
        $userId = (int)$id;
        $user   = $this->loadAndScope($userId);

        $this->render('staff/reset_password', [
            'title' => 'Reset Password',
            'user'  => $user,
            'errors' => [],
        ]);
    }

    public function resetPassword(int $id): void
    {
        RoleMiddleware::handle(['dept_admin', 'institution_admin']);
        $this->requireCsrf();
        $userId = (int)$id;

        if ($userId === (int)Auth::id()) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Use "Change Password" to update your own password.'];
            $this->redirect('/staff');
            return;
        }

        $user = $this->loadAndScope($userId);

        $newPassword     = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $errors          = [];

        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            $errors['new_password'] = 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            $this->render('staff/reset_password', [
                'title'  => 'Reset Password',
                'user'   => $user,
                'errors' => $errors,
            ]);
            return;
        }

        StaffUser::update($userId, [
            'password_hash'        => password_hash($newPassword, PASSWORD_BCRYPT),
            'must_change_password' => 1,
        ]);
        MasterAuditLogger::log('reset_password', 'staff_user', $userId);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Password reset. The staff member must set a new password on next login.'];
        $this->redirect('/staff');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Load user by $id and enforce department scope for dept_admin.
     * Renders 403 + exits on failure.
     */
    private function loadAndScope(int $userId): array
    {
        $user = StaffUser::findById($userId);
        if (!$user) {
            $this->render('errors/404', ['title' => 'Not found'], 404);
            exit;
        }
        if (Auth::role() === 'dept_admin' && (int)$user['department_id'] !== (int)Auth::departmentId()) {
            $this->render('errors/403', ['title' => 'Forbidden'], 403);
            exit;
        }
        return $user;
    }
}
