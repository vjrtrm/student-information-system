<?php
namespace App\Helpers;

use App\Models\Department;
use App\Models\EnrolmentBatch;
use App\Models\OptionValue;
use App\Models\Student;

class EnrolmentNumberGenerator
{
    /**
     * Format a single enrolment number from components.
     * e.g. format(['code'=>'BCA','level'=>'UG'], '2024-25', 41) → '24UBCA041'
     */
    public static function format(array $dept, string $ayValue, int $serial): string
    {
        $yy        = substr($ayValue, 2, 2);                         // "2024-25" → "24"
        $level     = ($dept['level'] === 'UG') ? 'U' : 'P';
        $code      = strtoupper($dept['code']);
        $pad       = (int)Config::get('enrolment.serial_pad_length', 3);
        $serialStr = str_pad((string)$serial, $pad, '0', STR_PAD_LEFT);
        return $yy . $level . $code . $serialStr;
    }

    /**
     * Generate enrolment numbers for all eligible students in dept+year.
     * Runs inside a transaction. Returns the new batch ID.
     *
     * @throws \RuntimeException if blocked or no eligible students
     */
    public static function generate(int $deptId, int $ayId, int $generatedBy): int
    {
        // Pre-flight checks (outside transaction — fast fails)
        if (Student::hasPendingBatch($deptId, $ayId)) {
            throw new \RuntimeException(
                'A batch is already pending — approve all existing numbers before generating a new one.'
            );
        }
        $eligible = Student::findPendingForGeneration($deptId, $ayId);
        if (empty($eligible)) {
            throw new \RuntimeException(
                'No students with Pending Enrolment status found for this academic year.'
            );
        }

        $dept = Department::find($deptId);
        $ay   = OptionValue::find($ayId);
        if (!$dept || !$ay) {
            throw new \RuntimeException('Invalid department or academic year.');
        }

        $pdo = Db::conn();
        $pdo->beginTransaction();
        try {
            // Lock rows to prevent concurrent generation producing duplicate serials.
            // FOR UPDATE is MySQL-only; skip on SQLite (used in tests).
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver !== 'sqlite') {
                Db::execute(
                    "SELECT id FROM students
                     WHERE department_id = ? AND academic_year_id = ?
                     FOR UPDATE",
                    [$deptId, $ayId]
                );
            }

            $nextSerial = Student::maxSerial($deptId, $ayId) + 1;
            $batchId    = EnrolmentBatch::create($deptId, $ayId, $generatedBy);

            foreach ($eligible as $student) {
                $serial = $nextSerial++;
                $number = self::format($dept, $ay['value'], $serial);
                Student::assignEnrolmentNumber((int)$student['id'], $number, $serial, $batchId);
            }

            EnrolmentBatch::updateStudentCount($batchId, count($eligible));
            $pdo->commit();

            MasterAuditLogger::log('enrolment_batch_generated', 'enrolment_batch', $batchId, [
                'department_id'    => $deptId,
                'academic_year_id' => $ayId,
                'count'            => count($eligible),
            ]);

            return $batchId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
