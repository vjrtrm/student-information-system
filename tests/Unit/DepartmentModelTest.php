<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Department;

/**
 * Unit tests for the Department model (Module 2 — Master Data).
 *
 * The base TestCase already creates departments and users tables via
 * sis_test_schema(), so no extra DDL is needed in setUp().
 */
class DepartmentModelTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Code normalisation
    // -------------------------------------------------------------------------

    public function testCodeIsStoredUppercase(): void
    {
        $id   = Department::create('Bachelor of Computer Applications', 'bca', 'UG');
        $dept = Department::find($id);
        $this->assertSame('BCA', $dept['code']);
    }

    public function testCodeWithMixedCaseIsNormalisedOnCreate(): void
    {
        $id   = Department::create('Master of Computer Applications', 'Mca', 'PG');
        $dept = Department::find($id);
        $this->assertSame('MCA', $dept['code']);
    }

    public function testNameIsTrimmedOnCreate(): void
    {
        $id   = Department::create('  Computer Science  ', 'CS', 'UG');
        $dept = Department::find($id);
        $this->assertSame('Computer Science', $dept['name']);
    }

    // -------------------------------------------------------------------------
    // codeExists — duplicate detection
    // -------------------------------------------------------------------------

    public function testCodeExistsReturnsTrueForExactMatch(): void
    {
        Department::create('Test Dept', 'TST', 'UG');
        $this->assertTrue(Department::codeExists('TST'));
    }

    public function testCodeExistsIsCaseInsensitive(): void
    {
        Department::create('Test Dept', 'TST', 'UG');
        $this->assertTrue(Department::codeExists('tst'));
        $this->assertTrue(Department::codeExists('Tst'));
    }

    public function testCodeExistsReturnsFalseWhenNoneSeeded(): void
    {
        $this->assertFalse(Department::codeExists('NONE'));
    }

    public function testCodeExistsExcludesCurrentDeptOnUpdate(): void
    {
        $id = Department::create('Test Dept', 'TST', 'UG');
        // A dept's own code should not trigger uniqueness failure on self-update.
        $this->assertFalse(Department::codeExists('TST', $id));
    }

    public function testCodeExistsStillDetectsOtherDeptWithSameCode(): void
    {
        $id1 = Department::create('Dept One', 'TST', 'UG');
        $id2 = Department::create('Dept Two', 'XYZ', 'PG');
        // Excluding $id2 should still find $id1's TST.
        $this->assertTrue(Department::codeExists('TST', $id2));
    }

    // -------------------------------------------------------------------------
    // Deactivate / Reactivate
    // -------------------------------------------------------------------------

    public function testNewDeptIsActiveByDefault(): void
    {
        $id   = Department::create('Test Dept', 'TST', 'UG');
        $dept = Department::find($id);
        $this->assertSame('active', $dept['status']);
    }

    public function testDeactivateSetsStatusInactive(): void
    {
        $id = Department::create('Test Dept', 'TST', 'UG');
        Department::deactivate($id);
        $this->assertSame('inactive', Department::find($id)['status']);
    }

    public function testReactivateSetsStatusActive(): void
    {
        $id = Department::create('Test Dept', 'TST', 'UG');
        Department::deactivate($id);
        Department::reactivate($id);
        $this->assertSame('active', Department::find($id)['status']);
    }

    // -------------------------------------------------------------------------
    // inUse — reference safety
    // -------------------------------------------------------------------------

    public function testInUseReturnsFalseWhenNoReferences(): void
    {
        $id = Department::create('Test Dept', 'TST', 'UG');
        $this->assertFalse(Department::inUse($id));
    }

    public function testInUseReturnsTrueWhenUserReferencesDept(): void
    {
        $id = Department::create('Test Dept', 'TST', 'UG');
        $this->pdo->prepare(
            "INSERT INTO users (name, email, password_hash, role, department_id) VALUES (?,?,?,?,?)"
        )->execute(['John', 'john@test.com', 'hash', 'staff', $id]);
        $this->assertTrue(Department::inUse($id));
    }

    public function testInUseReturnsTrueWhenStudentReferencesDept(): void
    {
        $id = Department::create('Test Dept', 'TST', 'UG');
        $this->pdo->prepare(
            "INSERT INTO students (mobile, dob, department_id) VALUES (?,?,?)"
        )->execute(['9876543210', '2000-01-01', $id]);
        $this->assertTrue(Department::inUse($id));
    }

    // -------------------------------------------------------------------------
    // allActive / all
    // -------------------------------------------------------------------------

    public function testAllActiveExcludesInactiveDepts(): void
    {
        $id1 = Department::create('Active Dept',   'ACT', 'UG');
        $id2 = Department::create('Inactive Dept', 'INA', 'PG');
        Department::deactivate($id2);

        $active = Department::allActive();
        $this->assertCount(1, $active);
        $this->assertSame('ACT', $active[0]['code']);
    }

    public function testAllReturnsEveryDept(): void
    {
        Department::create('Dept A', 'AAA', 'UG');
        $id2 = Department::create('Dept B', 'BBB', 'PG');
        Department::deactivate($id2);

        $this->assertCount(2, Department::all());
    }

    // -------------------------------------------------------------------------
    // search
    // -------------------------------------------------------------------------

    public function testSearchMatchesByName(): void
    {
        Department::create('Bachelor of Arts', 'BA',  'UG');
        Department::create('Master of Science', 'MSC', 'PG');
        $results = Department::search('bachelor');
        $this->assertCount(1, $results);
        $this->assertSame('BA', $results[0]['code']);
    }

    public function testSearchMatchesByCode(): void
    {
        Department::create('Bachelor of Arts', 'BA',  'UG');
        Department::create('Master of Science', 'MSC', 'PG');
        $results = Department::search('MSC');
        $this->assertCount(1, $results);
        $this->assertSame('MSC', $results[0]['code']);
    }

    public function testSearchWithEmptyQueryReturnsAll(): void
    {
        Department::create('Dept A', 'AAA', 'UG');
        Department::create('Dept B', 'BBB', 'PG');
        $this->assertCount(2, Department::search(''));
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function testUpdateChangesFields(): void
    {
        $id = Department::create('Old Name', 'OLD', 'UG');
        Department::update($id, 'New Name', 'new', 'PG');
        $dept = Department::find($id);
        $this->assertSame('New Name', $dept['name']);
        $this->assertSame('NEW', $dept['code']);
        $this->assertSame('PG', $dept['level']);
    }
}
