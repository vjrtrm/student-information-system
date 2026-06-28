<?php
namespace App\Helpers;

/**
 * Read-only query helpers for M8 Dashboard views.
 * All methods use prepared statements and compute timestamps in PHP.
 */
class DashboardQuery
{
    // -------------------------------------------------------------------------
    // Student-facing
    // -------------------------------------------------------------------------

    /**
     * Return onboarding_status, enrolment_number, enrolment_serial, and
     * form_status for a given student.  form_status is null when no profile row
     * exists yet.
     */
    public static function studentSummary(int $studentId): array
    {
        $row = Db::selectOne(
            "SELECT s.onboarding_status, s.enrolment_number, s.enrolment_serial,
                    sp.form_status
             FROM students s
             LEFT JOIN student_profiles sp ON sp.student_id = s.id
             WHERE s.id = ?",
            [$studentId]
        );
        return $row ?? [
            'onboarding_status' => null,
            'enrolment_number'  => null,
            'enrolment_serial'  => null,
            'form_status'       => null,
        ];
    }

    /**
     * Return the latest pending RTC row for a student, or null.
     */
    public static function pendingRtc(int $studentId): ?array
    {
        return Db::selectOne(
            "SELECT * FROM change_requests
             WHERE student_id = ? AND status = 'pending'
             ORDER BY created_at DESC LIMIT 1",
            [$studentId]
        );
    }

