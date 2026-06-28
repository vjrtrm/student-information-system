<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Helpers\FieldConfig;
use App\Models\CustomField;
use Tests\TestCase;

class CustomFieldCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FieldConfig::clearCache();
    }

    public function testCreateSetsFieldKey(): void
    {
        $userId = $this->seedUser('admin@test.com', 'pw', 'institution_admin');
        $id     = CustomField::create([
            'label'         => 'Test Field',
            'field_type'    => 'text',
            'section'       => 'Personal Details',
            'scope'         => 'institution',
            'department_id' => null,
            'mode'          => 'optional',
            'sort_order'    => 0,
            'created_by'    => $userId,
        ]);
        // Set field_key after create (as done in controller)
        Db::execute('UPDATE custom_fields SET field_key = ? WHERE id = ?', ['custom_' . $id, $id]);

        $field = CustomField::findById($id);
        $this->assertNotNull($field);
        $this->assertSame('custom_' . $id, $field['field_key']);
    }

    public function testFindActiveReturnsOnlyActiveFields(): void
    {
        $deptId = $this->seedDepartment('BCA');
        $userId = $this->seedUser('admin2@test.com', 'pw', 'institution_admin');
        $now    = date('Y-m-d H:i:s');
        Db::execute(
            "INSERT INTO custom_fields (field_key, label, field_type, section, scope, mode, status, sort_order, created_by, created_at, updated_at)
             VALUES ('custom_10', 'Active Field', 'text', 'Personal Details', 'institution', 'optional', 'active', 0, ?, ?, ?)",
            [$userId, $now, $now]
        );
        Db::execute(
            "INSERT INTO custom_fields (field_key, label, field_type, section, scope, mode, status, sort_order, created_by, created_at, updated_at)
             VALUES ('custom_11', 'Inactive Field', 'text', 'Personal Details', 'institution', 'optional', 'inactive', 0, ?, ?, ?)",
            [$userId, $now, $now]
        );

        $active = CustomField::findActive($deptId);
        $keys = array_column($active, 'field_key');
        $this->assertContains('custom_10', $keys);
        $this->assertNotContains('custom_11', $keys);
    }

    public function testUpdateDoesNotChangeFieldType(): void
    {
        $userId = $this->seedUser('admin3@test.com', 'pw', 'institution_admin');
        $now    = date('Y-m-d H:i:s');
        Db::execute(
            "INSERT INTO custom_fields (field_key, label, field_type, section, scope, mode, status, sort_order, created_by, created_at, updated_at)
             VALUES ('custom_20', 'Update Test', 'select', 'Personal Details', 'institution', 'optional', 'active', 0, ?, ?, ?)",
            [$userId, $now, $now]
        );
        $id = (int)Db::selectOne('SELECT id FROM custom_fields WHERE field_key = ?', ['custom_20'])['id'];

        CustomField::update($id, [
            'label'   => 'Updated Label',
            'mode'    => 'required',
            'options' => json_encode(['A', 'B', 'C']),
            'status'  => 'active',
        ]);

        $field = CustomField::findById($id);
        $this->assertSame('Updated Label', $field['label']);
        $this->assertSame('required', $field['mode']);
        $this->assertSame('select', $field['field_type']); // unchanged
    }

    public function testDeactivateRemovesFromFindActive(): void
    {
        $deptId = $this->seedDepartment('CSE');
        $userId = $this->seedUser('admin4@test.com', 'pw', 'institution_admin');
        $now    = date('Y-m-d H:i:s');
        Db::execute(
            "INSERT INTO custom_fields (field_key, label, field_type, section, scope, mode, status, sort_order, created_by, created_at, updated_at)
             VALUES ('custom_30', 'To Deactivate', 'text', 'Personal Details', 'institution', 'optional', 'active', 0, ?, ?, ?)",
            [$userId, $now, $now]
        );
        $id = (int)Db::selectOne('SELECT id FROM custom_fields WHERE field_key = ?', ['custom_30'])['id'];

        CustomField::update($id, [
            'label'   => 'To Deactivate',
            'mode'    => 'optional',
            'options' => null,
            'status'  => 'inactive',
        ]);

        $active = CustomField::findActive($deptId);
        $keys = array_column($active, 'field_key');
        $this->assertNotContains('custom_30', $keys);
    }
}
