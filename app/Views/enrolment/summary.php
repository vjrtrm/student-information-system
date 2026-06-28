<?php
use App\Helpers\View;
$title = 'Enrolment Summary';
$currentAy = (int)($ayId ?? 0);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Enrolment Summary by Department</h4>
    <a href="/enrolment" class="btn btn-outline-secondary btn-sm">← Back to Batches</a>
</div>

<!-- AY Filter -->
<form method="GET" action="/enrolment/summary" class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label mb-1 small fw-semibold">Academic Year</label>
                <select name="academic_year_id" class="form-select form-select-sm">
                    <option value="">All Years</option>
                    <?php foreach ($academicYears as $ay): ?>
                        <option value="<?= View::e($ay['id']) ?>"
                            <?= ($currentAy === (int)$ay['id']) ? 'selected' : '' ?>>
                            <?= View::e($ay['display']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
        </div>
    </div>
</form>

<?php if (empty($summary)): ?>
<div class="alert alert-info">No enrolment data found<?= $currentAy ? ' for the selected academic year' : '' ?>.</div>
<?php else: ?>

<!-- Stat cards per department -->
<div class="row g-4 mb-4">
    <?php foreach ($summary as $row): ?>
    <div class="col-sm-6 col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white">
                <strong><?= View::e($row['dept_name']) ?></strong>
            </div>
            <div class="card-body">
                <div class="row text-center g-0">
                    <div class="col-4 border-end">
                        <div class="fs-2 fw-bold text-dark"><?= View::e((int)($row['total_pending'] ?? 0) + (int)($row['total_approved'] ?? 0)) ?></div>
                        <div class="small text-muted">Total</div>
                    </div>
                    <div class="col-4 border-end">
                        <div class="fs-2 fw-bold text-success"><?= View::e((int)($row['total_approved'] ?? 0)) ?></div>
                        <div class="small text-muted">Approved</div>
                    </div>
                    <div class="col-4">
                        <div class="fs-2 fw-bold text-warning"><?= View::e((int)($row['total_pending'] ?? 0)) ?></div>
                        <div class="small text-muted">Pending</div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light small text-muted">
                <?= View::e((int)($row['total_batches'] ?? 0)) ?> batch<?= ((int)($row['total_batches'] ?? 0) !== 1) ? 'es' : '' ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Summary table -->
<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">All Departments</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Department</th>
                        <th class="text-end">Batches</th>
                        <th class="text-end">Total Generated</th>
                        <th class="text-end">Approved</th>
                        <th class="text-end">Pending</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totBatches  = 0;
                    $totApproved = 0;
                    $totPending  = 0;
                    foreach ($summary as $row):
                        $approved = (int)($row['total_approved'] ?? 0);
                        $pending  = (int)($row['total_pending']  ?? 0);
                        $batches  = (int)($row['total_batches']  ?? 0);
                        $totBatches  += $batches;
                        $totApproved += $approved;
                        $totPending  += $pending;
                    ?>
                    <tr>
                        <td><?= View::e($row['dept_name']) ?></td>
                        <td class="text-end"><?= View::e($batches) ?></td>
                        <td class="text-end"><?= View::e($approved + $pending) ?></td>
                        <td class="text-end text-success"><?= View::e($approved) ?></td>
                        <td class="text-end text-warning"><?= View::e($pending) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td>Total</td>
                        <td class="text-end"><?= View::e($totBatches) ?></td>
                        <td class="text-end"><?= View::e($totApproved + $totPending) ?></td>
                        <td class="text-end text-success"><?= View::e($totApproved) ?></td>
                        <td class="text-end text-warning"><?= View::e($totPending) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
