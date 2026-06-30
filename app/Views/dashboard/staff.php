<?php
/** @var array $data */
$queueCounts = $data['queue_counts'] ?? ['pending_approvals' => 0, 'pending_rtcs' => 0, 'pending_enrolments' => 0];
$recentAudit = $data['recent_audit'] ?? [];

ob_start();
?>
<h4 class="mb-4">Staff Dashboard</h4>

<!-- Queue Count Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-5 fw-bold text-primary"><?= (int)$queueCounts['pending_approvals'] ?></div>
                <div class="text-muted mt-1">Pending Approvals</div>
                <a href="/approvals" class="btn btn-sm btn-outline-primary mt-3">View</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-5 fw-bold text-warning"><?= (int)$queueCounts['pending_rtcs'] ?></div>
                <div class="text-muted mt-1">Pending Change Requests</div>
                <a href="/approvals" class="btn btn-sm btn-outline-warning mt-3">View</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-5 fw-bold text-info"><?= (int)$queueCounts['pending_enrolments'] ?></div>
                <div class="text-muted mt-1">Pending Enrolment Approvals</div>
                <a href="/enrolment" class="btn btn-sm btn-outline-info mt-3">View</a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card mb-4">
    <div class="card-header"><strong>Recent Activity</strong></div>
    <div class="card-body p-0">
        <?php if (empty($recentAudit)): ?>
            <p class="text-muted p-3 mb-0">No recent activity.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($recentAudit as $entry): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>
                        <span class="badge bg-secondary me-2"><?= htmlspecialchars($entry['entity'], ENT_QUOTES) ?></span>
                        <?= htmlspecialchars($entry['action'], ENT_QUOTES) ?>
                        <small class="text-muted ms-1">#<?= (int)$entry['entity_id'] ?></small>
                    </span>
                    <small class="text-muted"><?= htmlspecialchars($entry['created_at'] ?? '', ENT_QUOTES) ?></small>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Links -->
<div class="card">
    <div class="card-header"><strong>Quick Links</strong></div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-auto"><a href="/onboarding" class="btn btn-outline-secondary btn-sm">Students</a></div>
            <div class="col-auto"><a href="/approvals" class="btn btn-outline-secondary btn-sm">Approvals</a></div>
            <div class="col-auto"><a href="/enrolment" class="btn btn-outline-secondary btn-sm">Enrolment Numbers</a></div>
            <div class="col-auto"><a href="/notifications" class="btn btn-outline-secondary btn-sm">Notifications</a></div>
        </div>
    </div>
</div>
