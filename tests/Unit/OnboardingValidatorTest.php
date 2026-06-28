<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\OnboardingValidator;

class OnboardingValidatorTest extends TestCase
{
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'first_name'      => 'Anitha',
            'last_name'       => 'Kumar',
            'dob'             => '01/01/2000',
            'mobile'          => '9876543210',
            'gender'          => 'female',
            'department_id'   => 1,
            'academic_year_id'=> 2,
            'class_id'        => 3,
            'section_id'      => null,
            'admission_date'  => '01/06/2024',
        ], $overrides);
    }

    public function testValidDataPassesWithNoErrors(): void
    {
        $errors = OnboardingValidator::validate($this->validData(), 1);
        $this->assertEmpty($errors);
    }

    public function testFirstNameRequired(): void
    {
        $errors = OnboardingValidator::validate($this->validData(['first_name' => '']), 1);
        $this->assertArrayHasKey('first_name', $errors);
    }

    public function testFirstNameTooLong(): void
    {
        $errors = OnboardingValidator::validate($this->validData(['first_name' => str_repeat('A', 101)]), 1);
        $this->assertArrayHasKey('first_name', $errors);
    }

    public function testFirstNameInvalidChars(): void
    {
        $errors = OnboardingValidator::validate($this->validData(['first_name' => 'An1tha']), 1);
        $this->assertArrayHasKey('first_name', $errors);
    }

    public function testLastNameRequired(): void
    {
        $errors = OnboardingValidator::validate($this->validData(['last_name' => '']), 1);
        $this->assertArrayHasKey('last_name', $errors);
    }

    public function testDobInvalidFormat(): void
    {
        $errors = OnboardingValidator::validate($this->validData(['dob' => '2000-01-01']), 1);
        $this->assertArrayHasKey('dob', $errors);
    }

    public function testDobAgeLessThan15(): void
    {
        // Born 10 years ago relative to admission_date
        $dob = date('d/m/Y', strtotime('-10 years'));
        $errors = OnboardingValidator::validate($this->validData(['dob' => $dob]), 1);
        $this->assertArrayHasKey('dob', $errors);
        $this->assertStringContainsString('15', $errors['dob']);
    }

    public function testMobileMustBe10Digits(): void
    {
        $errors = OnboardingValidator::validate($this->validData(['mobile' => '98765']), 1);
        $this->assertArrayHasKey('mobile', $errors);
    }

    public function testMobileCannotContainLetters(): void
    {
        $errors = OnboardingValidator::validate($this->validData(['mobile' => '987654321A']), 1);
        $this->assertArrayHasKey('mobile', $errors);
    }

    public function testGenderMustBeValid(): void
    {
        $errors = OnboardingValidator::validate($this->validData(['gender' => 'unknown']), 1);
        $this->assertArrayHasKey('gender', $errors);
    }

    public function testDepartmentMismatch(): void
    {
        $errors = OnboardingValidator::validate($this->validData(['department_id' => 99]), 1);
        $this->assertArrayHasKey('department_id', $errors);
    }

    public function testAcademicYearRequired(): void
    {
        $errors = OnboardingValidator::validate($this->validData(['academic_year_id' => '']), 1);
        $this->assertArrayHasKey('academic_year_id', $errors);
    }

    public function testClassRequired(): void
    {
        $errors = OnboardingValidator::validate($this->validData(['class_id' => '']), 1);
        $this->assertArrayHasKey('class_id', $errors);
    }

    public function testAdmissionDateInvalidFormat(): void
    {
        $errors = OnboardingValidator::validate($this->validData(['admission_date' => '2024-06-01']), 1);
        $this->assertArrayHasKey('admission_date', $errors);
    }

    public function testAdmissionDateCannotBeFuture(): void
    {
        $future = date('d/m/Y', strtotime('+1 year'));
        $errors = OnboardingValidator::validate($this->validData(['admission_date' => $future]), 1);
        $this->assertArrayHasKey('admission_date', $errors);
    }

    public function testToDbDateConversion(): void
    {
        $this->assertSame('2000-01-15', OnboardingValidator::toDbDate('15/01/2000'));
        $this->assertNull(OnboardingValidator::toDbDate('invalid'));
        $this->assertNull(OnboardingValidator::toDbDate('2000-01-15'));
    }
}
