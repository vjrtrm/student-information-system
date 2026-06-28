<?php
use App\Helpers\View;
$byDept        = $data['byDept'] ?? [];
$academicYears = $data['academicYears'] ?? [];
$academicYearId= $data['academicYearId'] ?? null;
$title         = 'Onboarding Summary';

$allStatuses = ['pending_enrolment', 'enrolment_assigned', 'form_submitted', 'approved'];
$statusLabels = [
    'pending_enrolment'  => 'Pending Enrolment',
    'enrolment_assigned' => 'Enrolment Assigned',
    'form_submitted'     => 'Form Submitted',
    'approved'           => 'Approved',
];
$statusClasses = [
    'pending_enrolment'  => 'text-bg-secondary',
    'enrolment_assigned' => 'text-bg-info',
    'form_submitted'     => 'text-bg-primary',
    'approved'           => 'text-bg-success',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Onboarding Summary</h1>
    <a href="/onboarding" class="btn btn-outline-secondary btn-sm">All Students</a>
</div>

<!-- Academic Year Filter -->
<form method="GET" action="/onboarding/summary" class="mb-4 d-flex align-items-end gap-2">
    <div>
        <label class="form-label form-label-sm mb-1">Academic Year</label>
        <select name="academic_year_id" class="form-select form-select-sm">
            <option value="">All Years</option>
            <?php foreach ($academicYears as $ay): ?>
                <option value="<?= (int)$ay['id'] ?>"
                    <?= (int)$academicYearId === (int)$ay['id'] ? 'selected' : '' ?>>
                    <?= View::e($ay['display'] ?? $ay['value']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
    <a href="/onboarding/summary" class="btn btn-outline-secondary btn-sm">Reset</a>
</form>

<?php if (empty($byDept)): ?>
    <div class="alert alert-info">No student data available for the selected period.</div>
<?php else: ?>

<?php foreach ($byDept as $deptId => $dept):
    $statuses = $dept['statuses'];
    $total = array_sum($statuses);
?>
<div class="card mb-4 shadow-sm">
    <div class="card-header fw-semibold"><?= View::e($dept['dept_name']) ?></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6 col-md-2">
                <div class="card text-bg-light text-center border-0">
                    <div class="card-body py-2">
                        <div class="fs-4 fw-bold"><?= $total ?></div>
                        <div class="small text-muted">Total</div>
                    </div>
                </div>
            </div>
            <?php foreach ($allStatuses as $status):
                $count = $statuses[$status] ?? 0;
                $cls = $statusClasses[$status];
                $lbl = $statusLabels[$status];
            ?>
            <div class="col-6 col-md-2">
                <div class="card <?= $cls ?> text-center border-0">
                    <div class="card-body py-2">
                        <div class="fs-4 fw-bold"><?= $count ?></div>
                        <div class="small"><?= $lbl ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>
