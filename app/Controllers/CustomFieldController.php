<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\FieldConfig;
use App\Helpers\FieldRegistry;
use App\Helpers\MasterAuditLogger;
use App\Helpers\View;
use App\Middleware\RoleMiddleware;
use App\Models\CustomField;

class CustomFieldController extends Controller
{
    // GET /field-config/custom
    public function index(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $fields = CustomField::findAll();
        $title = 'Custom Fields';
        $this->render('field-config/custom/index', compact('fields', 'title'));
    }

    // GET /field-config/custom/create
    public function createForm(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $departments = Db::selectAll("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
        $sections = FieldRegistry::SECTIONS;
        $errors = $_SESSION['form_errors'] ?? [];
        $old = $_SESSION['form_old'] ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_old']);
        $mode = 'create';
        $field = null;
        $title = 'Add Custom Field';
        $this->render('field-config/custom/form', compact('departments', 'sections', 'errors', 'old', 'mode', 'field', 'title'));
    }

    // POST /field-config/custom/create
    public function store(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();
        $errors = $this->validateCustomField($_POST, null);
        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_old'] = $_POST;
            View::redirect('/field-config/custom/create');
            return;
        }
        $scope = $_POST['scope'];
        $deptId = $scope === 'department' ? (int)$_POST['department_id'] : null;
        $options = null;
        if ($_POST['field_type'] === 'select') {
            $opts = array_values(array_filter(array_map('trim', explode("\n", $_POST['options'] ?? ''))));
            $options = json_encode($opts);
        }
        $id = CustomField::create([
            'label'         => trim($_POST['label']),
            'field_type'    => $_POST['field_type'],
            'section'       => $_POST['section'],
            'scope'         => $scope,
            'department_id' => $deptId,
            'mode'          => $_POST['mode'],
            'options'       => $options,
            'sort_order'    => 0,
            'created_by'    => Auth::id(),
        ]);
        // Set field_key = custom_{id}
        Db::execute('UPDATE custom_fields SET field_key = ? WHERE id = ?', ['custom_' . $id, $id]);
        MasterAuditLogger::log(
            'create', 'custom_field', $id,
            ['label' => trim($_POST['label']), 'type' => $_POST['field_type'], 'section' => $_POST['section']]
        );
        FieldConfig::clearCache();
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Custom field '" . htmlspecialchars(trim($_POST['label'])) . "' created."];
        View::redirect('/field-config/custom');
    }

    // GET /field-config/custom/{id}/edit
    public function editForm(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $field = CustomField::findById($id);
        if (!$field) { http_response_code(404); echo 'Not found'; exit; }
        $departments = Db::selectAll("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
        $sections = FieldRegistry::SECTIONS;
        $errors = $_SESSION['form_errors'] ?? [];
        $old = $_SESSION['form_old'] ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_old']);
        $mode = 'edit';
        $title = 'Edit Custom Field';
        $this->render('field-config/custom/form', compact('departments', 'sections', 'errors', 'old', 'mode', 'field', 'title'));
    }

    // POST /field-config/custom/{id}/edit
    public function update(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();
        $field = CustomField::findById($id);
        if (!$field) { http_response_code(404); echo 'Not found'; exit; }
        $errors = $this->validateCustomField($_POST, $field);
        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_old'] = $_POST;
            View::redirect('/field-config/custom/' . $id . '/edit');
            return;
        }
        $options = null;
        if ($field['field_type'] === 'select') {
            $opts = array_values(array_filter(array_map('trim', explode("\n", $_POST['options'] ?? ''))));
            $options = json_encode($opts);
        }
        CustomField::update($id, [
            'label'   => trim($_POST['label']),
            'mode'    => $_POST['mode'],
            'options' => $options,
            'status'  => $field['status'], // status unchanged by edit form
        ]);
        MasterAuditLogger::log(
            'update', 'custom_field', $id,
            ['label' => trim($_POST['label']), 'mode' => $_POST['mode']]
        );
        FieldConfig::clearCache();
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Custom field updated.'];
        View::redirect('/field-config/custom');
    }

    // POST /field-config/custom/{id}/toggle
    public function toggleStatus(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();
        $field = CustomField::findById($id);
        if (!$field) { http_response_code(404); echo 'Not found'; exit; }
        $newStatus = $field['status'] === 'active' ? 'inactive' : 'active';
        CustomField::update($id, [
            'label'   => $field['label'],
            'mode'    => $field['mode'],
            'options' => $field['options'],
            'status'  => $newStatus,
        ]);
        MasterAuditLogger::log(
            $newStatus === 'inactive' ? 'deactivate' : 'reactivate',
            'custom_field', $id,
            ['label' => $field['label']]
        );
        FieldConfig::clearCache();
        $msg = $newStatus === 'inactive'
            ? 'Custom field deactivated. Existing student data is preserved.'
            : 'Custom field reactivated.';
        $_SESSION['flash'] = ['type' => 'success', 'message' => $msg];
        View::redirect('/field-config/custom');
    }

    private function validateCustomField(array $post, ?array $existingField): array
    {
        $errors = [];
        $label = trim($post['label'] ?? '');
        if (strlen($label) < 2 || strlen($label) > 150) {
            $errors['label'] = 'Label must be 2–150 characters.';
        }
        $validTypes = ['text', 'textarea', 'number', 'date', 'select'];
        // Only validate field_type on create (existingField === null)
        if ($existingField === null && !in_array($post['field_type'] ?? '', $validTypes, true)) {
            $errors['field_type'] = 'Invalid field type.';
        }
        if (!in_array($post['section'] ?? '', FieldRegistry::SECTIONS, true)) {
            $errors['section'] = 'Invalid section.';
        }
        if (!in_array($post['mode'] ?? '', ['required', 'optional', 'hidden'], true)) {
            $errors['mode'] = 'Invalid mode.';
        }
        $fieldType = $existingField ? $existingField['field_type'] : ($post['field_type'] ?? '');
        if ($fieldType === 'select') {
            $opts = array_values(array_filter(array_map('trim', explode("\n", $post['options'] ?? ''))));
            if (count($opts) < 2) {
                $errors['options'] = 'Select fields require at least 2 options.';
            }
        }
        if ($existingField === null) {
            $scope = $post['scope'] ?? '';
            if (!in_array($scope, ['institution', 'department'], true)) {
                $errors['scope'] = 'Invalid scope.';
            }
            if ($scope === 'department') {
                $deptId = (int)($post['department_id'] ?? 0);
                if ($deptId <= 0) {
                    $errors['department_id'] = 'Select a department.';
                } else {
                    $dept = Db::selectOne('SELECT id FROM departments WHERE id = ?', [$deptId]);
                    if (!$dept) $errors['department_id'] = 'Department not found.';
                }
            }
        }
        return $errors;
    }
}
