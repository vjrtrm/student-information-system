<?php
use App\Helpers\View;
$batch = $data['batch'];
$pendingDuplicates = $data['pendingDuplicates'] ?? [];
$failedRows = $data['failedRows'] ?? [];
$title = 'Upload Result';

$created = (int)$batch['created_count'];
$held    = (int)$batch['duplicate_held_count'];
$failed  = (int)$batch['failed_count'];
$total   = (int)$batch['total_rows'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Upload Result</h1>
    <a href="/onboarding" class="btn btn-outline-secondary btn-sm">Back to Students</a>
</div>

<!-- Summary Banner -->
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="card text-bg-light text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-secondary"><?= $total ?></div>
                <div class="text-muted small">Total Rows</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card text-bg-success text-center border-0 shadow-sm text-white">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= $created ?></div>
                <div class="small">Created</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card text-bg-warning text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= $held ?></div>
                <div class="small">Held (Duplicate)</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card text-bg-danger text-center border-0 shadow-sm text-white">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= $failed ?></div>
                <div class="small">Failed</div>
            </div>
        </div>
    </div>
</div>

<?php if ($held > 0): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center">
    <span><strong><?= $held ?> row(s)</strong> were flagged as potential duplicates and held for review.</span>
    <a href="/onboarding/duplicates/<?= (int)$batch['id'] ?>" class="btn btn-warning btn-sm">Review Held Rows</a>
</div>
<?php endif; ?>

<?php if ($failed > 0): ?>
<div class="accordion mb-3" id="failedAccordion">
    <div class="accordion-item border-danger">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed bg-danger-subtle text-danger fw-semibold" type="button"
                    data-bs-toggle="collapse" data-bs-target="#failedPanel">
                <?= $failed ?> Failed Row(s) — validation errors
            </button>
        </h2>
        <div id="failedPanel" class="accordion-collapse collapse">
            <div class="accordion-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-danger">
                            <tr>
                                <th>Row</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Mobile</th>
                                <th>Errors</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($failedRows as $fr): ?>
                            <tr>
                                <td><?= View::e($fr['row']) ?></td>
                                <td><?= View::e($fr['data']['first_name'] ?? '') ?></td>
                                <td><?= View::e($fr['data']['last_name'] ?? '') ?></td>
                                <td><?= View::e($fr['data']['mobile'] ?? '') ?></td>
                                <td class="text-danger small"><?= View::e(implode('; ', $fr['errors'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-2">
                    <a href="/onboarding/result/<?= (int)$batch['id'] ?>/errors.xlsx" class="btn btn-sm btn-outline-danger">
                        Download Error Report (.xlsx)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($created > 0): ?>
<div class="accordion mb-3" id="successAccordion">
    <div class="accordion-item border-success">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed bg-success-subtle text-success fw-semibold" type="button"
                    data-bs-toggle="collapse" data-bs-target="#successPanel">
                <?= $created ?> Student(s) Created Successfully
            </button>
        </h2>
        <div id="successPanel" class="accordion-collapse collapse">
            <div class="accordion-body">
                <p class="mb-0">These students have been added with <code>onboarding_status = pending_enrolment</code>. Enrolment numbers will be assigned in the next step.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="mt-3">
    <a href="/onboarding/upload" class="btn btn-outline-primary btn-sm">Upload Another File</a>
    <a href="/onboarding" class="btn btn-primary btn-sm ms-2">View All Students</a>
</div>
