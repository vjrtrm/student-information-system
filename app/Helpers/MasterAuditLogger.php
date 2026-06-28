<?php
namespace App\Helpers;

/**
 * Audit trail for Module 2 master-data changes (departments, geography, option values).
 * Writes to audit_log (distinct from auth_audit_log used by AuditLogger).
 */
class MasterAuditLogger
{
    public static function log(string $action, string $entity, ?int $entityId, array $details = []): void
    {
        $user = Auth::user();
        Db::execute(
            "INSERT INTO audit_log (actor_id, actor_role, action, entity, entity_id, details, ip, created_at)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                $user['id'] ?? null,
                $user['role'] ?? null,
                $action,
                $entity,
                $entityId,
                $details ? json_encode($details) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                date('Y-m-d H:i:s'),
            ]
        );
    }
}
