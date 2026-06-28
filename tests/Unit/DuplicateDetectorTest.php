<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\DuplicateDetector;

class DuplicateDetectorTest extends TestCase
{
    private int $deptId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptId = $this->seedDepartment();
    }

    private function rowData(array $overrides = []): array
    {
        return array_merge([
            'first_name'   => 'Anitha',
            'last_name'    => 'Kumar',
            'dob'          => '01/01/2000',
            'mobile'       => '9876543210',
        ], $overrides);
    }

    public function testNoMatchReturnsNull(): void
    {
        $result = DuplicateDetector::check($this->rowData());
        $this->assertNull($result);
    }

    public function testMobileMatchDetected(): void
    {
        $this->seedFullStudent(['mobile' => '9876543210', 'department_id' => $this->deptId]);
        $result = DuplicateDetector::check($this->rowData(['first_name' => 'Different']));
        $this->assertNotNull($result);
        $this->assertSame('mobile_exists', $result['type']);
    }

    public function testNameDobMatchDetected(): void
    {
        $this->seedFullStudent([
            'first_name'   => 'Anitha',
            'last_name'    => 'Kumar',
            'dob'          => '2000-01-01',
            'mobile'       => '1111111111',
            'department_id'=> $this->deptId,
        ]);
        $result = DuplicateDetector::check($this->rowData(['mobile' => '9999999999']));
        $this->assertNotNull($result);
        $this->assertSame('name_dob_exists', $result['type']);
    }

    public function testBothMatchDetected(): void
    {
        $this->seedFullStudent([
            'first_name'   => 'Anitha',
            'last_name'    => 'Kumar',
            'dob'          => '2000-01-01',
            'mobile'       => '9876543210',
            'department_id'=> $this->deptId,
        ]);
        $result = DuplicateDetector::check($this->rowData());
        $this->assertNotNull($result);
        $this->assertSame('both', $result['type']);
    }

    public function testExcludeIdPreventsSelfMatch(): void
    {
        $id = $this->seedFullStudent([
            'first_name'   => 'Anitha',
            'last_name'    => 'Kumar',
            'dob'          => '2000-01-01',
            'mobile'       => '9876543210',
            'department_id'=> $this->deptId,
        ]);
        $result = DuplicateDetector::check($this->rowData(), $id);
        $this->assertNull($result);
    }
}
