<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Db;
use App\Helpers\NotificationProcessor;
use App\Middleware\RoleMiddleware;
use App\Models\Department;
use App\Models\NotificationErrorLog;

class NotificationController extends Controller
{
    private const ADMIN_ROLES = ['dept_admin', 'institution_admin'];

    // GET /notifications
    public function index(): void
    {
        RoleMiddleware::handle(self::ADMIN_ROLES);
        $role = Auth::role();

        $filterDeptId    = null;
        $filterEventKey  = $_GET['event_key']      ?? '';
        $filterRecipient = $_GET['recipient_type'] ?? '';
        $filterStatus    = $_GET['status']         ?? 'all';
        $page            = max(1, (int)($_GET['page'] ?? 1));
        $perPage         = 50;
        $offset          = ($page - 1) * $perPage;

        if ($role === 'institution_admin' && !empty($_GET['department_id'])) {
            $filterDeptId = (int)$_GET['department_id'];
        }

        [$events, $total] = self::loadEvents(
            $role, $filterDeptId, $filterEventKey, $filterRecipient, $filterStatus, $perPage, $offset
        );

        $deptId      = ($role === 'institution_admin') ? $filterDeptId : Auth::departmentId();
        $failedCount = NotificationErrorLog::countByDept($deptId);
        $departments = ($role === 'institution_admin') ? Department::allActive() : [];
        $pages       = $total > 0 ? (int)ceil($total / $perPage) : 1;

        $this->render('notifications/index', compact(
            'events', 'total', 'page', 'pages', 'perPage',
            'failedCount', 'departments', 'role',
            'filterDeptId', 'filterEventKey', 'filterRecipient', 'filterStatus'
        ));
    }

    // POST /notifications/send
    public function send(): void
    {
        RoleMiddleware::handle(self::ADMIN_ROLES);
        $this->requireCsrf();
        $role = Auth::role();

        if ($role === 'institution_admin') {
            $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        } else {
            $deptId = Auth::departmentId();
        }

        $result = NotificationProcessor::process($deptId);

        $msg = sprintf(
            'Sent %d notification%s. %d skipped (no email on file). %d failed (SMTP error).',
            $result['sent'],
            $result['sent'] === 1 ? '' : 's',
            $result['skipped'],
            $result['failed']
        );
        $type = ($result['failed'] > 0) ? 'warning' : 'success';
        $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
        $this->redirect('/notifications');
    }

    // GET /notifications/errors
    public function errors(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        $errors = NotificationErrorLog::findByDept(null, $perPage, $offset);
        $total  = (int)(Db::selectOne(
            "SELECT COUNT(*) AS cnt FROM notification_error_log", []
        )['cnt'] ?? 0);
        $pages  = $total > 0 ? (int)ceil($total / $perPage) : 1;

        $this->render('notifications/errors', compact('errors', 'total', 'page', 'pages', 'perPage'));
    }

    // ---------------------------------------------------------------
    private static function loadEvents(
        string $role,
        ?int   $filterDeptId,
        string $filterEventKey,
        string $filterRecipient,
        string $filterStatus,
        int    $limit,
        int    $offset
    ): array {
        $params = [];
        $where  = ['1=1'];

        // Dept scope
        if ($role !== 'institution_admin') {
            $where[]  = 's.department_id = ?';
            $params[] = Auth::departmentId();
        } elseif ($filterDeptId !== null) {
            $where[]  = 's.department_id = ?';
            $params[] = $filterDeptId;
        }

        if ($filterEventKey !== '') {
            $where[]  = 'ne.event_key = ?';
            $params[] = $filterEventKey;
        }
        if ($filterRecipient !== '') {
            $where[]  = 'ne.recipient_type = ?';
            $params[] = $filterRecipient;
        }
        if ($filterStatus === 'sent') {
            $where[] = 'ne.sent_at IS NOT NULL';
        } elseif ($filterStatus === 'pending') {
            $where[] = 'ne.sent_at IS NULL';
        }

        $whereClause = implode(' AND ', $where);

        $countRow = Db::selectOne(
            "SELECT COUNT(*) AS cnt
             FROM notification_events ne
             JOIN students s ON s.id = ne.student_id
             WHERE {$whereClause}",
            $params
        );
        $total = (int)($countRow['cnt'] ?? 0);

        $rows = Db::selectAll(
            "SELECT ne.id, ne.event_key, ne.recipient_type, ne.sent_at, ne.created_at,
                    s.enrolment_serial, s.enrolment_number, s.department_id
             FROM notification_events ne
             JOIN students s ON s.id = ne.student_id
             WHERE {$whereClause}
             ORDER BY ne.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        return [$rows, $total];
    }
}
