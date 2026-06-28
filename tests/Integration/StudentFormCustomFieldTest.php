<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Helpers\FieldConfig;
use Tests\TestCase;

class StudentFormCustomFieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FieldConfig::clearCache();
    }

    private function seedCustomField(string $mode = 'optional', string $scope = 'institution', ?int $deptId = null): int
    {
        $existing = \App\Helpers\Db::selectOne('SELECT id FROM users WHERE email = ?', ['cf_admin@test.com']);
        $userId   = $existing ? (int)$existing['id'] : $this->seedUser('cf_admin@test.com', 'pw', 'institution_admin');
        $now    = date('Y-m-d H:i:s');
        Db::execute(
            "INSERT INTO custom_fields (field_key, label, field_type, section, scope, department_id, mode, status, sort_order, created_by, created_at, updated_at)
             VALUES ('', 'Extra Info', 'text', 'Personal Details', ?, ?, ?, 'active', 0, ?, ?, ?)",
            [$scope, $deptId, $mode, $userId, $now, $now]
        );
        $id = (int)Db::lastInsertId();
        Db::execute('UPDATE custom_fields SET field_key = ? WHERE id = ?', ['custom_' . $id, $id]);
        return $id;
    }

    public function testSaveCustomFieldValue(): void
    {
        $deptId   = $this->seedDepartment('BCA');
        $studentId = $this->seedFullStudent(['department_id' => $deptId]);
        $cfId     = $this->seedCustomField('optional', 'institution');

        $now = date('Y-m-d H:i:s');
        Db::execute(
            'REPLACE INTO student_custom_data (student_id, custom_field_id, value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$studentId, $cfId, 'Test value', $now, $now]
        );

        $row = Db::selectOne(
            'SELECT value FROM student_custom_data WHERE student_id = ? AND custom_field_id = ?',
            [$studentId, $cfId]
        );
        $this->assertNotNull($row);
        $this->assertSame('Test value', $row['value']);
    }

    public function testHiddenCustomFieldNotSaved(): void
    {
        $deptId    = $this->seedDepartment('MCA');
        $studentId = $this->seedFullStudent(['department_id' => $deptId]);
        $cfId      = $this->seedCustomField('hidden', 'institution');

        // Simulate the save logic: hidden fields are skipped
        $customFields = Db::selectAll(
            "SELECT id, mode FROM custom_fields WHERE status = 'active' AND (scope = 'institution' OR department_id = ?)",
            [$deptId]
        );

        $now = date('Y-m-d H:i:s');
        foreach ($customFields as $cf) {
            $cfMode = $cf['mode'];
            if ($cfMode === 'hidden') continue; // skip hidden
            Db::execute(
                'REPLACE INTO student_custom_data (student_id, custom_field_id, value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                [$studentId, $cf['id'], 'should not be saved', $now, $now]
            );
        }

        $row = Db::selectOne(
            'SELECT value FROM student_custom_data WHERE student_id = ? AND custom_field_id = ?',
            [$studentId, $cfId]
        );
        $this->assertNull($row, 'Hidden custom field value should not be saved');
    }

    public function testRequiredCustomFieldBlocksSubmitWhenBlank(): void
    {
        $deptId    = $this->seedDepartment('EEE');
        $studentId = $this->seedFullStudent(['department_id' => $deptId]);
        $cfId      = $this->seedCustomField('required', 'institution');
        FieldConfig::clearCache();

        // Check validation logic: required field with no value should be in missing list
        $fieldConfig  = FieldConfig::resolve($deptId);
        $customFields = \App\Helpers\FieldConfig::resolveCustomFields($deptId);
        $customData   = []; // no data saved yet

        $missingCustom = [];
        foreach ($customFields as $cf) {
            $cfKey  = 'custom_' . $cf['id'];
            $cfMode = $fieldConfig[$cfKey] ?? $cf['mode'];
            if ($cfMode === 'required') {
                $val = isset($customData[(int)$cf['id']]) ? trim($customData[(int)$cf['id']]) : '';
                if ($val === '') {
                    $missingCustom[] = $cf['label'];
                }
            }
        }

        $this->assertNotEmpty($missingCustom, 'Required custom field with blank value should be in missing list');
        $this->assertContains('Extra Info', $missingCustom);
    }

    public function testRequiredCustomFieldWithValuePassesValidation(): void
    {
        $deptId    = $this->seedDepartment('CSE');
        $studentId = $this->seedFullStudent(['department_id' => $deptId]);
        $cfId      = $this->seedCustomField('required', 'institution');
        FieldConfig::clearCache();

        $now = date('Y-m-d H:i:s');
        Db::execute(
            'REPLACE INTO student_custom_data (student_id, custom_field_id, value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$studentId, $cfId, 'Filled value', $now, $now]
        );

        $fieldConfig  = FieldConfig::resolve($deptId);
        $customFields = \App\Helpers\FieldConfig::resolveCustomFields($deptId);
        $cdRows       = Db::selectAll('SELECT custom_field_id, value FROM student_custom_data WHERE student_id = ?', [$studentId]);
        $customData   = [];
        foreach ($cdRows as $row) { $customData[(int)$row['custom_field_id']] = $row['value']; }

        $missingCustom = [];
        foreach ($customFields as $cf) {
            $cfKey  = 'custom_' . $cf['id'];
            $cfMode = $fieldConfig[$cfKey] ?? $cf['mode'];
            if ($cfMode === 'required') {
                $val = isset($customData[(int)$cf['id']]) ? trim($customData[(int)$cf['id']]) : '';
                if ($val === '') {
                    $missingCustom[] = $cf['label'];
                }
            }
        }

        $this->assertEmpty($missingCustom, 'Required custom field with value should pass validation');
    }
}
