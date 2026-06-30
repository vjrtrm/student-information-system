<?php
namespace App\Models;

use App\Helpers\Db;

class PromotionBatch
{
    public static function findAll(int $deptId = 0): array
    {
        $where  = $deptId > 0 ? 'WHERE pb.department_id = ?' : '';
        $params = $deptId > 0 ? [$deptId] : [];
        return Db::selectAll(
            "SELECT pb.*, d.name AS dept_name,
                    u.name AS initiated_by_name,
                    r.name AS reviewed_by_name,
                    ov.display AS target_year_label
             FROM promotion_batches pb
             JOIN departments d ON d.id = pb.department_id
             JOIN users u ON u.id = pb.initiated_by
             LEFT JOIN users r ON r.id = pb.reviewed_by
             LEFT JOIN option_values ov ON ov.id = pb.target_academic_year_id
             $where
             ORDER BY pb.created_at DESC",
            $params
        );
    }

    public static function findById(int $id): ?array
    {
        return Db::selectOne(
            "SELECT pb.*, d.name AS dept_name,
                    u.name AS initiated_by_name,
                    r.name AS reviewed_by_name,
                    oy.display AS target_year_label,
                    oc.display AS target_class_label,
                    os.display AS target_section_label
             FROM promotion_batches pb
             JOIN departments d ON d.id = pb.department_id
             JOIN users u ON u.id = pb.initiated_by
             LEFT JOIN users r ON r.id = pb.reviewed_by
             LEFT JOIN option_values oy ON oy.id = pb.target_academic_year_id
             LEFT JOIN option_values oc ON oc.id = pb.target_class_id
             LEFT JOIN option_values os ON os.id = pb.target_section_id
             WHERE pb.id = ?",
            [$id]
        );
    }

    public static function findPendingForDept(int $deptId): ?array
    {
        return Db::selectOne(
            "SELECT id FROM promotion_batches WHERE department_id = ? AND status = 'pending_approval' LIMIT 1",
            [$deptId]
        );
    }

    public static function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        Db::execute(
            "INSERT INTO promotion_batches
             (department_id, target_academic_year_id, target_class_id, target_section_id,
              status, requires_inst_admin, initiated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['department_id'],
                $data['target_academic_year_id'],
                $data['target_class_id'],
                $data['target_section_id'],
                $data['status'] ?? 'pending_approval',
                $data['requires_inst_admin'] ?? 0,
                $data['initiated_by'],
                $now, $now,
            ]
        );
        return (int) Db::lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $now = date('Y-m-d H:i:s');
        Db::execute(
            "UPDATE promotion_batches SET
                target_academic_year_id = ?,
                target_class_id = ?,
                target_section_id = ?,
                status = ?,
                requires_inst_admin = ?,
                rejection_reason = ?,
                reviewed_by = ?,
                reviewed_at = ?,
                updated_at = ?
             WHERE id = ?",
            [
                $data['target_academic_year_id'],
                $data['target_class_id'],
                $data['target_section_id'],
                $data['status'],
                $data['requires_inst_admin'] ?? 0,
                $data['rejection_reason'] ?? null,
                $data['reviewed_by'] ?? null,
                $data['reviewed_at'] ?? null,
                $now,
                $id,
            ]
        );
    }

    public static function getIncluded(int $batchId): array
    {
        return Db::selectAll(
            "SELECT s.id, s.first_name, s.last_name, s.enrolment_number,
                    s.academic_year_id, s.onboarding_status
             FROM promotion_batch_students pbs
             JOIN students s ON s.id = pbs.student_id
             WHERE pbs.batch_id = ?
             ORDER BY s.first_name",
            [$batchId]
        );
    }

    public static function getExcluded(int $batchId): array
    {
        return Db::selectAll(
            "SELECT s.id, s.first_name, s.last_name, s.enrolment_number, pe.reason
             FROM promotion_exclusions pe
             JOIN students s ON s.id = pe.student_id
             WHERE pe.batch_id = ?
             ORDER BY s.first_name",
            [$batchId]
        );
    }

    public static function isWindowOpen(): bool
    {
        $row = Db::selectOne("SELECT value FROM settings WHERE `key` = 'promotion_window_open'");
        return ($row['value'] ?? '0') === '1';
    }

    public static function execute(int $batchId, int $approverId): void
    {
        $pdo   = Db::conn();
        $now   = date('Y-m-d H:i:s');
        $batch = self::findById($batchId);

        $pdo->beginTransaction();
        try {
            // Update included students
            $included = self::getIncluded($batchId);
            foreach ($included as $s) {
                Db::execute(
                    'UPDATE students SET academic_year_id = ?, class_id = ?, section_id = ?, updated_at = ? WHERE id = ?',
                    [$batch['target_academic_year_id'], $batch['target_class_id'], $batch['target_section_id'], $now, $s['id']]
                );
                Db::execute(
                    "UPDATE student_profiles
                     SET form_status = 'incomplete', form_completion_pct = 0,
                         form_submitted_at = NULL, last_saved_at = NULL, updated_at = ?
                     WHERE student_id = ?",
                    [$now, $s['id']]
                );
            }
            // Detain excluded students
            $excluded = self::getExcluded($batchId);
            foreach ($excluded as $e) {
                Db::execute(
                    "UPDATE students SET onboarding_status = 'detained', updated_at = ? WHERE id = ?",
                    [$now, $e['id']]
                );
            }
            // Mark batch approved
            Db::execute(
                "UPDATE promotion_batches SET status = 'approved', reviewed_by = ?, reviewed_at = ?, updated_at = ? WHERE id = ?",
                [$approverId, $now, $now, $batchId]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
