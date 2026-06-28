<?php
/** @var array $data */
$kpis                = $data['kpis']                   ?? [];
$deptBreakdown       = $data['dept_breakdown']          ?? [];
$enrolmentChart      = $data['enrolment_status_chart']  ?? ['labels' => [], 'counts' => []];
$deptChart           = $data['dept_comparison_chart']   ?? ['labels' => [], 'approved' => [], 'pending_form' => [], 'pending_enrolment' => []];
$formChart           = $data['form_completion_chart']   ?? ['labels' => [], 'counts' => []];
$ayList              = $data['ay_list']                 ?? [];
$departments         = $data['departments']             ?? [];
$prefs               = $data['prefs']                   ?? [];

ob_start();
?>
<h4 class="mb-4">Institution Dashboard</h4>

<!-- Filter Bar -->
<form method="GET" action="/dashboard" class="row g-2 align-items-end mb-4" id="filterForm">
    <div class="col-auto">
        <label for="pref_academic_year" class="form-label mb-0 small">Academic Year</label>
        <select id="pref_academic_year" name="pref_academic_year" class="form-select form-select-sm"
                onchange="document.getElementById('filterForm').submit()">
            <option value="0">All Years</option>
            <?php foreach ($ayList as $ay): ?>
                <option value="<?= (int)$ay['id'] ?>"
                    <?= ((int)($prefs['academic_year'] ?? 0) === (int)$ay['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ay['display'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <label for="pref_department_id" class="form-label mb-0 small">Department</label>
        <select id="pref_department_id" name="pref_department_id" class="form-select form-select-sm"
                onchange="document.getElementById('filterForm').submit()">
            <option value="0">All Departments</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= (int)$dept['id'] ?>"
                    <?= ((int)($prefs['department_id'] ?? 0) === (int)$dept['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
    </div>
</form>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <?php
    $kpiItems = [
        ['label' => 'Total Students',       'key' => 'total_students',       'color' => 'primary'],
        ['label' => 'Approved',             'key' => 'approved',             'color' => 'success'],
        ['label' => 'Pending Form',         'key' => 'pending_form',         'color' => 'warning'],
        ['label' => 'Pending Enrolment',    'key' => 'pending_enrolment',    'color' => 'info'],
        ['label' => 'Pending RTCs',         'key' => 'pending_rtcs',         'color' => 'danger'],
        ['label' => 'Unsent Notifications', 'key' => 'unsent_notifications', 'color' => 'secondary'],
    ];
    foreach ($kpiItems as $kpi): ?>
    <div class="col-md-4 col-lg-2">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-6 fw-bold text-<?= $kpi['color'] ?>">
                    <?= (int)($kpis[$kpi['key']] ?? 0) ?>
                </div>
                <div class="small text-muted mt-1"><?= htmlspecialchars($kpi['label'], ENT_QUOTES) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Department Breakdown Table -->
<div class="card mb-4">
    <div class="card-header"><strong>Department Breakdown</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Department</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Approved</th>
                        <th class="text-end">Pending Form</th>
                        <th class="text-end">Pending Enrolment</th>
                        <th class="text-end">Pending RTCs</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($deptBreakdown)): ?>
                    <tr><td colspan="6" class="text-muted text-center py-3">No data available.</td></tr>
                <?php else: ?>
                    <?php foreach ($deptBreakdown as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['dept_name'], ENT_QUOTES) ?></td>
                        <td class="text-end"><?= (int)$row['total'] ?></td>
                        <td class="text-end"><?= (int)$row['approved'] ?></td>
                        <td class="text-end"><?= (int)$row['pending_form'] ?></td>
                        <td class="text-end"><?= (int)$row['pending_enrolment'] ?></td>
                        <td class="text-end"><?= (int)$row['pending_rtcs'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Enrolment Status Chart -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><strong>Enrolment Status</strong></div>
            <div class="card-body">
                <canvas id="enrolmentChart" aria-label="Enrolment status chart"></canvas>
                <noscript>
                    <table class="table table-sm mt-2">
                        <thead><tr><th>Status</th><th>Count</th></tr></thead>
                        <tbody>
                        <?php foreach ($enrolmentChart['labels'] as $i => $lbl): ?>
                            <tr>
                                <td><?= htmlspecialchars($lbl, ENT_QUOTES) ?></td>
                                <td><?= (int)($enrolmentChart['counts'][$i] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </noscript>
            </div>
        </div>
    </div>

    <!-- Form Completion Doughnut -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><strong>Form Completion</strong></div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="formChart" aria-label="Form completion chart" style="max-height:280px;"></canvas>
                <noscript>
                    <table class="table table-sm mt-2">
                        <thead><tr><th>Form Status</th><th>Count</th></tr></thead>
                        <tbody>
                        <?php foreach ($formChart['labels'] as $i => $lbl): ?>
                            <tr>
                                <td><?= htmlspecialchars($lbl, ENT_QUOTES) ?></td>
                                <td><?= (int)($formChart['counts'][$i] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </noscript>
            </div>
        </div>
    </div>
</div>

<!-- Department Comparison Chart -->
<div class="card mb-4">
    <div class="card-header"><strong>Department Comparison</strong></div>
    <div class="card-body">
        <canvas id="deptChart" aria-label="Department comparison chart" style="max-height:350px;"></canvas>
        <noscript>
            <table class="table table-sm mt-2">
                <thead>
                    <tr><th>Department</th><th>Approved</th><th>Pending Form</th><th>Pending Enrolment</th></tr>
                </thead>
                <tbody>
                <?php foreach ($deptChart['labels'] as $i => $lbl): ?>
                    <tr>
                        <td><?= htmlspecialchars($lbl, ENT_QUOTES) ?></td>
                        <td><?= (int)($deptChart['approved'][$i] ?? 0) ?></td>
                        <td><?= (int)($deptChart['pending_form'][$i] ?? 0) ?></td>
                        <td><?= (int)($deptChart['pending_enrolment'][$i] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </noscript>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    var enrolmentData   = <?= json_encode($enrolmentChart, JSON_HEX_TAG) ?>;
    var deptData        = <?= json_encode($deptChart,       JSON_HEX_TAG) ?>;
    var formData        = <?= json_encode($formChart,        JSON_HEX_TAG) ?>;

    // Enrolment status — horizontal bar
    var ctx1 = document.getElementById('enrolmentChart');
    if (ctx1) {
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: enrolmentData.labels,
                datasets: [{ label: 'Students', data: enrolmentData.counts, backgroundColor: '#0d6efd' }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    // Department comparison — grouped bar
    var ctx2 = document.getElementById('deptChart');
    if (ctx2) {
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: deptData.labels,
                datasets: [
                    { label: 'Approved',          data: deptData.approved,          backgroundColor: '#198754' },
                    { label: 'Pending Form',       data: deptData.pending_form,      backgroundColor: '#ffc107' },
                    { label: 'Pending Enrolment',  data: deptData.pending_enrolment, backgroundColor: '#0dcaf0' }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    // Form completion — doughnut
    var ctx3 = document.getElementById('formChart');
    if (ctx3) {
        new Chart(ctx3, {
            type: 'doughnut',
            data: {
                labels: formData.labels,
                datasets: [{ data: formData.counts, backgroundColor: ['#6c757d','#0dcaf0','#198754','#0d6efd'] }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
})();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
