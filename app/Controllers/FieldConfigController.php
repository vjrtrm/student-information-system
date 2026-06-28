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

class FieldConfigController extends Controller
{
    // GET /field-config
    public function index(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $configurableFields = FieldRegistry::configurableFields();
        // Load current institution-wide config (dept_id=0)
        $rows = Db::selectAll('SELECT field_key, mode FROM field_configs WHERE department_id = 0');
        $saved = [];
        foreach ($rows as $r) { $saved[$r['field_key']] = $r['mode']; }
        // Load departments for selector
        $departments = Db::selectAll("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
        // Group fields by section
        $sections = FieldRegistry::SECTIONS;
        $bySection = [];
        foreach ($configurableFields as $key => $field) {
            $bySection[$field['section']][$key] = $field + ['current_mode' => $saved[$key] ?? $field['default_mode']];
        }
        $title = 'Field Configuration — Institution Defaults';
        $this->render('field-config/index', compact('bySection', 'departments', 'sections', 'title'));
    }

    // POST /field-config
    public function saveBulk(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();
        $modes = $_POST['mode'] ?? [];
        $valid = ['required', 'optional', 'hidden'];
        $count = 0;
        foreach (FieldRegistry::configurableFields() as $key => $field) {
            $mode = $modes[$key] ?? null;
            if (!in_array($mode, $valid, true)) continue;
            $now = date('Y-m-d H:i:s');
            Db::execute(
                'REPLACE INTO field_configs (field_key, department_id, mode, created_at, updated_at) VALUES (?, 0, ?, ?, ?)',
                [$key, $mode, $now, $now]
            );
            $count++;
        }
        MasterAuditLogger::log(
            'bulk_save', 'field_config', 0,
            ['scope' => 'institution', 'count' => $count]
        );
        FieldConfig::clearCache();
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Institution-wide field defaults saved.'];
        View::redirect('/field-config');
    }

    // GET /field-config/my-dept  (dept_admin shortcut)
    public function myDept(): void
    {
        RoleMiddleware::handle(['dept_admin']);
        View::redirect('/field-config/' . Auth::departmentId());
    }

    // GET /field-config/{deptId}
    public function deptView(int $deptId): void
    {
        RoleMiddleware::handle(['institution_admin', 'dept_admin']);
        if (Auth::role() === 'dept_admin' && $deptId !== (int) Auth::departmentId()) {
            http_response_code(403); echo 'Forbidden'; exit;
        }
        $dept = Db::selectOne('SELECT * FROM departments WHERE id = ?', [$deptId]);
        if (!$dept) { http_response_code(404); echo 'Not found'; exit; }
        $configurableFields = FieldRegistry::configurableFields();
        // Institution defaults
        $instRows = Db::selectAll('SELECT field_key, mode FROM field_configs WHERE department_id = 0');
        $instDefaults = [];
        foreach ($instRows as $r) { $instDefaults[$r['field_key']] = $r['mode']; }
        // Dept overrides
        $deptRows = Db::selectAll('SELECT field_key, mode FROM field_configs WHERE department_id = ?', [$deptId]);
        $deptOverrides = [];
        foreach ($deptRows as $r) { $deptOverrides[$r['field_key']] = $r['mode']; }
        $departments = Db::selectAll("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
        $sections = FieldRegistry::SECTIONS;
        $bySection = [];
        foreach ($configurableFields as $key => $field) {
            $instMode = $instDefaults[$key] ?? $field['default_mode'];
            $deptMode = $deptOverrides[$key] ?? 'use_default';
            $bySection[$field['section']][$key] = $field + [
                'inst_mode'    => $instMode,
                'dept_mode'    => $deptMode,
                'has_override' => isset($deptOverrides[$key]),
            ];
        }
        $customFields = CustomField::findActive($deptId);
        $editable = Auth::role() === 'institution_admin';
        $title = 'Field Configuration — ' . htmlspecialchars($dept['name']);
        $this->render('field-config/dept', compact('bySection', 'sections', 'departments', 'dept', 'deptId', 'editable', 'customFields', 'title'));
    }

    // POST /field-config/{deptId}
    public function saveDeptBulk(int $deptId): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();
        $dept = Db::selectOne('SELECT id FROM departments WHERE id = ?', [$deptId]);
        if (!$dept) { http_response_code(404); echo 'Not found'; exit; }
        $modes = $_POST['mode'] ?? [];
        $valid = ['required', 'optional', 'hidden'];
        $count = 0;
        foreach (FieldRegistry::configurableFields() as $key => $field) {
            $mode = $modes[$key] ?? 'use_default';
            if ($mode === 'use_default') {
                Db::execute('DELETE FROM field_configs WHERE field_key = ? AND department_id = ?', [$key, $deptId]);
            } elseif (in_array($mode, $valid, true)) {
                $now = date('Y-m-d H:i:s');
                Db::execute(
                    'REPLACE INTO field_configs (field_key, department_id, mode, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                    [$key, $deptId, $mode, $now, $now]
                );
                $count++;
            }
        }
        MasterAuditLogger::log(
            'bulk_save', 'field_config', $deptId,
            ['scope' => 'department', 'dept_id' => $deptId, 'overrides' => $count]
        );
        FieldConfig::clearCache();
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Department field configuration saved.'];
        View::redirect('/field-config/' . $deptId);
    }

    // POST /field-config/{deptId}/reset
    public function resetDept(int $deptId): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();
        $dept = Db::selectOne('SELECT id FROM departments WHERE id = ?', [$deptId]);
        if (!$dept) { http_response_code(404); echo 'Not found'; exit; }
        Db::execute('DELETE FROM field_configs WHERE department_id = ?', [$deptId]);
        MasterAuditLogger::log(
            'reset', 'field_config', $deptId,
            ['dept_id' => $deptId]
        );
        FieldConfig::clearCache();
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Department overrides cleared. Fields now follow institution defaults.'];
        View::redirect('/field-config/' . $deptId);
    }
}
