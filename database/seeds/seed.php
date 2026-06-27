<?php
// Seed test accounts (Task M1-T04). Usage: php database/seeds/seed.php
require __DIR__ . '/../../vendor/autoload.php';

use App\Helpers\Config;
use App\Helpers\Db;

Config::setPath(__DIR__ . '/../../config');
$pdo = Db::conn();

// Department
$pdo->prepare("INSERT INTO departments (name, code, level) VALUES (?,?,?)
               ON DUPLICATE KEY UPDATE name=VALUES(name)")
    ->execute(['Bachelor of Computer Applications', 'BCA', 'UG']);
$deptId = (int)$pdo->lastInsertId();
if (!$deptId) {
    $deptId = (int)$pdo->query("SELECT id FROM departments WHERE code='BCA'")->fetchColumn();
}

$hash = password_hash('ChangeMe#123', PASSWORD_BCRYPT);

$users = [
    ['Institution Admin', 'admin@college.edu',     'institution_admin', null],
    ['BCA Dept Admin',    'bca.admin@college.edu', 'dept_admin',        $deptId],
    ['BCA Staff',         'bca.staff@college.edu', 'staff',             $deptId],
];
foreach ($users as [$name, $email, $role, $dept]) {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, department_id)
                           VALUES (?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE name=VALUES(name)");
    $stmt->execute([$name, $email, $hash, $role, $dept]);
}

// Sample student: mobile 9879879870 + DOB 2007-10-10
$stmt = $pdo->prepare("INSERT INTO students (mobile, dob, department_id) VALUES (?,?,?)");
$exists = $pdo->prepare("SELECT id FROM students WHERE mobile=?");
$exists->execute(['9879879870']);
if (!$exists->fetchColumn()) {
    $stmt->execute(['9879879870', '2007-10-10', $deptId]);
}

echo "Seed complete. Default password for staff/admin: ChangeMe#123 (change on first login).\n";
echo "Sample student login: mobile 9879879870 / DOB 2007-10-10.\n";
