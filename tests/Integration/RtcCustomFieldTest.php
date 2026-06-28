<?php
namespace Tests\Integration;

use App\Helpers\Db;
use App\Helpers\FieldConfig;
use App\Helpers\FieldRegistry;
use App\Helpers\RtcFieldHelper;
use App\Models\StudentProfile;
use Tests\TestCase;

class RtcCustomFieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FieldConfig::clearCache();
    }

    private function seedCustomField(int $userId): int
    {
        $now = date('Y-m-d H:i:s');
        Db::execute(
            "INSERT INTO custom_fields (field_key, label, field_type, section, scope, mode, status, sort_order, created_by, created_at, updated_at)
             VALUES ('', 'Extra Info', 'text', 'Personal Details', 'institution', 'optional', 'active', 0, ?, ?, ?)",
            [$userId, $now, $now]
        );
        $id = (int)Db::lastInsertId();
        Db::execute('UPDATE custom_fields SET field_key = ? WHERE id = ?', ['custom_' . $id, $id]);
        return $id;
    }

    public function testBuildChangesetWithCustomKey(): void
    {
        $deptId    = $this->seedDepartment('BCA');
        $userId    = $this->seedUser('admin@test.com', 'pw', 'institution_admin');
        $studentId = $this->seedFullStudent(['department_id' => $deptId]);
        $cfId      = $this->seedCustomField($userId);
        FieldConfig::clearCache();

        $student     = Db::selectOne('SELECT * FROM students WHERE id = ?', [$studentId]);
        $profile     = [];
        $customData  = [$cfId => 'Old value'];
        $activeKeys  = ['custom_' . $cfId];

        $changeset = RtcFieldHelper::buildChangeset(
            ['custom_' . $cfId => 'New value'],
            $profile,
            $student,
            $customData,
            $activeKeys
        );

        $this->assertCount(1, $changeset);
        $this->assertSame('custom_' . $cfId, $changeset[0]['field_key']);
        $this->assertSame('Old value', $changeset[0]['current_value']);
        $this->assertSame('New value', $changeset[0]['proposed_value']);
    }

    public function testApplyChangesetUpdatesCustomData(): void
    {
        $deptId    = $this->seedDepartment('MCA');
        $studentId = $this->seedFullStudent(['department_id' => $deptId]);
        $userId    = $this->seedUser('admin2@test.com', 'pw', 'institution_admin');
        $cfId      = $this->seedCustomField($userId);

        // Insert initial student_profiles row
        $now = date('Y-m-d H:i:s');
        Db::execute(
            "INSERT INTO student_profiles (student_id, form_status, last_saved_at, created_at) VALUES (?, 'incomplete', ?, ?)",
            [$studentId, $now, $now]
        );
        // Insert initial custom data
        Db::execute(
            'REPLACE INTO student_custom_data (student_id, custom_field_id, value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$studentId, $cfId, 'Old value', $now, $now]
        );

        // Apply changeset with custom key
        StudentProfile::applyChangeset($studentId, ['custom_' . $cfId => 'New value']);

        $row = Db::selectOne(
            'SELECT value FROM student_custom_data WHERE student_id = ? AND custom_field_id = ?',
            [$studentId, $cfId]
        );
        $this->assertNotNull($row);
        $this->assertSame('New value', $row['value']);
    }

    public function testBuildChangesetSkipsNoOpCustomChanges(): void
    {
        $deptId    = $this->seedDepartment('EEE');
        $userId    = $this->seedUser('admin3@test.com', 'pw', 'institution_admin');
        $studentId = $this->seedFullStudent(['department_id' => $deptId]);
        $cfId      = $this->seedCustomField($userId);

        $student    = Db::selectOne('SELECT * FROM students WHERE id = ?', [$studentId]);
        $profile    = [];
        $customData = [$cfId => 'Same value'];
        $activeKeys = ['custom_' . $cfId];

        $this->expectException(\InvalidArgumentException::class);
        RtcFieldHelper::buildChangeset(
            ['custom_' . $cfId => 'Same value'], // same value — no-op
            $profile,
            $student,
            $customData,
            $activeKeys
        );
    }

    public function testIsCustomKeyHelperWorks(): void
    {
        $this->assertTrue(FieldRegistry::isCustomKey('custom_1'));
        $this->assertTrue(FieldRegistry::isCustomKey('custom_999'));
        $this->assertFalse(FieldRegistry::isCustomKey('blood_group'));
    }
}
