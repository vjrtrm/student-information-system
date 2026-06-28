<?php
use App\Helpers\Csrf;
use App\Helpers\View;
/** @var string $title */
/** @var array|null $result */
$result = $result ?? null;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Import Geography Data</h1>
    <a href="/master-data/geography" class="btn btn-outline-secondary btn-sm">
        &larr; Back to Geography
    </a>
</div>

<div class="card mb-4" style="max-width: 600px;">
    <div class="card-body">
        <p class="text-muted">
            Upload an <strong>.xlsx</strong> file with the following columns:
            <code>State</code>, <code>District</code> (optional), <code>Taluk</code> (optional).
            Each row may have any combination — missing columns are skipped.
            Duplicate entries are skipped automatically.
        </p>

        <form method="POST" action="/master-data/geography/import"
              enctype="multipart/form-data">
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label for="import-file" class="form-label fw-semibold">Select File (.xlsx)</label>
                <input type="file"
                       id="import-file"
                       name="file"
                       class="form-control"
                       accept=".xlsx"
                       required>
                <div class="form-text">Maximum file size: 5 MB. Only .xlsx format accepted.</div>
            </div>
            <button type="submit" class="btn btn-primary">Upload &amp; Import</button>
        </form>
    </div>
</div>

<?php if ($result !== null): ?>
<div class="mb-4">
    <h2 class="h5 mb-3">Import Results</h2>
    <div class="row g-3 mb-4">
        <div class="col-auto">
            <div class="card border-success text-center" style="min-width: 130px;">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-success"><?= (int)($result['created'] ?? 0) ?></div>
                    <div class="text-muted small">Created</div>
                </div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card border-warning text-center" style="min-width: 130px;">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-warning"><?= (int)($result['skipped'] ?? 0) ?></div>
                    <div class="text-muted small">Skipped (duplicates)</div>
                </div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card border-danger text-center" style="min-width: 130px;">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-danger"><?= count($result['importErrors'] ?? []) ?></div>
                    <div class="text-muted small">Errors</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($result['importErrors'])): ?>
    <div class="card border-danger">
        <div class="card-header text-danger fw-semibold">Import Errors</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">Row #</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($result['importErrors'] as $err): ?>
                <tr>
                    <td><?= (int)($err['row'] ?? 0) ?></td>
                    <td><?= View::e($err['message'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
