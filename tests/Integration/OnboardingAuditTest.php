<?php
namespace Tests\Integration;

use Tests\TestCase;
use App\Models\Student;
use App\Helpers\MasterAuditLogger;
use App\Helpers\Db;

class OnboardingAuditTest extends TestCase
{
    private int $deptId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptId = $this->seedDepartment('BCA');
        $this->userId = $this->seedUser('staff@test.com', 'pass', 'staff', $this->deptId);

        // Simulate a logged-in user so MasterAuditLogger can pick up Auth::id()
        $_SESSION['user'] = [
            'id'            => $this->userId,
            'role'          => 'staff',
            'department_id' => $this->deptId,
        ];
    }

    public function testStudentCreateFollowedByAuditLogWritesRow(): void
    {
        $data = [
            'first_name'      => 'Audit',
            'last_name'       => 'Test',
            'dob'             => '2001-01-01',
            'mobile'          => '9800000001',
            'gender'          => 'male',
            'department_id'   => $this->deptId,
            'programme_level' => 'UG',
            'academic_year_id'=> 1,
            'class_id'        => 1,
            'section_id'      => null,
            'admission_date'  => '2024-06-01',
            'created_by'      => $this->userId,
            'upload_batch_id' => null,
        ];

        $id = Student::create($data);
        $this->assertGreaterThan(0, $id);

        MasterAuditLogger::log('student_created', 'student', $id, ['source' => 'test']);

        $logRow = Db::selectOne(
            "SELECT * FROM audit_log WHERE action = 'student_created' AND entity = 'student' AND entity_id = ?",
            [$id]
        );

        $this->assertNotNull($logRow, 'Audit log row should exist after student creation.');
        $this->assertSame('student_created', $logRow['action']);
        $this->assertSame('student', $logRow['entity']);
        $this->assertSame($id, (int)$logRow['entity_id']);
    }

    public function testBulkUploadAuditLog(): void
    {
        $data = [
            'first_name'      => 'BulkAudit',
            'last_name'       => 'Student',
            'dob'             => '2002-03-10',
            'mobile'          => '9800000002',
            'gender'          => 'female',
            'department_id'   => $this->deptId,
            'programme_level' => 'UG',
            'academic_year_id'=> 1,
            'class_id'        => 1,
            'section_id'      => null,
            'admission_date'  => '2024-06-01',
            'created_by'      => $this->userId,
            'upload_batch_id' => null,
        ];

        $id = Student::create($data);
        MasterAuditLogger::log('student_created_bulk', 'student', null, ['batch' => 42]);

        $logRow = Db::selectOne(
            "SELECT * FROM audit_log WHERE action = 'student_created_bulk' ORDER BY id DESC LIMIT 1",
            []
        );
        $this->assertNotNull($logRow);
        $this->assertSame('student_created_bulk', $logRow['action']);

        $details = json_decode($logRow['details'] ?? '{}', true);
        $this->assertSame(42, $details['batch'] ?? null);
    }
}