    /**
     * Count notification_events for a student within the last $days days.
     */
    public static function recentNotifications(int $studentId, int $days): int
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $row = Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM notification_events
             WHERE student_id = ? AND created_at >= ?",
            [$studentId, $since]
        );
        return (int)($row['cnt'] ?? 0);
    }

    // -------------------------------------------------------------------------
    // Staff / Dept Admin
    // -------------------------------------------------------------------------

    /**
     * Returns three queue counts for a department.
     */
    public static function queueCounts(int $deptId): array
    {
        $pendingApprovals = (int)(Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM students
             WHERE department_id = ? AND onboarding_status = 'form_submitted'",
            [$deptId]
        )['cnt'] ?? 0);

        $pendingRtcs = (int)(Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM change_requests
             WHERE department_id = ? AND status = 'pending'",
            [$deptId]
        )['cnt'] ?? 0);

        $pendingEnrolments = (int)(Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM students
             WHERE department_id = ? AND enrolment_approval_status = 'pending'",
            [$deptId]
        )['cnt'] ?? 0);

        return [
            'pending_approvals'  => $pendingApprovals,
            'pending_rtcs'       => $pendingRtcs,
            'pending_enrolments' => $pendingEnrolments,
        ];
    }

    /**
     * Count unsent notification events for a department.
     */
    public static function unsentNotifications(int $deptId): int
    {
        $row = Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM notification_events ne
             JOIN students s ON s.id = ne.student_id
             WHERE ne.sent_at IS NULL AND s.department_id = ?",
            [$deptId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Summary counts for a single department, optionally filtered by academic year.
     */
    public static function deptSummary(int $deptId, ?int $ayId = null): array
    {
        $params = [$deptId];
        $ayClause = '';
        if ($ayId !== null) {
            $ayClause = ' AND academic_year_id = ?';
            $params[] = $ayId;
        }

        $row = Db::selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN onboarding_status = 'approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN onboarding_status IN ('pending_enrolment','form_incomplete','form_submitted') THEN 1 ELSE 0 END) AS pending_form,
                SUM(CASE WHEN enrolment_approval_status = 'pending' THEN 1 ELSE 0 END) AS pending_enrolment
             FROM students
             WHERE department_id = ?" . $ayClause,
            $params
        );

        return [
            'total'             => (int)($row['total'] ?? 0),
            'approved'          => (int)($row['approved'] ?? 0),
            'pending_form'      => (int)($row['pending_form'] ?? 0),
            'pending_enrolment' => (int)($row['pending_enrolment'] ?? 0),
        ];
    }

    // -------------------------------------------------------------------------
    // Institution Admin
    // -------------------------------------------------------------------------

    /**
     * Six institution-wide KPIs; optionally scoped by dept and/or academic year.
     */
    public static function institutionKpis(?int $deptId = null, ?int $ayId = null): array
    {
        $params = [];
        $where  = [];
        if ($deptId !== null) { $where[] = 's.department_id = ?'; $params[] = $deptId; }
        if ($ayId   !== null) { $where[] = 's.academic_year_id = ?'; $params[] = $ayId; }
        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $row = Db::selectOne(
            "SELECT
                COUNT(*) AS total_students,
                SUM(CASE WHEN s.onboarding_status = 'approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN s.onboarding_status IN ('pending_enrolment','form_incomplete','form_submitted') THEN 1 ELSE 0 END) AS pending_form,
                SUM(CASE WHEN s.enrolment_approval_status = 'pending' THEN 1 ELSE 0 END) AS pending_enrolment
             FROM students s" . $whereClause,
            $params
        );

        // pending RTCs
        $rtcParams = [];
        $rtcWhere  = ["cr.status = 'pending'"];
        if ($deptId !== null) { $rtcWhere[] = 'cr.department_id = ?'; $rtcParams[] = $deptId; }
        $rtcRow = Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM change_requests cr WHERE " . implode(' AND ', $rtcWhere),
            $rtcParams
        );

        // unsent notifications
        $unsentParams = [];
        $unsentWhere  = ['ne.sent_at IS NULL'];
        if ($deptId !== null) { $unsentWhere[] = 's2.department_id = ?'; $unsentParams[] = $deptId; }
        $unsentRow = Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM notification_events ne
             JOIN students s2 ON s2.id = ne.student_id
             WHERE " . implode(' AND ', $unsentWhere),
            $unsentParams
        );

        return [
            'total_students'       => (int)($row['total_students'] ?? 0),
            'approved'             => (int)($row['approved'] ?? 0),
            'pending_form'         => (int)($row['pending_form'] ?? 0),
            'pending_enrolment'    => (int)($row['pending_enrolment'] ?? 0),
            'pending_rtcs'         => (int)($rtcRow['cnt'] ?? 0),
            'unsent_notifications' => (int)($unsentRow['cnt'] ?? 0),
        ];
    }

    /**
     * Per-department breakdown; optionally filtered by dept and/or academic year.
     */
    public static function deptBreakdown(?int $deptId = null, ?int $ayId = null): array
    {
        $params = [];
        $where  = [];
        if ($deptId !== null) { $where[] = 's.department_id = ?'; $params[] = $deptId; }
        if ($ayId   !== null) { $where[] = 's.academic_year_id = ?'; $params[] = $ayId; }
        $whereClause = $where ? ' AND ' . implode(' AND ', $where) : '';

        return Db::selectAll(
            "SELECT
                d.id AS department_id,
                d.name AS dept_name,
                COUNT(s.id) AS total,
                SUM(CASE WHEN s.onboarding_status = 'approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN s.onboarding_status IN ('pending_enrolment','form_incomplete','form_submitted') THEN 1 ELSE 0 END) AS pending_form,
                SUM(CASE WHEN s.enrolment_approval_status = 'pending' THEN 1 ELSE 0 END) AS pending_enrolment,
                (SELECT COUNT(*) FROM change_requests cr
                 WHERE cr.department_id = d.id AND cr.status = 'pending') AS pending_rtcs
             FROM departments d
             LEFT JOIN students s ON s.department_id = d.id" . $whereClause . "
             WHERE d.status = 'active'
             GROUP BY d.id, d.name
             ORDER BY d.name ASC",
            $params
        );
    }

    /**
     * Last $limit audit entries related to students or change_requests in a dept.
     */
    public static function recentAudit(int $deptId, int $limit): array
    {
        return Db::selectAll(
            "SELECT al.action, al.entity, al.entity_id, al.created_at, al.actor_id
             FROM audit_log al
             WHERE
               (al.entity = 'student' AND al.entity_id IN (
                   SELECT id FROM students WHERE department_id = ?
               ))
               OR
               (al.entity = 'change_request' AND al.entity_id IN (
                   SELECT id FROM change_requests WHERE department_id = ?
               ))
             ORDER BY al.created_at DESC
             LIMIT ?",
            [$deptId, $deptId, $limit]
        );
    }

    // -------------------------------------------------------------------------
    // Chart data
    // -------------------------------------------------------------------------

    public static function enrolmentStatusChartData(?int $deptId = null, ?int $ayId = null): array
    {
        $params = [];
        $where  = [];
        if ($deptId !== null) { $where[] = 'department_id = ?'; $params[] = $deptId; }
        if ($ayId   !== null) { $where[] = 'academic_year_id = ?'; $params[] = $ayId; }
        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $rows = Db::selectAll(
            "SELECT onboarding_status, COUNT(*) AS cnt FROM students" . $whereClause . "
             GROUP BY onboarding_status ORDER BY onboarding_status",
            $params
        );

        $labels = [];
        $counts = [];
        foreach ($rows as $r) {
            $labels[] = $r['onboarding_status'];
            $counts[] = (int)$r['cnt'];
        }
        return ['labels' => $labels, 'counts' => $counts];
    }

    public static function deptComparisonChartData(?int $deptId = null, ?int $ayId = null): array
    {
        $params = [];
        $where  = [];
        if ($deptId !== null) { $where[] = 's.department_id = ?'; $params[] = $deptId; }
        if ($ayId   !== null) { $where[] = 's.academic_year_id = ?'; $params[] = $ayId; }
        $whereClause = $where ? ' AND ' . implode(' AND ', $where) : '';

        $rows = Db::selectAll(
            "SELECT
                d.name AS dept_name,
                SUM(CASE WHEN s.onboarding_status = 'approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN s.onboarding_status IN ('pending_enrolment','form_incomplete','form_submitted') THEN 1 ELSE 0 END) AS pending_form,
                SUM(CASE WHEN s.enrolment_approval_status = 'pending' THEN 1 ELSE 0 END) AS pending_enrolment
             FROM departments d
             LEFT JOIN students s ON s.department_id = d.id" . $whereClause . "
             WHERE d.status = 'active'
             GROUP BY d.id, d.name
             ORDER BY d.name ASC",
            $params
        );

        $labels          = [];
        $approved        = [];
        $pendingForm     = [];
        $pendingEnrolment = [];
        foreach ($rows as $r) {
            $labels[]           = $r['dept_name'];
            $approved[]         = (int)$r['approved'];
            $pendingForm[]      = (int)$r['pending_form'];
            $pendingEnrolment[] = (int)$r['pending_enrolment'];
        }
        return [
            'labels'           => $labels,
            'approved'         => $approved,
            'pending_form'     => $pendingForm,
            'pending_enrolment' => $pendingEnrolment,
        ];
    }

    public static function formCompletionChartData(?int $deptId = null, ?int $ayId = null): array
    {
        $params = [];
        $where  = [];
        if ($deptId !== null) { $where[] = 's.department_id = ?'; $params[] = $deptId; }
        if ($ayId   !== null) { $where[] = 's.academic_year_id = ?'; $params[] = $ayId; }
        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $rows = Db::selectAll(
            "SELECT sp.form_status, COUNT(*) AS cnt
             FROM student_profiles sp
             JOIN students s ON s.id = sp.student_id" . $whereClause . "
             GROUP BY sp.form_status
             ORDER BY sp.form_status",
            $params
        );

        $labels = [];
        $counts = [];
        foreach ($rows as $r) {
            $labels[] = $r['form_status'];
            $counts[] = (int)$r['cnt'];
        }
        return ['labels' => $labels, 'counts' => $counts];
    }

    public static function funnelChartData(int $deptId): array
    {
        $row = Db::selectOne(
            "SELECT
                SUM(CASE WHEN onboarding_status IN ('pending_enrolment','form_incomplete') THEN 1 ELSE 0 END) AS pending_form,
                SUM(CASE WHEN onboarding_status = 'form_submitted' THEN 1 ELSE 0 END) AS submitted,
                SUM(CASE WHEN enrolment_approval_status = 'pending' THEN 1 ELSE 0 END) AS pending_enrolment,
                SUM(CASE WHEN onboarding_status = 'approved' THEN 1 ELSE 0 END) AS approved
             FROM students
             WHERE department_id = ?",
            [$deptId]
        );
        return [
            'labels' => ['Pending Form', 'Submitted', 'Pending Enrolment', 'Approved'],
            'counts' => [
                (int)($row['pending_form'] ?? 0),
                (int)($row['submitted'] ?? 0),
                (int)($row['pending_enrolment'] ?? 0),
                (int)($row['approved'] ?? 0),
            ],
        ];
    }
}
