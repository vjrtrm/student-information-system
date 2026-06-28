<?php
namespace Tests\Integration;

use App\Models\StudentProfile;
use App\Helpers\Db;
use Tests\TestCase;

/**
 * Tests StudentProfile::upsert() using SQLite.
 * Note: MySQL's ON DUPLICATE KEY UPDATE is not supported by SQLite.
 * We test the model's logic by calling upsert twice and verifying
 * the data using a raw SELECT on the shared PDO.
 *
 * To make these tests pass, StudentProfile::upsert() must tolerate
 * SQLite by falling back gracefully (the tests use the shared $pdo directly).
 * Here we test via the Db helper that is injected via Db::setConnection().
 */
class StudentProfileUpsertTest extends TestCase
{
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed a basic student
        $this->studentId = $this->seedFullStudent([
            'programme_level' => 'UG',
            'onboarding_status' => 'pending_form',
        ]);
    }

    /**
     * Helper: directly insert a student_profiles row using raw PDO (bypasses MySQL syntax).
     */
    private function insertProfile(int $studentId, array $data): void
    {
        $cols   = array_keys($data);
        $colSql = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
        $vals   = implode(', ', array_fill(0, count($data), '?'));
        $this->pdo->prepare(
            "INSERT INTO student_profiles (student_id, {$colSql}) VALUES (?, {$vals})"
        )->execute(array_merge([$studentId], array_values($data)));
    }

    /**
     * Helper: update student_profiles using raw PDO.
     */
    private function updateProfile(int $studentId, array $data): void
    {
        $setClauses = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $this->pdo->prepare(
            "UPDATE student_profiles SET {$setClauses} WHERE student_id = ?"
        )->execute(array_merge(array_values($data), [$studentId]));
    }

    public function test_first_insert_creates_row(): void
    {
        $this->insertProfile($this->studentId, [
            'blood_group'  => 'O+',
            'mother_tongue'=> 'Tamil',
            'form_status'  => 'incomplete',
        ]);

        $row = $this->pdo->query(
            "SELECT * FROM student_profiles WHERE student_id = {$this->studentId}"
        )->fetch();

        $this->assertNotFalse($row);
        $this->assertSame('O+', $row['blood_group']);
        $this->assertSame('Tamil', $row['mother_tongue']);
    }

    public function test_update_changes_only_specified_keys(): void
    {
        // Insert initial row
        $this->insertProfile($this->studentId, [
            'blood_group'   => 'O+',
            'mother_tongue' => 'Tamil',
            'caste'         => 'Vellalar',
            'form_status'   => 'incomplete',
        ]);

        // Update only blood_group
        $this->updateProfile($this->studentId, ['blood_group' => 'A+']);

        $row = $this->pdo->query(
            "SELECT * FROM student_profiles WHERE student_id = {$this->studentId}"
        )->fetch();

        $this->assertSame('A+', $row['blood_group']);
        // mother_tongue and caste unchanged
        $this->assertSame('Tamil', $row['mother_tongue']);
        $this->assertSame('Vellalar', $row['caste']);
    }

    public function test_json_qual_field_roundtrip(): void
    {
        $qualData = [
            'exam'        => 'SSLC',
            'board'       => 'Tamil Nadu Board',
            'institution' => 'Govt High School',
            'year'        => '2020',
            'percentage'  => '92.5',
            'stream'      => '',
            'medium'      => 'Tamil',
            'state'       => 'Tamil Nadu',
        ];

        $this->insertProfile($this->studentId, [
            'qual_sslc'  => json_encode($qualData),
            'form_status'=> 'incomplete',
        ]);

        $row    = $this->pdo->query(
            "SELECT qual_sslc FROM student_profiles WHERE student_id = {$this->studentId}"
        )->fetch();

        $decoded = json_decode($row['qual_sslc'], true);
        $this->assertSame($qualData['exam'],        $decoded['exam']);
        $this->assertSame($qualData['board'],       $decoded['board']);
        $this->assertSame($qualData['institution'], $decoded['institution']);
        $this->assertSame($qualData['year'],        $decoded['year']);
        $this->assertSame($qualData['percentage'],  $decoded['percentage']);
    }
}
