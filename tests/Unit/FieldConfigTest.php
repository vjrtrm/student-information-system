<?php
namespace Tests\Unit;

use App\Helpers\Db;
use App\Helpers\FieldConfig;
use App\Helpers\FieldRegistry;
use Tests\TestCase;

class FieldConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FieldConfig::clearCache();
    }

    public function testResolveReturnsRegistryDefaultsWithNoOverrides(): void
    {
        $deptId = $this->seedDepartment('BCA');
        $config = FieldConfig::resolve($deptId);

        // mother_tongue should be 'required' by default
        $this->assertArrayHasKey('mother_tongue', $config);
        $this->assertSame('required', $config['mother_tongue']);

        // bank_name should be 'optional' by default
        $this->assertArrayHasKey('bank_name', $config);
        $this->assertSame('optional', $config['bank_name']);
    }

    public function testResolveAppliesInstitutionOverride(): void
    {
        $deptId = $this->seedDepartment('BCA');
        $now    = date('Y-m-d H:i:s');
        Db::execute(
            'INSERT INTO field_configs (field_key, department_id, mode, created_at, updated_at) VALUES (?, 0, ?, ?, ?)',
            ['bank_name', 'required', $now, $now]
        );
        FieldConfig::clearCache();
        $config = FieldConfig::resolve($deptId);

        $this->assertSame('required', $config['bank_name']);
    }

    public function testResolveAppliesDeptOverrideOnTopOfInstitution(): void
    {
        $deptId = $this->seedDepartment('MCA');
        $now    = date('Y-m-d H:i:s');
        // Institution says bank_name = required
        Db::execute(
            'INSERT INTO field_configs (field_key, department_id, mode, created_at, updated_at) VALUES (?, 0, ?, ?, ?)',
            ['bank_name', 'required', $now, $now]
        );
        // Dept overrides to hidden
        Db::execute(
            'INSERT INTO field_configs (field_key, department_id, mode, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['bank_name', $deptId, 'hidden', $now, $now]
        );
        FieldConfig::clearCache();
        $config = FieldConfig::resolve($deptId);

        $this->assertSame('hidden', $config['bank_name']);
    }

    public function testResolveIncludesActiveCustomFields(): void
    {
        $deptId = $this->seedDepartment('EEE');
        $userId = $this->seedUser('admin@test.com', 'pw', 'institution_admin');
        $now    = date('Y-m-d H:i:s');
        Db::execute(
            "INSERT INTO custom_fields (field_key, label, field_type, section, scope, mode, status, sort_order, created_by, created_at, updated_at)
             VALUES ('custom_1', 'Test Field', 'text', 'Personal Details', 'institution', 'optional', 'active', 0, ?, ?, ?)",
            [$userId, $now, $now]
        );
        FieldConfig::clearCache();
        $config = FieldConfig::resolve($deptId);

        $this->assertArrayHasKey('custom_1', $config);
        $this->assertSame('optional', $config['custom_1']);
    }

    public function testResolveExcludesInactiveCustomFields(): void
    {
        $deptId = $this->seedDepartment('CSE');
        $userId = $this->seedUser('admin2@test.com', 'pw', 'institution_admin');
        $now    = date('Y-m-d H:i:s');
        Db::execute(
            "INSERT INTO custom_fields (field_key, label, field_type, section, scope, mode, status, sort_order, created_by, created_at, updated_at)
             VALUES ('custom_2', 'Inactive Field', 'text', 'Personal Details', 'institution', 'optional', 'inactive', 0, ?, ?, ?)",
            [$userId, $now, $now]
        );
        FieldConfig::clearCache();
        $config = FieldConfig::resolve($deptId);

        $this->assertArrayNotHasKey('custom_2', $config);
    }

    public function testCacheIsUsedOnSecondCall(): void
    {
        $deptId = $this->seedDepartment('PHY');
        $config1 = FieldConfig::resolve($deptId);
        $config2 = FieldConfig::resolve($deptId);
        $this->assertSame($config1, $config2);
    }

    public function testClearCacheWorks(): void
    {
        $deptId = $this->seedDepartment('CHE');
        FieldConfig::resolve($deptId);
        $now = date('Y-m-d H:i:s');
        Db::execute(
            'INSERT INTO field_configs (field_key, department_id, mode, created_at, updated_at) VALUES (?, 0, ?, ?, ?)',
            ['bank_name', 'required', $now, $now]
        );
        FieldConfig::clearCache();
        $config = FieldConfig::resolve($deptId);
        $this->assertSame('required', $config['bank_name']);
    }
}
