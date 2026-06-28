<?php $title = 'Notifications Log'; ?>
<?php ob_start(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        Notifications Log
        <?php if ($failedCount > 0 && $role === 'institution_admin'): ?>
            <a href="/notifications/errors" class="ms-2">
                <span class="badge bg-danger" title="Failed send attempts"><?= $failedCount ?> failed</span>
            </a>
        <?php elseif ($failedCount > 0): ?>
            <span class="badge bg-danger ms-2" title="Failed send attempts — contact institution admin"><?= $failedCount ?> failed</span>
        <?php endif; ?>
    </h4>

    <!-- Send Now -->
    <form method="POST" action="/notifications/send">
        <?= \App\Helpers\View::csrfField() ?>
        <?php if ($role === 'institution_admin' && $filterDeptId): ?>
            <input type="hidden" name="department_id" value="<?= (int)$filterDeptId ?>">
        <?php endif; ?>
        <button class="btn btn-primary btn-sm">▶ Send Now</button>
    </form>
</div>

<!-- Filters -->
<form method="GET" action="/notifications" class="row g-2 mb-3 align-items-end">
    <?php if ($role === 'institution_admin' && !empty($departments)): ?>
        <div class="col-auto">
            <select name="department_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($filterDeptId == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="col-auto">
        <select name="event_key" class="form-select form-select-sm">
            <option value="">All Events</option>
            <?php foreach (['submission_approved','rtc_created_by_student','rtc_created_by_staff','rtc_approved','rtc_rejected'] as $ek): ?>
                <option value="<?= $ek ?>" <?= ($filterEventKey === $ek) ? 'selected' : '' ?>><?= $ek ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <select name="recipient_type" class="form-select form-select-sm">
            <option value="">All Recipients</option>
            <option value="student"    <?= ($filterRecipient === 'student')    ? 'selected' : '' ?>>Student</option>
            <option value="dept_admin" <?= ($filterRecipient === 'dept_admin') ? 'selected' : '' ?>>Dept Admin</option>
        </select>
    </div>
    <div class="col-auto">
        <select name="status" class="form-select form-select-sm">
            <option value="all"     <?= ($filterStatus === 'all')     ? 'selected' : '' ?>>All Status</option>
            <option value="sent"    <?= ($filterStatus === 'sent')    ? 'selected' : '' ?>>Sent</option>
            <option value="pending" <?= ($filterStatus === 'pending') ? 'selected' : '' ?>>Pending</option>
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary btn-sm">Filter</button>
    </div>
</form>

<!-- Events table -->
<?php if (empty($events)): ?>
    <p class="text-muted">No notification events found.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Event</th>
                    <th>Recipient</th>
                    <th>Serial</th>
                    <th>Status</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $ev): ?>
                    <tr>
                        <td class="text-muted small"><?= (int)$ev['id'] ?></td>
                        <td><code class="small"><?= htmlspecialchars($ev['event_key']) ?></code></td>
                        <td>
                            <span class="badge <?= $ev['recipient_type'] === 'student' ? 'bg-info text-dark' : 'bg-secondary' ?>">
                                <?= $ev['recipient_type'] === 'student' ? 'Student' : 'Dept Admin' ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($ev['enrolment_number'] ?? ('S-' . ($ev['enrolment_serial'] ?? '—'))) ?></td>
                        <td>
                            <?php if ($ev['sent_at']): ?>
                                <span class="badge bg-success">Sent</span>
                                <small class="text-muted d-block"><?= date('d M H:i', strtotime($ev['sent_at'])) ?></small>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= date('d M Y H:i', strtotime($ev['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?>&event_key=<?= urlencode($filterEventKey) ?>&recipient_type=<?= urlencode($filterRecipient) ?>&status=<?= urlencode($filterStatus) ?><?= $filterDeptId ? '&department_id=' . $filterDeptId : '' ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <p class="text-muted small">Showing <?= count($events) ?> of <?= $total ?> events</p>
    <?php endif; ?>
<?php endif; ?>

<?php $content = ob_get_clean(); ?>
<?php require dirname(__DIR__) . '/layouts/app.php'; ?>
