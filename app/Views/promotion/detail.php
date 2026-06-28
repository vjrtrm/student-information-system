<?php use App\Helpers\Auth; use App\Helpers\Csrf; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Promotion Batch #<?= $batch['id'] ?></h4>
    <a href="/promotion" class="btn btn-outline-secondary btn-sm">&larr; Back</a>
</div>

<?php
$statusBadge = match($batch['status']) {
    'approved' => 'success',
    'rejected' => 'danger',
    default    => 'warning',
};
?>

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <small class="text-muted d-block">Status</small>
                <span class="badge bg-<?= $statusBadge ?> fs-6"><?= ucwords(str_replace('_', ' ', $batch['status'])) ?></span>
                <?php if ($batch['requires_inst_admin'] && $batch['status'] === 'pending_approval'): ?>
                    <span class="badge bg-info text-dark ms-1">Institution Admin required</span>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Department</small>
                <?= htmlspecialchars($batch['dept_name']) ?>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Target Year</small>
                <?= htmlspecialchars($batch['target_year_label'] ?? '—') ?>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Target Class</small>
                <?= htmlspecialchars($batch['target_class_label'] ?? '—') ?>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Target Section</small>
                <?= htmlspecialchars($batch['target_section_label'] ?? '—') ?>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Initiated By</small>
                <?= htmlspecialchars($batch['initiated_by_name']) ?>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Created</small>
                <?= substr($batch['created_at'], 0, 10) ?>
            </div>
            <?php if ($batch['reviewed_by_name']): ?>
            <div class="col-md-3">
                <small class="text-muted d-block">Reviewed By</small>
                <?= htmlspecialchars($batch['reviewed_by_name']) ?>
                <span class="text-muted small">(<?= substr($batch['reviewed_at'], 0, 10) ?>)</span>
            </div>
            <?php endif; ?>
            <?php if ($batch['rejection_reason']): ?>
            <div class="col-12">
                <small class="text-muted d-block">Rejection Reason</small>
                <span class="text-danger"><?= htmlspecialchars($batch['rejection_reason']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Approve / Reject actions -->
<?php if ($batch['status'] === 'pending_approval' && in_array(Auth::role(), ['dept_admin', 'institution_admin'], true)): ?>
<?php $canApprove = !(((int)$batch['requires_inst_admin'] === 1) && Auth::role() !== 'institution_admin'); ?>
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning bg-opacity-25"><strong>Review Batch</strong></div>
    <div class="card-body">
        <div class="d-flex gap-3 align-items-start flex-wrap">
            <?php if ($canApprove): ?>
            <form method="POST" action="/promotion/<?= $batch['id'] ?>/approve">
                <?= Csrf::field() ?>
                <button class="btn btn-success" onclick="return confirm('Approve and execute this promotion? This cannot be undone.')">
                    Approve &amp; Execute
                </button>
            </form>
            <?php else: ?>
            <div class="alert alert-info py-2 mb-0">Only Institution Admin can approve resubmitted batches.</div>
            <?php endif; ?>
            <form method="POST" action="/promotion/<?= $batch['id'] ?>/reject" class="d-flex gap-2 align-items-start flex-grow-1">
                <?= Csrf::field() ?>
                <textarea name="rejection_reason" class="form-control form-control-sm" rows="1"
                          placeholder="Rejection reason (required)" required style="min-width:260px"></textarea>
                <button class="btn btn-danger btn-sm">Reject</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Edit & Resubmit for rejected batches -->
<?php if ($batch['status'] === 'rejected' && Auth::role() === 'staff'): ?>
<div class="mb-4">
    <a href="/promotion/<?= $batch['id'] ?>/edit" class="btn btn-outline-primary">Edit &amp; Resubmit</a>
</div>
<?php endif; ?>

<!-- Included students -->
<div class="card mb-4">
    <div class="card-header"><strong>Included Students</strong>
        <span class="badge bg-secondary ms-2"><?= count($included) ?></span>
    </div>
    <?php if (empty($included)): ?>
        <div class="card-body text-muted">No students included.</div>
    <?php else: ?>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr><th>Name</th><th>Enrolment No.</th><th>Current Year</th></tr>
            </thead>
            <tbody>
                <?php foreach ($included as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td><?= htmlspecialchars($s['enrolment_number'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['academic_year_id'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Excluded students -->
<?php if (!empty($excluded)): ?>
<div class="card mb-4">
    <div class="card-header"><strong>Excluded Students</strong>
        <span class="badge bg-danger ms-2"><?= count($excluded) ?></span>
        <?php if ($batch['status'] === 'approved'): ?>
            <small class="text-muted ms-2">(Status set to Detained on approval)</small>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr><th>Name</th><th>Enrolment No.</th><th>Exclusion Reason</th></tr>
            </thead>
            <tbody>
                <?php foreach ($excluded as $e): ?>
                <tr>
                    <td><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></td>
                    <td><?= htmlspecialchars($e['enrolment_number'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($e['reason']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
