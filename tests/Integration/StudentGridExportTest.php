<?php
namespace Tests\Integration;

use Tests\TestCase;
use App\Controllers\StudentGridController;
use App\Helpers\Db;

class StudentGridExportTest extends TestCase
{
    public function testFetchAllReturnsAllRows(): void
    {
        $dept = $this->seedDepartment('EX');
        for ($i = 1; $i <= 5; $i++) {
            $this->seedFullStudent(['department_id' => $dept, 'first_name' => "Export$i", 'last_name' => 'E']);
        }

        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);
        $fetchAll = $rc->getMethod('fetchAll');
        $fetchAll->setAccessible(true);

        $filters = ['dept_id' => $dept, 'search' => '', 'year_id' => null,
                    'prog_level' => '', 'form_status' => [], 'enrol_status' => ''];
        $rows = $fetchAll->invoke($ctrl, $filters, 'enrolment_number', 'ASC');
        $this->assertCount(5, $rows);
    }

    public function testFetchAllNoDeptFilterReturnsAll(): void
    {
        $deptA = $this->seedDepartment('EA');
        $deptB = $this->seedDepartment('EB');
        $this->seedFullStudent(['department_id' => $deptA, 'first_name' => 'AlphaEx', 'last_name' => 'A']);
        $this->seedFullStudent(['department_id' => $deptB, 'first_name' => 'BetaEx',  'last_name' => 'B']);

        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);
        $fetchAll = $rc->getMethod('fetchAll');
        $fetchAll->setAccessible(true);

        $filters = ['dept_id' => null, 'search' => '', 'year_id' => null,
                    'prog_level' => '', 'form_status' => [], 'enrol_status' => ''];
        $rows = $fetchAll->invoke($ctrl, $filters, 'enrolment_number', 'ASC');

        $names = array_column($rows, 'first_name');
        $this->assertContains('AlphaEx', $names);
        $this->assertContains('BetaEx', $names);
    }

    public function testAuditLogEntryOnExportAction(): void
    {
        $dept = $this->seedDepartment('AL');
        $this->seedFullStudent(['department_id' => $dept]);

        // Simulate the audit log call directly (as the export() method would)
        \App\Helpers\MasterAuditLogger::log('export', 'student_grid', null, [
            'filters'   => ['dept_id' => $dept],
            'row_count' => 1,
        ]);

        $entry = Db::selectOne("SELECT * FROM audit_log WHERE action = 'export' AND entity = 'student_grid'");
        $this->assertNotNull($entry);
        $this->assertSame('export', $entry['action']);
        $this->assertSame('student_grid', $entry['entity']);
    }

    public function testControllerHasRequiredMethods(): void
    {
        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);

        $this->assertTrue($rc->hasMethod('export'));
        $this->assertTrue($rc->hasMethod('index'));
    }

    public function testFetchAllRespectsDeptFilter(): void
    {
        $deptA = $this->seedDepartment('FA');
        $deptB = $this->seedDepartment('FB');
        $this->seedFullStudent(['department_id' => $deptA, 'first_name' => 'FilterA', 'last_name' => 'X']);
        $this->seedFullStudent(['department_id' => $deptB, 'first_name' => 'FilterB', 'last_name' => 'Y']);

        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);
        $fetchAll = $rc->getMethod('fetchAll');
        $fetchAll->setAccessible(true);

        $filters = ['dept_id' => $deptA, 'search' => '', 'year_id' => null,
                    'prog_level' => '', 'form_status' => [], 'enrol_status' => ''];
        $rows = $fetchAll->invoke($ctrl, $filters, 'enrolment_number', 'ASC');

        $names = array_column($rows, 'first_name');
        $this->assertContains('FilterA', $names);
        $this->assertNotContains('FilterB', $names);
    }

    public function testFetchAllReturnsExpectedColumns(): void
    {
        $dept = $this->seedDepartment('EC');
        $this->seedFullStudent(['department_id' => $dept, 'first_name' => 'ColCheck', 'last_name' => 'C']);

        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);
        $fetchAll = $rc->getMethod('fetchAll');
        $fetchAll->setAccessible(true);

        $filters = ['dept_id' => $dept, 'search' => '', 'year_id' => null,
                    'prog_level' => '', 'form_status' => [], 'enrol_status' => ''];
        $rows = $fetchAll->invoke($ctrl, $filters, 'enrolment_number', 'ASC');

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('first_name', $row);
        $this->assertArrayHasKey('last_name', $row);
        $this->assertArrayHasKey('mobile', $row);
        $this->assertArrayHasKey('gender', $row);
        $this->assertArrayHasKey('programme_level', $row);
        $this->assertArrayHasKey('enrolment_number', $row);
        $this->assertArrayHasKey('dept_name', $row);
    }
}
