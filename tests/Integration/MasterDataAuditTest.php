<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Helpers\Auth;
use App\Helpers\MasterAuditLogger;
use App\Models\Department;

/**
 * Integration tests for MasterAuditLogger (Module 2 — Master Data).
 *
 * Verifies that MasterAuditLogger::log() correctly writes rows to the
 * audit_log table, pulling actor context from the active session via Auth::user().
 *
 * audit_log is NOT in the base sis_test_schema(), so it is created in setUp()
 * here.  Once Module 2 is promoted to the canonical schema this CREATE TABLE
 * should move into bootstrap.php's sis_test_schema().
 */
class MasterDataAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // creates base schema, injects PDO into Db, clears $_SESSION

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                actor_id INTEGER,
                actor_role TEXT,
                action TEXT NOT NULL,
                entity TEXT NOT NULL,
                entity_id INTEGER,
                details TEXT,
                ip TEXT,
                created_at TEXT
            )"
        );

        // Simulate an authenticated institution_admin session for Auth::user()
        Auth::start();
        $_SESSION['principal_type'] = 'user';
        $_SESSION['principal_id']   = 42;
        $_SESSION['role']           = 'institution_admin';
        $_SESSION['department_id']  = null;
        $_SESSION['last_activity']  = time();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    // =========================================================================
    // Basic row creation
    // =========================================================================

    public function testAuditLogRowIsCreatedOnDepartmentCreate(): void
    {
        $deptId = Department::create('Test Department', 'TST', 'UG');
        MasterAuditLogger::log('create', 'department', $deptId, [
            'name' => 'Test Department',
            'code' => 'TST',
        ]);

        $row = $this->pdo->query(
            "SELECT * FROM audit_log WHERE entity = 'department' AND action = 'create'"
        )->fetch();

        $this->assertNotFalse($row, 'Expected an audit_log row for department create');
        $this->assertSame(42, (int)$row['actor_id']);
        $this->assertSame('institution_admin', $row['actor_role']);
        $this->assertSame('create', $row['action']);
        $this->assertSame('department', $row['entity']);
        $this->assertSame($deptId, (int)$row['entity_id']);
    }

    public function testAuditLogDetailsAreJsonEncoded(): void
    {
        $deptId = Department::create('Audit Dept', 'AUD', 'PG');
        MasterAuditLogger::log('create', 'department', $deptId, [
            'name' => 'Audit Dept',
            'code' => 'AUD',
        ]);

        $row     = $this->pdo->query(
            "SELECT details FROM audit_log WHERE entity = 'department'"
        )->fetch();
        $details = json_decode($row['details'], true);
        $this->assertIsArray($details);
        $this->assertSame('Audit Dept', $details['name']);
        $this->assertSame('AUD', $details['code']);
    }

    public function testAuditLogTimestampIsRecorded(): void
    {
        MasterAuditLogger::log('create', 'department', 1, ['name' => 'X']);
        $row = $this->pdo->query("SELECT created_at FROM audit_log")->fetch();
        $this->assertNotNull($row['created_at']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $row['created_at']
        );
    }

    // =========================================================================
    // Null details
    // =========================================================================

    public function testAuditLogStoresNullWhenDetailsEmpty(): void
    {
        MasterAuditLogger::log('deactivate', 'department', 5);
        $row = $this->pdo->query(
            "SELECT details FROM audit_log WHERE action = 'deactivate'"
        )->fetch();
        $this->assertNotFalse($row);
        $this->assertNull($row['details']);
    }

    public function testAuditLogStoresNullWhenDetailsExplicitlyEmpty(): void
    {
        MasterAuditLogger::log('reactivate', 'department', 7, []);
        $row = $this->pdo->query(
            "SELECT details FROM audit_log WHERE action = 'reactivate'"
        )->fetch();
        $this->assertNotFalse($row);
        $this->assertNull($row['details']);
    }

    // =========================================================================
    // Null entity_id (bulk operations)
    // =========================================================================

    public function testAuditLogWithNullEntityId(): void
    {
        MasterAuditLogger::log('import', 'geography', null, [
            'created' => 10,
            'skipped' => 2,
        ]);
        $row = $this->pdo->query(
            "SELECT * FROM audit_log WHERE action = 'import'"
        )->fetch();
        $this->assertNotFalse($row);
        $this->assertNull($row['entity_id']);
        $details = json_decode($row['details'], true);
        $this->assertSame(10, $details['created']);
        $this->assertSame(2, $details['skipped']);
    }

    // =========================================================================
    // Multiple log entries
    // =========================================================================

    public function testMultipleActionsAreEachLogged(): void
    {
        $deptId = Department::create('Multi Dept', 'MLT', 'UG');
        MasterAuditLogger::log('create',     'department', $deptId, ['name' => 'Multi Dept']);
        MasterAuditLogger::log('deactivate', 'department', $deptId);
        MasterAuditLogger::log('reactivate', 'department', $deptId);

        $count = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM audit_log WHERE entity = 'department'"
        )->fetchColumn();
        $this->assertSame(3, $count);
    }

    // =========================================================================
    // Actor from session
    // =========================================================================

    public function testActorIdAndRoleMatchSession(): void
    {
        // Change session to a dept_admin.
        $_SESSION['principal_id'] = 99;
        $_SESSION['role']         = 'dept_admin';

        MasterAuditLogger::log('update', 'state', 3, ['name' => 'Updated State']);

        $row = $this->pdo->query(
            "SELECT actor_id, actor_role FROM audit_log WHERE action = 'update'"
        )->fetch();
        $this->assertSame(99, (int)$row['actor_id']);
        $this->assertSame('dept_admin', $row['actor_role']);
    }

    public function testActorIsNullWhenNotLoggedIn(): void
    {
        // Clear session to simulate unauthenticated context.
        $_SESSION = [];

        MasterAuditLogger::log('import', 'geography', null, ['source' => 'csv']);

        $row = $this->pdo->query(
            "SELECT actor_id, actor_role FROM audit_log WHERE action = 'import'"
        )->fetch();
        $this->assertNull($row['actor_id']);
        $this->assertNull($row['actor_role']);
    }

    // =========================================================================
    // Various entity types
    // =========================================================================

    public function testLogWorksForStateEntity(): void
    {
        MasterAuditLogger::log('create', 'state', 1, ['name' => 'Tamil Nadu']);
        $row = $this->pdo->query(
            "SELECT * FROM audit_log WHERE entity = 'state'"
        )->fetch();
        $this->assertNotFalse($row);
        $this->assertSame('state', $row['entity']);
        $this->assertSame(1, (int)$row['entity_id']);
    }

    public function testLogWorksForOptionValueEntity(): void
    {
        MasterAuditLogger::log('create', 'option_value', 7, ['value' => 'A+']);
        $row = $this->pdo->query(
            "SELECT * FROM audit_log WHERE entity = 'option_value'"
        )->fetch();
        $this->assertNotFalse($row);
        $details = json_decode($row['details'], true);
        $this->assertSame('A+', $details['value']);
    }
}
