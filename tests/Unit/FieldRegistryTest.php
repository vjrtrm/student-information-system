<?php
namespace Tests\Unit;

use App\Helpers\FieldRegistry;
use Tests\TestCase;

class FieldRegistryTest extends TestCase
{
    public function testSectionsHasSixEntries(): void
    {
        $this->assertCount(6, FieldRegistry::SECTIONS);
    }

    public function testAllFieldsHaveRequiredKeys(): void
    {
        foreach (FieldRegistry::ALL_FIELDS as $key => $field) {
            $this->assertArrayHasKey('label', $field, "Field $key missing label");
            $this->assertArrayHasKey('section', $field, "Field $key missing section");
            $this->assertArrayHasKey('default_mode', $field, "Field $key missing default_mode");
            $this->assertArrayHasKey('locked', $field, "Field $key missing locked");
        }
    }

    public function testConfigurableFieldsExcludesLockedKeys(): void
    {
        $locked = FieldRegistry::LOCKED_KEYS;
        $configurable = FieldRegistry::configurableFields();
        foreach ($locked as $key) {
            $this->assertArrayNotHasKey($key, $configurable, "Locked key $key should not be in configurableFields()");
        }
    }

    public function testConfigurableFieldsContainsNonLockedFields(): void
    {
        $configurable = FieldRegistry::configurableFields();
        $this->assertArrayHasKey('mother_tongue', $configurable);
        $this->assertArrayHasKey('religion', $configurable);
        $this->assertArrayHasKey('bank_name', $configurable);
    }

    public function testIsCustomKey(): void
    {
        $this->assertTrue(FieldRegistry::isCustomKey('custom_5'));
        $this->assertTrue(FieldRegistry::isCustomKey('custom_123'));
        $this->assertFalse(FieldRegistry::isCustomKey('blood_group'));
        $this->assertFalse(FieldRegistry::isCustomKey('first_name'));
        $this->assertFalse(FieldRegistry::isCustomKey('custom'));
    }

    public function testLockedKeysAreSubsetOfAllFields(): void
    {
        $allKeys = array_keys(FieldRegistry::ALL_FIELDS);
        foreach (FieldRegistry::LOCKED_KEYS as $key) {
            $this->assertContains($key, $allKeys, "Locked key $key not in ALL_FIELDS");
        }
    }

    public function testSectionsMatchFieldSectionValues(): void
    {
        $validSections = FieldRegistry::SECTIONS;
        foreach (FieldRegistry::ALL_FIELDS as $key => $field) {
            $this->assertContains($field['section'], $validSections, "Field $key has invalid section: {$field['section']}");
        }
    }
}
