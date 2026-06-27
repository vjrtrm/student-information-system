<?php
namespace App\Middleware;

use App\Helpers\Auth;
use App\Helpers\AccessControl;
use App\Helpers\View;

/**
 * Enforces department scoping (Design §6). For staff/dept_admin it provides the
 * mandatory department filter; institution_admin bypasses it. Use scopeWhere() when
 * building queries and assertDepartment() before opening a single record.
 */
class DepartmentScopeMiddleware
{
    /** Ensures a scoped user actually has a department on the session. */
    public static function handle(): void
    {
        if (AccessControl::isDepartmentScoped(Auth::role()) && Auth::departmentId() === null) {
            View::render('errors/403', ['title' => 'Forbidden'], 403);
            exit;
        }
    }

    /**
     * Returns [sqlFragment, params] to append to a WHERE clause for the current user.
     * institution_admin -> no restriction; scoped roles -> department_id = ?.
     */
    public static function scopeWhere(string $column = 'department_id'): array
    {
        if (AccessControl::isDepartmentScoped(Auth::role())) {
            return [" AND {$column} = ?", [Auth::departmentId()]];
        }
        return ['', []];
    }

    /** Halts with 403 if the current user may not touch the given department. */
    public static function assertDepartment(?int $targetDeptId): void
    {
        if (!AccessControl::canAccessDepartment(Auth::role(), Auth::departmentId(), $targetDeptId)) {
            View::render('errors/403', ['title' => 'Forbidden'], 403);
            exit;
        }
    }
}
