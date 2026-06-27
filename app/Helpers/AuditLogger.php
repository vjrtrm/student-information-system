<?php
namespace App\Helpers;

/** Writes the authentication audit trail. Never stores secrets (Design §D3). */
class AuditLogger
{
    public const EVENTS = [
        'login_success','login_fail','lockout','logout','reset_request','reset_success',
    ];

    public static function log(string $event, ?string $principalType = null, ?int $principalId = null): void
    {
        if (!in_array($event, self::EVENTS, true)) return;
        Db::execute(
            "INSERT INTO auth_audit_log (principal_type, principal_id, event, ip, user_agent, created_at)
             VALUES (?,?,?,?,?,?)",
            [
                $principalType ?: 'unknown',
                $principalId,
                $event,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
                date('Y-m-d H:i:s'),
            ]
        );
    }
}
