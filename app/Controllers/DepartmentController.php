<?php
namespace App\Controllers;

use App\Helpers\MasterAuditLogger;
use App\Helpers\View;
use App\Middleware\RoleMiddleware;
use App\Models\Department;

class DepartmentController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        RoleMiddleware::handle(['institution_admin']);

        $q       = (string)$this->input('q', '');
        $page    = max(1, (int)$this->input('page', 1));
        $perPage = self::PER_PAGE;

        $departments = Department::search($q, $page, $perPage);
        $total       = Department::count($q);

        $this->render('master-data/departments/index', [
            'departments' => $departments,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'q'           => $q,
            'title'       => 'Departments',
        ]);
    }

    public function create(): void
    {
        RoleMiddleware::handle(['institution_admin']);

        $this->render('master-data/departments/form', [
            'dept'  => null,
            'title' => 'Add Department',
        ]);
    }

    public function store(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        $name  = (string)$this->input('name', '');
        $code  = strtoupper((string)$this->input('code', ''));
        $level = (string)$this->input('level', '');

        if ($error = $this->validateDeptFields($name, $code, $level)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $error];
            $this->redirect('/master-data/departments/create');
            return;
        }

        if (Department::codeExists($code)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Department code already exists.'];
            $this->redirect('/master-data/departments/create');
            return;
        }

        $id = Department::create($name, $code, $level);
        MasterAuditLogger::log('create', 'department', $id, [
            'name'  => $name,
            'code'  => $code,
            'level' => $level,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Department created successfully.'];
        $this->redirect('/master-data/departments');
    }

    public function edit(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);

        $dept = Department::find($id);
        if (!$dept) {
            $this->render('errors/404', ['title' => 'Not Found'], 404);
            exit;
        }

        $this->render('master-data/departments/form', [
            'dept'  => $dept,
            'title' => 'Edit Department',
        ]);
    }

    public function update(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        $dept = Department::find($id);
        if (!$dept) {
            $this->render('errors/404', ['title' => 'Not Found'], 404);
            exit;
        }

        $name  = (string)$this->input('name', '');
        $code  = strtoupper((string)$this->input('code', ''));
        $level = (string)$this->input('level', '');

        if ($error = $this->validateDeptFields($name, $code, $level)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $error];
            $this->redirect('/master-data/departments/' . $id . '/edit');
            return;
        }

        $codeChanged = ($code !== strtoupper((string)$dept['code']));

        if ($codeChanged && Department::codeExists($code, $id)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Department code already exists.'];
            $this->redirect('/master-data/departments/' . $id . '/edit');
            return;
        }

        if ($codeChanged && Department::inUse($id) && ($_POST['confirm_code_change'] ?? '') !== '1') {
            $this->render('master-data/departments/form', [
                'dept'              => $dept,
                'title'             => 'Edit Department',
                'codeChangeWarning' => 'This department is in use. Changing the code will affect existing enrolment numbers. Confirm to proceed.',
                'pendingName'       => $name,
                'pendingCode'       => $code,
                'pendingLevel'      => $level,
            ]);
            return;
        }

        Department::update($id, $name, $code, $level);
        MasterAuditLogger::log('update', 'department', $id, [
            'name'  => $name,
            'code'  => $code,
            'level' => $level,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Department updated successfully.'];
        $this->redirect('/master-data/departments');
    }

    public function deactivate(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        $dept = Department::find($id);
        if (!$dept) {
            $this->render('errors/404', ['title' => 'Not Found'], 404);
            exit;
        }

        Department::deactivate($id);
        MasterAuditLogger::log('deactivate', 'department', $id);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Department deactivated.'];
        $this->redirect('/master-data/departments');
    }

    public function reactivate(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        Department::reactivate($id);
        MasterAuditLogger::log('reactivate', 'department', $id);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Department reactivated.'];
        $this->redirect('/master-data/departments');
    }

    // ---- helpers ----

    /** Returns an error string or null if valid. */
    private function validateDeptFields(string $name, string $code, string $level): ?string
    {
        if ($name === '' || strlen($name) > 100) {
            return 'Department name is required and must not exceed 100 characters.';
        }
        if ($code === '' || strlen($code) > 20 || !ctype_alnum($code)) {
            return 'Department code is required, must be 1–20 alphanumeric characters.';
        }
        if (!in_array($level, ['UG', 'PG'], true)) {
            return 'Level must be UG or PG.';
        }
        return null;
    }
}
