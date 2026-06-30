<?php
/** @var array $data */
$queueCounts         = $data['queue_counts']         ?? ['pending_approvals' => 0, 'pending_rtcs' => 0, 'pending_enrolments' => 0];
$unsentNotifications = $data['unsent_notifications'] ?? 0;
$deptSummary         = $data['dept_summary']         ?? ['total' => 0, 'approved' => 0, 'pending_form' => 0, 'pending_enrolment' => 0];
$funnelChart         = $data['funnel_chart']         ?? ['labels' => [], 'counts' => []];
$recentAudit         = $data['recent_audit']         ?? [];

ob_start();
?>
<h4 class="mb-4">Department Dashboard</h4>

<!-- Queue Count Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-5 fw-bold text-primary"><?= (int)$queueCounts['pending_approvals'] ?></div>
                <div class="text-muted mt-1">Pending Approvals</div>
                <a href="/approvals" class="btn btn-sm btn-outline-primary mt-3">View</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-5 fw-bold text-warning"><?= (int)$queueCounts['pending_rtcs'] ?></div>
                <div class="text-muted mt-1">Pending Change Requests</div>
                <a href="/approvals" class="btn btn-sm btn-outline-warning mt-3">View</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-5 fw-bold text-info"><?= (int)$queueCounts['pending_enrolments'] ?></div>
                <div class="text-muted mt-1">Pending Enrolment Approvals</div>
                <a href="/enrolment" class="btn btn-sm btn-outline-info mt-3">View</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100 border-danger">
            <div class="card-body">
                <div class="display-5 fw-bold text-danger"><?= (int)$unsentNotifications ?></div>
                <div class="text-muted mt-1">Unsent Notifications</div>
                <a href="/notifications" class="btn btn-sm btn-outline-danger mt-3">View</a>
            </div>
        </div>
    </div>
</div>

<!-- Department Summary -->
<div class="card mb-4">
    <div class="card-header"><strong>Department Summary</strong></div>
    <div class="card-body">
        <div class="row text-center g-3">
            <div class="col">
                <div class="border rounded p-3">
                    <div class="fs-4 fw-bold"><?= (int)$deptSummary['total'] ?></div>
                    <div class="small text-muted">Total Students</div>
                </div>
            </div>
            <div class="col">
                <div class="border rounded p-3 border-success">
                    <div class="fs-4 fw-bold text-success"><?= (int)$deptSummary['approved'] ?></div>
                    <div class="small text-muted">Approved</div>
                </div>
            </div>
            <div class="col">
                <div class="border rounded p-3 border-warning">
                    <div class="fs-4 fw-bold text-warning"><?= (int)$deptSummary['pending_form'] ?></div>
                    <div class="small text-muted">Pending Form</div>
                </div>
            </div>
            <div class="col">
                <div class="border rounded p-3 border-info">
                    <div class="fs-4 fw-bold text-info"><?= (int)$deptSummary['pending_enrolment'] ?></div>
                    <div class="small text-muted">Pending Enrolment</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Funnel Chart -->
<div class="card mb-4">
    <div class="card-header"><strong>Onboarding Funnel</strong></div>
    <div class="card-body">
        <canvas id="funnelChart" aria-label="Onboarding funnel chart" style="max-height:300px;"></canvas>
        <noscript>
            <table class="table table-sm mt-2">
                <thead><tr><th>Stage</th><th>Count</th></tr></thead>
                <tbody>
                <?php foreach ($funnelChart['labels'] as $i => $label): ?>
                    <tr>
                        <td><?= htmlspecialchars($label, ENT_QUOTES) ?></td>
                        <td><?= (int)($funnelChart['counts'][$i] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </noscript>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    var funnelData = <?= json_encode($funnelChart, JSON_HEX_TAG) ?>;
    var ctx = document.getElementById('funnelChart');
    if (ctx && funnelData) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: funnelData.labels,
                datasets: [{
                    label: 'Students',
                    data: funnelData.counts,
                    backgroundColor: ['#ffc107','#0dcaf0','#0d6efd','#198754'],
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }
})();
</script>
