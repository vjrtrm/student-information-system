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
}
