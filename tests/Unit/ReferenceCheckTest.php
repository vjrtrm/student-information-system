<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\ReferenceCheck;

/**
 * Unit tests for ReferenceCheck helper (Module 2 — Master Data).
 *
 * ReferenceCheck::MAP defines which downstream tables to query for each entity.
 * Current mappings:
 *   department  → users.department_id, students.department_id
 *   state       → districts.state_id
 *   district    → taluks.district_id
 *   taluk       → (empty — leaf node)
 *   option_value→ students.academic_year_id
 *
 * departments, users, students are in sis_test_schema().
 * states, districts, taluks are created in setUp() here.
 */
class ReferenceCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // creates base schema + injects PDO into Db

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS states (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                created_at TEXT
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS districts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                state_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                created_at TEXT
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS taluks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                district_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                created_at TEXT
            )"
        );
    }

    // =========================================================================
    // department entity
    // =========================================================================

    public function testDepartmentInUseReturnsFalseWhenNoReferences(): void
    {
        $this->pdo->exec("INSERT INTO departments (name, code, level) VALUES ('BCA', 'BCA', 'UG')");
        $deptId = (int)$this->pdo->lastInsertId();
        $this->assertFalse(ReferenceCheck::inUse('department', $deptId));
    }

    public function testDepartmentInUseReturnsTrueWhenUserReferencesDept(): void
    {
        $this->pdo->exec("INSERT INTO departments (name, code, level) VALUES ('BCA', 'BCA', 'UG')");
        $deptId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO users (name, email, password_hash, role, department_id) VALUES (?,?,?,?,?)"
        )->execute(['Staff', 'staff@test.com', 'hash', 'staff', $deptId]);
        $this->assertTrue(ReferenceCheck::inUse('department', $deptId));
    }

    public function testDepartmentInUseReturnsTrueWhenStudentReferencesDept(): void
    {
        $this->pdo->exec("INSERT INTO departments (name, code, level) VALUES ('BCA', 'BCA', 'UG')");
        $deptId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO students (mobile, dob, department_id) VALUES (?,?,?)"
        )->execute(['9876543210', '2000-01-01', $deptId]);
        $this->assertTrue(ReferenceCheck::inUse('department', $deptId));
    }

    public function testDepartmentInUseReturnsFalseForNonExistentId(): void
    {
        // No dept with id=999; both users and students tables empty.
        $this->assertFalse(ReferenceCheck::inUse('department', 999));
    }

    public function testDepartmentInUseOnlyChecksOwnId(): void
    {
        // Insert a user linked to dept 1; check inUse for dept 2.
        $this->pdo->exec("INSERT INTO departments (name, code, level) VALUES ('Dept1', 'D1', 'UG')");
        $deptId1 = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO departments (name, code, level) VALUES ('Dept2', 'D2', 'PG')");
        $deptId2 = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO users (name, email, password_hash, role, department_id) VALUES (?,?,?,?,?)"
        )->execute(['Staff', 'staff@test.com', 'hash', 'staff', $deptId1]);
        $this->assertFalse(ReferenceCheck::inUse('department', $deptId2));
    }

    // =========================================================================
    // state entity
    // =========================================================================

    public function testStateInUseReturnsFalseWhenNoDistricts(): void
    {
        $this->pdo->exec("INSERT INTO states (name) VALUES ('Isolated State')");
        $stateId = (int)$this->pdo->lastInsertId();
        $this->assertFalse(ReferenceCheck::inUse('state', $stateId));
    }

    public function testStateInUseReturnsTrueWhenDistrictReferencesState(): void
    {
        $this->pdo->exec("INSERT INTO states (name) VALUES ('Tamil Nadu')");
        $stateId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO districts (state_id, name) VALUES (?,?)"
        )->execute([$stateId, 'Chennai']);
        $this->assertTrue(ReferenceCheck::inUse('state', $stateId));
    }

    public function testStateInUseOnlyChecksOwnId(): void
    {
        $this->pdo->exec("INSERT INTO states (name) VALUES ('State A')");
        $stateIdA = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO states (name) VALUES ('State B')");
        $stateIdB = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO districts (state_id, name) VALUES (?,?)"
        )->execute([$stateIdA, 'District of A']);
        $this->assertFalse(ReferenceCheck::inUse('state', $stateIdB));
    }

    // =========================================================================
    // district entity
    // =========================================================================

    public function testDistrictInUseReturnsFalseWhenNoTaluks(): void
    {
        $this->pdo->exec("INSERT INTO states (name) VALUES ('Tamil Nadu')");
        $stateId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO districts (state_id, name) VALUES (?,?)"
        )->execute([$stateId, 'Empty District']);
        $districtId = (int)$this->pdo->lastInsertId();
        $this->assertFalse(ReferenceCheck::inUse('district', $districtId));
    }

    public function testDistrictInUseReturnsTrueWhenTalukReferencesDistrict(): void
    {
        $this->pdo->exec("INSERT INTO states (name) VALUES ('Tamil Nadu')");
        $stateId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO districts (state_id, name) VALUES (?,?)"
        )->execute([$stateId, 'Chennai']);
        $districtId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO taluks (district_id, name) VALUES (?,?)"
        )->execute([$districtId, 'Ambattur']);
        $this->assertTrue(ReferenceCheck::inUse('district', $districtId));
    }

    // =========================================================================
    // taluk entity — leaf; MAP entry is empty so always false
    // =========================================================================

    public function testTalukInUseAlwaysReturnsFalse(): void
    {
        $this->pdo->exec("INSERT INTO states (name) VALUES ('Tamil Nadu')");
        $stateId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO districts (state_id, name) VALUES (?,?)"
        )->execute([$stateId, 'Chennai']);
        $districtId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare(
            "INSERT INTO taluks (district_id, name) VALUES (?,?)"
        )->execute([$districtId, 'Ambattur']);
        $talukId = (int)$this->pdo->lastInsertId();
        // No downstream table references taluks yet.
        $this->assertFalse(ReferenceCheck::inUse('taluk', $talukId));
    }

    public function testTalukInUseReturnsFalseForArbitraryId(): void
    {
        $this->assertFalse(ReferenceCheck::inUse('taluk', 1));
    }

    // =========================================================================
    // Unknown entity
    // =========================================================================

    public function testUnknownEntityReturnsFalse(): void
    {
        // MAP has no entry for 'course'; should return false (empty checks).
        $this->assertFalse(ReferenceCheck::inUse('course', 1));
    }

    // =========================================================================
    // Graceful handling of missing tables (option_value entity)
    // =========================================================================

    public function testOptionValueGracefullyHandlesMissingStudentsColumn(): void
    {
        // option_value maps to students.academic_year_id, which does not exist
        // in the test schema.  ReferenceCheck catches the exception and returns false.
        // We verify it does NOT throw; the return value is false because the column
        // doesn't exist (PDO exception swallowed internally).
        $result = ReferenceCheck::inUse('option_value', 1);
        $this->assertFalse($result);
    }
}
