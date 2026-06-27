<?php
namespace App\Helpers;

/**
 * Pure RBAC policy (Design §6). Kept free of HTTP/session so it is fully unit-testable.
 * Middleware consults these functions; it never trusts the client.
 */
class AccessControl
{
    public const ROLES = ['student', 'staff', 'dept_admin', 'institution_admin'];

    /** capability => roles permitted (Module 1 surface; extended by later modules). */
    private const MATRIX = [
        'access_own_student_record'  => ['student'],
        'access_department_students' => ['staff', 'dept_admin', 'institution_admin'],
        'reach_admin_routes'         => ['dept_admin', 'institution_admin'],
        'cross_department_access'    => ['institution_admin'],
        'manage_department_data'     => ['dept_admin', 'institution_admin'],
    ];

    public static function can(?string $role, string $capability): bool
    {
        $allowed = self::MATRIX[$capability] ?? [];
        return $role !== null && in_array($role, $allowed, true);
    }

    /**
     * Can a user acting in $role (scoped to $userDeptId) touch data in $targetDeptId?
     * institution_admin: any department. staff/dept_admin: only their own. students: never (handled elsewhere).
     */
    public static function canAccessDepartment(?string $role, ?int $userDeptId, ?int $targetDeptId): bool
    {
        if ($role === 'institution_admin') return true;
        if (in_array($role, ['staff', 'dept_admin'], true)) {
            return $userDeptId !== null && $userDeptId === $targetDeptId;
        }
        return false;
    }

    /** Whether this role is restricted to a single department's data. */
    public static function isDepartmentScoped(?string $role): bool
    {
        return in_array($role, ['staff', 'dept_admin'], true);
    }
}
