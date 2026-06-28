<?php
namespace Tests\Integration;

use Tests\TestCase;
use App\Controllers\StudentGridController;
use App\Helpers\Db;

class StudentGridIndexTest extends TestCase
{
    public function testDeptScopingBlocksOtherDeptStudents(): void
    {
        $deptA = $this->seedDepartment('DA');
        $deptB = $this->seedDepartment('DB');
        $this->seedFullStudent(['department_id' => $deptA, 'first_name' => 'Alice', 'last_name' => 'Aaa']);
        $this->seedFullStudent(['department_id' => $deptB, 'first_name' => 'Bob',   'last_name' => 'Bbb']);

        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);

        $filters = ['dept_id' => $deptA, 'search' => '', 'year_id' => null,
                    'prog_level' => '', 'form_status' => [], 'enrol_status' => ''];
        $fetchPage = $rc->getMethod('fetchPage');
        $fetchPage->setAccessible(true);
        $rows = $fetchPage->invoke($ctrl, $filters, 1, 25, 'enrolment_number', 'ASC');

        $names = array_column($rows, 'first_name');
        $this->assertContains('Alice', $names);
        $this->assertNotContains('Bob', $names);
    }

    public function testSearchFilterByFirstName(): void
    {
        $dept = $this->seedDepartment('SR');
        $this->seedFullStudent(['department_id' => $dept, 'first_name' => 'Charlie', 'last_name' => 'Ccc']);
        $this->seedFullStudent(['department_id' => $dept, 'first_name' => 'Diana',   'last_name' => 'Ddd']);

        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);

        $filters = ['dept_id' => $dept, 'search' => 'Charlie', 'year_id' => null,
                    'prog_level' => '', 'form_status' => [], 'enrol_status' => ''];
        $fetchPage = $rc->getMethod('fetchPage');
        $fetchPage->setAccessible(true);
        $rows = $fetchPage->invoke($ctrl, $filters, 1, 25, 'enrolment_number', 'ASC');

        $this->assertCount(1, $rows);
        $this->assertSame('Charlie', $rows[0]['first_name']);
    }

    public function testInstAdminSeesAllDepts(): void
    {
        $deptA = $this->seedDepartment('DX');
        $deptB = $this->seedDepartment('DY');
        $this->seedFullStudent(['department_id' => $deptA, 'first_name' => 'Eve',   'last_name' => 'Eee']);
        $this->seedFullStudent(['department_id' => $deptB, 'first_name' => 'Frank', 'last_name' => 'Fff']);

        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);

        // No dept_id filter = inst_admin sees all
        $filters = ['dept_id' => null, 'search' => '', 'year_id' => null,
                    'prog_level' => '', 'form_status' => [], 'enrol_status' => ''];
        $fetchPage = $rc->getMethod('fetchPage');
        $fetchPage->setAccessible(true);
        $rows = $fetchPage->invoke($ctrl, $filters, 1, 100, 'enrolment_number', 'ASC');

        $names = array_column($rows, 'first_name');
        $this->assertContains('Eve', $names);
        $this->assertContains('Frank', $names);
    }

    public function testPaginationLimitsRows(): void
    {
        $dept = $this->seedDepartment('PG');
        for ($i = 1; $i <= 30; $i++) {
            $this->seedFullStudent(['department_id' => $dept, 'first_name' => "Student$i", 'last_name' => 'Tst']);
        }

        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);
        $filters = ['dept_id' => $dept, 'search' => '', 'year_id' => null,
                    'prog_level' => '', 'form_status' => [], 'enrol_status' => ''];

        $fetchPage = $rc->getMethod('fetchPage');
        $fetchPage->setAccessible(true);

        $page1 = $fetchPage->invoke($ctrl, $filters, 1, 25, 'enrolment_number', 'ASC');
        $this->assertCount(25, $page1);

        $page2 = $fetchPage->invoke($ctrl, $filters, 2, 25, 'enrolment_number', 'ASC');
        $this->assertCount(5, $page2);
    }

    public function testFetchCountReturnsCorrectTotal(): void
    {
        $dept = $this->seedDepartment('CT');
        $this->seedFullStudent(['department_id' => $dept, 'first_name' => 'One',   'last_name' => 'O']);
        $this->seedFullStudent(['department_id' => $dept, 'first_name' => 'Two',   'last_name' => 'T']);
        $this->seedFullStudent(['department_id' => $dept, 'first_name' => 'Three', 'last_name' => 'H']);

        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);

        $filters = ['dept_id' => $dept, 'search' => '', 'year_id' => null,
                    'prog_level' => '', 'form_status' => [], 'enrol_status' => ''];
        $fetchCount = $rc->getMethod('fetchCount');
        $fetchCount->setAccessible(true);

        $count = $fetchCount->invoke($ctrl, $filters);
        $this->assertSame(3, $count);
    }

    public function testProgrammeLevelFilter(): void
    {
        $dept = $this->seedDepartment('PL');
        $this->seedFullStudent(['department_id' => $dept, 'first_name' => 'UgStudent', 'last_name' => 'U', 'programme_level' => 'UG']);
        $this->seedFullStudent(['department_id' => $dept, 'first_name' => 'PgStudent', 'last_name' => 'P', 'programme_level' => 'PG']);

        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);

        $filters = ['dept_id' => $dept, 'search' => '', 'year_id' => null,
                    'prog_level' => 'UG', 'form_status' => [], 'enrol_status' => ''];
        $fetchPage = $rc->getMethod('fetchPage');
        $fetchPage->setAccessible(true);
        $rows = $fetchPage->invoke($ctrl, $filters, 1, 25, 'enrolment_number', 'ASC');

        $names = array_column($rows, 'first_name');
        $this->assertContains('UgStudent', $names);
        $this->assertNotContains('PgStudent', $names);
    }

    public function testStatChipsReturnExpectedKeys(): void
    {
        $dept = $this->seedDepartment('SC');
        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);

        $filters = ['dept_id' => $dept, 'search' => '', 'year_id' => null,
                    'prog_level' => '', 'form_status' => [], 'enrol_status' => ''];
        $fetchStatChips = $rc->getMethod('fetchStatChips');
        $fetchStatChips->setAccessible(true);

        $chips = $fetchStatChips->invoke($ctrl, $filters);
        $this->assertArrayHasKey('total', $chips);
        $this->assertArrayHasKey('submitted', $chips);
        $this->assertArrayHasKey('approved', $chips);
    }

    public function testEnrolmentStatusFilterNotGenerated(): void
    {
        $dept = $this->seedDepartment('EG');
        $this->seedFullStudent(['department_id' => $dept, 'first_name' => 'NoEnrol',  'last_name' => 'N', 'enrolment_approval_status' => null]);
        $this->seedFullStudent(['department_id' => $dept, 'first_name' => 'HasEnrol', 'last_name' => 'H', 'enrolment_approval_status' => 'approved']);

        $ctrl = new StudentGridController();
        $rc   = new \ReflectionClass($ctrl);

        $filters = ['dept_id' => $dept, 'search' => '', 'year_id' => null,
                    'prog_level' => '', 'form_status' => [], 'enrol_status' => 'not_generated'];
        $fetchPage = $rc->getMethod('fetchPage');
        $fetchPage->setAccessible(true);
        $rows = $fetchPage->invoke($ctrl, $filters, 1, 25, 'enrolment_number', 'ASC');

        $names = array_column($rows, 'first_name');
        $this->assertContains('NoEnrol', $names);
        $this->assertNotContains('HasEnrol', $names);
    }
}
