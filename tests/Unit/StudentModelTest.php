<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Student;

class StudentModelTest extends TestCase
{
    private int $deptId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptId = $this->seedDepartment('BCA');
        // Seed a user for created_by
        $this->seedUser('staff@test.com', 'pass', 'staff', $this->deptId);
    }

    private function makeStudentData(array $overrides = []): array
    {
        return array_merge([
            'first_name'      => 'Ravi',
            'last_name'       => 'Shankar',
            'dob'             => '2002-05-10',
            'mobile'          => '9876543210',
            'gender'          => 'male',
            'department_id'   => $this->deptId,
            'programme_level' => 'UG',
            'academic_year_id'=> 1,
            'class_id'        => 1,
            'section_id'      => null,
            'admission_date'  => '2024-06-01',
            'created_by'      => 1,
            'upload_batch_id' => null,
        ], $overrides);
    }

    public function testCreateReturnsId(): void
    {
        $id = Student::create($this->makeStudentData());
        $this->assertGreaterThan(0, $id);
    }

    public function testCreatedStudentHasPendingEnrolmentStatus(): void
    {
        $id = Student::create($this->makeStudentData());
        $row = Student::find($id);
        $this->assertSame('pending_enrolment', $row['onboarding_status']);
        $this->assertSame(0, (int)$row['login_enabled']);
    }

    public function testFindAnyByMobile(): void
    {
        Student::create($this->makeStudentData(['mobile' => '9111111111']));
        $found = Student::findAnyByMobile('9111111111');
        $this->assertNotNull($found);
        $this->assertSame('9111111111', $found['mobile']);
    }

    public function testFindAnyByMobileReturnsNullForMissing(): void
    {
        $this->assertNull(Student::findAnyByMobile('0000000000'));
    }

    public function testFindByNameDob(): void
    {
        Student::create($this->makeStudentData(['first_name' => 'Suma', 'last_name' => 'Rajan', 'dob' => '2001-03-15', 'mobile' => '9222222222']));
        $found = Student::findByNameDob('Suma', 'Rajan', '2001-03-15');
        $this->assertNotNull($found);
        $this->assertSame('Suma', $found['first_name']);
    }

    public function testUpdateStatus(): void
    {
        $id = Student::create($this->makeStudentData(['mobile' => '9333333333']));
        Student::updateStatus($id, 'enrolment_assigned');
        $row = Student::find($id);
        $this->assertSame('enrolment_assigned', $row['onboarding_status']);
    }

    public function testEnableLogin(): void
    {
        $id = Student::create($this->makeStudentData(['mobile' => '9444444444']));
        Student::enableLogin($id);
        $row = Student::find($id);
        $this->assertSame(1, (int)$row['login_enabled']);
    }

    public function testGetListWithDeptFilter(): void
    {
        Student::create($this->makeStudentData(['mobile' => '9555555555']));
        $list = Student::getList(['department_id' => $this->deptId]);
        $this->assertNotEmpty($list);
        foreach ($list as $s) {
            $this->assertSame($this->deptId, (int)$s['department_id']);
        }
    }

    public function testGetListWithStatusFilter(): void
    {
        $id = Student::create($this->makeStudentData(['mobile' => '9666666666']));
        Student::updateStatus($id, 'approved');
        $list = Student::getList(['onboarding_status' => 'approved']);
        $this->assertCount(1, $list);
        $this->assertSame('approved', $list[0]['onboarding_status']);
    }

    public function testGetListWithSearchFilter(): void
    {
        Student::create($this->makeStudentData(['first_name' => 'Unique', 'mobile' => '9777777777']));
        $list = Student::getList(['search' => 'Unique']);
        $this->assertNotEmpty($list);
        $this->assertSame('Unique', $list[0]['first_name']);
    }

    public function testCountList(): void
    {
        Student::create($this->makeStudentData(['mobile' => '9888888881']));
        Student::create($this->makeStudentData(['mobile' => '9888888882']));
        $count = Student::countList(['department_id' => $this->deptId]);
        $this->assertSame(2, $count);
    }
}
