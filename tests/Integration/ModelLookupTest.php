<?php
namespace Tests\Integration;

use App\Models\Student;
use App\Models\User;
use Tests\TestCase;

/** Login lookups behave safely (Design §5.1/§5.2, §10). */
class ModelLookupTest extends TestCase
{
    public function testFindStaffByEmailCaseInsensitive(): void
    {
        $dept = $this->seedDepartment();
        $this->seedUser('Staff@College.edu', 'Secret#1', 'staff', $dept);

        $u = User::findByEmail('staff@college.edu');
        $this->assertNotNull($u);
        $this->assertSame('staff', $u['role']);
    }

    public function testStudentLookupByMobile(): void
    {
        $this->seedStudent('9879879870', '2007-10-10');
        $this->pdo->exec("UPDATE students SET login_enabled = 1 WHERE mobile = '9879879870'");
        $s = Student::findByMobile('9879879870');
        $this->assertNotNull($s);
        $this->assertSame('2007-10-10', $s['dob']);
    }

    public function testAmbiguousMobileRejected(): void
    {
        // Data-integrity guard: duplicate mobiles must never authenticate (Design §10)
        $this->seedStudent('9999999999', '2000-01-01');
        $this->seedStudent('9999999999', '2001-02-02');
        $this->assertNull(Student::findByMobile('9999999999'));
    }

    public function testUnknownReturnsNull(): void
    {
        $this->assertNull(User::findByEmail('nobody@x.com'));
        $this->assertNull(Student::findByMobile('0000000000'));
    }
}
