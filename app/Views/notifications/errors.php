<?php $title = 'Failed Notification Attempts'; ?>
<?php ob_start(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Failed Notification Attempts</h4>
    <a href="/notifications" class="btn btn-outline-secondary btn-sm">← Back to Log</a>
</div>

<?php if (empty($errors)): ?>
    <p class="text-muted">No failed notification attempts.</p>
<?php else: ?>
    <p class="text-muted small mb-3">Showing <?= count($errors) ?> of <?= $total ?> error entries. Events with <code>sent_at = NULL</code> will be retried on the next Send Now.</p>

    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Event ID</th>
                    <th>Event Key</th>
                    <th>Recipient</th>
                    <th>Error</th>
                    <th>Attempted At</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($errors as $err): ?>
                    <tr>
                        <td class="text-muted small"><?= (int)$err['notification_event_id'] ?></td>
                        <td><code class="small"><?= htmlspecialchars($err['event_key']) ?></code></td>
                        <td>
                            <span class="badge <?= $err['recipient_type'] === 'student' ? 'bg-info text-dark' : 'bg-secondary' ?>">
                                <?= $err['recipient_type'] === 'student' ? 'Student' : 'Dept Admin' ?>
                            </span>
                        </td>
                        <td class="small text-danger" title="<?= htmlspecialchars($err['error_message']) ?>">
                            <?= htmlspecialchars(mb_strimwidth($err['error_message'], 0, 200, '…')) ?>
                        </td>
                        <td class="small text-muted"><?= date('d M Y H:i', strtotime($err['attempted_at'])) ?></td>
                        <td>
                            <?php if ($err['sent_at']): ?>
                                <span class="badge bg-success">Sent</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Still Pending</span>
                            <?php endif; ?>
                        </td>
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
                        <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php $content = ob_get_clean(); ?>
<?php require dirname(__DIR__) . '/layouts/app.php'; ?>
