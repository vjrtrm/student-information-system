<?php
namespace Tests;

use App\Helpers\Db;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

/** Base test case: fresh in-memory SQLite per test, injected into Db. */
abstract class TestCase extends BaseTestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        foreach (sis_test_schema() as $ddl) {
            $this->pdo->exec($ddl);
        }
        Db::setConnection($this->pdo);
        $_SESSION = [];
    }

    protected function seedDepartment(string $code = 'BCA'): int
    {
        $this->pdo->prepare("INSERT INTO departments (name, code, level) VALUES (?,?,?)")
                  ->execute(["Dept {$code}", $code, 'UG']);
        return (int)$this->pdo->lastInsertId();
    }

    protected function seedUser(string $email, string $password, string $role = 'staff', ?int $dept = null): int
    {
        $this->pdo->prepare(
            "INSERT INTO users (name, email, password_hash, role, department_id) VALUES (?,?,?,?,?)"
        )->execute(['Test User', strtolower($email), password_hash($password, PASSWORD_BCRYPT), $role, $dept]);
        return (int)$this->pdo->lastInsertId();
    }

    protected function seedStudent(string $mobile, string $dob, ?int $dept = null): int
    {
        $this->pdo->prepare("INSERT INTO students (mobile, dob, department_id) VALUES (?,?,?)")
                  ->execute([$mobile, $dob, $dept]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Seed a full M3 student row. Returns new id. */
    protected function seedFullStudent(array $overrides = []): int
    {
        $deptId = $overrides['department_id'] ?? $this->seedDepartment();
        $defaults = [
            'first_name'      => 'Test',
            'last_name'       => 'Student',
            'dob'             => '2000-01-01',
            'mobile'          => '9' . str_pad((string)rand(0, 999999999), 9, '0', STR_PAD_LEFT),
            'gender'          => 'male',
            'department_id'   => $deptId,
            'programme_level' => 'UG',
            'academic_year_id'=> 1,
            'class_id'        => 1,
            'section_id'      => null,
            'admission_date'  => '2024-06-01',
            'onboarding_status' => 'pending_enrolment',
            'login_enabled'   => 0,
            'created_by'      => 1,
            'upload_batch_id' => null,
            'created_at'      => date('Y-m-d H:i:s'),
        ];
        $row = array_merge($defaults, $overrides);
        $this->pdo->prepare(
            "INSERT INTO students
             (first_name, last_name, dob, mobile, gender, department_id, programme_level,
              academic_year_id, class_id, section_id, admission_date, onboarding_status,
              login_enabled, created_by, upload_batch_id, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $row['first_name'], $row['last_name'], $row['dob'], $row['mobile'], $row['gender'],
            $row['department_id'], $row['programme_level'], $row['academic_year_id'],
            $row['class_id'], $row['section_id'], $row['admission_date'], $row['onboarding_status'],
            $row['login_enabled'], $row['created_by'], $row['upload_batch_id'], $row['created_at'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }
}
