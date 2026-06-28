<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Helpers\FieldConfig;
use Tests\TestCase;

class FieldConfigSaveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FieldConfig::clearCache();
    }

    public function testSaveInstitutionDefault(): void
    {
        $now = date('Y-m-d H:i:s');
        Db::execute(
            'REPLACE INTO field_configs (field_key, department_id, mode, created_at, updated_at) VALUES (?, 0, ?, ?, ?)',
            ['bank_name', 'required', $now, $now]
        );
        $row = Db::selectOne('SELECT mode FROM field_configs WHERE field_key = ? AND department_id = 0', ['bank_name']);
        $this->assertNotNull($row);
        $this->assertSame('required', $row['mode']);
    }

    public function testSaveDeptOverride(): void
    {
        $deptId = $this->seedDepartment('BCA');
        $now    = date('Y-m-d H:i:s');
        Db::execute(
            'REPLACE INTO field_configs (field_key, department_id, mode, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['bank_name', $deptId, 'hidden', $now, $now]
        );
        $row = Db::selectOne('SELECT mode FROM field_configs WHERE field_key = ? AND department_id = ?', ['bank_name', $deptId]);
        $this->assertSame('hidden', $row['mode']);
    }

    public function testUseDefaultDeletesDeptOverride(): void
    {
        $deptId = $this->seedDepartment('MCA');
        $now    = date('Y-m-d H:i:s');
        // First create override
        Db::execute(
            'REPLACE INTO field_configs (field_key, department_id, mode, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['bank_name', $deptId, 'hidden', $now, $now]
        );
        // Now remove it (use_default)
        Db::execute('DELETE FROM field_configs WHERE field_key = ? AND department_id = ?', ['bank_name', $deptId]);
        $row = Db::selectOne('SELECT id FROM field_configs WHERE field_key = ? AND department_id = ?', ['bank_name', $deptId]);
        $this->assertNull($row);
    }

    public function testResetDeptDeletesAllDeptOverrides(): void
    {
        $deptId = $this->seedDepartment('EEE');
        $now    = date('Y-m-d H:i:s');
        Db::execute(
            'REPLACE INTO field_configs (field_key, department_id, mode, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['bank_name', $deptId, 'hidden', $now, $now]
        );
        Db::execute(
            'REPLACE INTO field_configs (field_key, department_id, mode, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['bank_ifsc', $deptId, 'required', $now, $now]
        );
        // Reset
        Db::execute('DELETE FROM field_configs WHERE department_id = ?', [$deptId]);
        $rows = Db::selectAll('SELECT id FROM field_configs WHERE department_id = ?', [$deptId]);
        $this->assertCount(0, $rows);
    }

    public function testAuditLogEntryCreated(): void
    {
        $now = date('Y-m-d H:i:s');
        Db::execute(
            "INSERT INTO audit_log (actor_id, actor_role, action, entity, entity_id, details, ip, created_at)
             VALUES (1, 'institution_admin', 'bulk_save', 'field_config', 0, NULL, '127.0.0.1', ?)",
            [$now]
        );
        $row = Db::selectOne("SELECT id FROM audit_log WHERE action = 'bulk_save' AND entity = 'field_config'");
        $this->assertNotNull($row);
    }
}
