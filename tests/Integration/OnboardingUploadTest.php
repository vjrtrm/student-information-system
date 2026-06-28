<?php
namespace Tests\Integration;

use Tests\TestCase;
use App\Models\Student;
use App\Models\UploadBatch;
use App\Models\DuplicateOverrideRequest;
use App\Helpers\OnboardingValidator;
use App\Helpers\DuplicateDetector;

class OnboardingUploadTest extends TestCase
{
    private int $deptId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptId = $this->seedDepartment('MCA');
        $this->userId = $this->seedUser('staff@mca.edu', 'pass', 'staff', $this->deptId);
    }

    private function studentRow(array $overrides = []): array
    {
        return array_merge([
            'first_name'      => 'Priya',
            'last_name'       => 'Nair',
            'dob'             => '01/05/2002',
            'mobile'          => '9900000001',
            'gender'          => 'female',
            'department_id'   => $this->deptId,
            'programme_level' => 'PG',
            'academic_year_id'=> 1,
            'class_id'        => 1,
            'section_id'      => null,
            'admission_date'  => '01/06/2024',
        ], $overrides);
    }

    public function testValidRowCreatesStudentWithCorrectDefaults(): void
    {
        $data = $this->studentRow();
        $errors = OnboardingValidator::validate($data, $this->deptId);
        $this->assertEmpty($errors, 'Validation should pass: ' . json_encode($errors));

        $batchId = UploadBatch::create($this->deptId, $this->userId, 'test.xlsx', 1);
        $data['dob'] = OnboardingValidator::toDbDate($data['dob']);
        $data['admission_date'] = OnboardingValidator::toDbDate($data['admission_date']);
        $data['created_by'] = $this->userId;
        $data['upload_batch_id'] = $batchId;

        $id = Student::create($data);
        $this->assertGreaterThan(0, $id);

        $student = Student::find($id);
        $this->assertSame('pending_enrolment', $student['onboarding_status']);
        $this->assertSame(0, (int)$student['login_enabled']);
        $this->assertSame('Priya', $student['first_name']);
        $this->assertSame('9900000001', $student['mobile']);
        $this->assertSame($batchId, (int)$student['upload_batch_id']);
    }

    public function testDuplicateMobileIsHeld(): void
    {
        // Create existing student
        $this->seedFullStudent(['mobile' => '9900000002', 'department_id' => $this->deptId]);

        $data = $this->studentRow(['mobile' => '9900000002', 'first_name' => 'Different']);
        $dup = DuplicateDetector::check($data);

        $this->assertNotNull($dup);
        $this->assertSame('mobile_exists', $dup['type']);

        $batchId = UploadBatch::create($this->deptId, $this->userId, 'test.xlsx', 1);
        $overrideId = DuplicateOverrideRequest::create([
            'upload_batch_id'    => $batchId,
            'source_row_number'  => 2,
            'student_data'       => $data,
            'flagged_reason'     => $dup['type'],
            'existing_student_id'=> $dup['existing_student_id'],
            'requested_by'       => $this->userId,
        ]);

        $this->assertGreaterThan(0, $overrideId);
        $pending = DuplicateOverrideRequest::findPendingByBatch($batchId);
        $this->assertCount(1, $pending);
    }

    public function testInvalidRowFailsValidation(): void
    {
        $data = $this->studentRow([
            'mobile' => '123',      // too short
            'first_name' => '',     // missing
            'admission_date' => date('d/m/Y', strtotime('+1 year')), // future
        ]);
        $errors = OnboardingValidator::validate($data, $this->deptId);
        $this->assertArrayHasKey('mobile', $errors);
        $this->assertArrayHasKey('first_name', $errors);
        $this->assertArrayHasKey('admission_date', $errors);
    }

    public function testBatchCountsUpdatedCorrectly(): void
    {
        $batchId = UploadBatch::create($this->deptId, $this->userId, 'batch.xlsx', 3);
        UploadBatch::updateCounts($batchId, 2, 1, 0);
        $batch = UploadBatch::find($batchId);
        $this->assertSame(2, (int)$batch['created_count']);
        $this->assertSame(1, (int)$batch['duplicate_held_count']);
        $this->assertSame(0, (int)$batch['failed_count']);
    }
}
