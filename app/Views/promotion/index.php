<?php use App\Helpers\Auth; use App\Helpers\Csrf; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Student Promotion</h4>
    <?php if (Auth::role() === 'staff' && $windowOpen): ?>
        <a href="/promotion/create" class="btn btn-primary btn-sm">+ Create Promotion Batch</a>
    <?php endif; ?>
</div>

<?php if (Auth::role() === 'institution_admin'): ?>
<div class="alert alert-<?= $windowOpen ? 'success' : 'secondary' ?> d-flex justify-content-between align-items-center py-2">
    <span>Promotion window is currently <strong><?= $windowOpen ? 'OPEN' : 'CLOSED' ?></strong>.</span>
    <form method="POST" action="/promotion/window/toggle" class="m-0">
        <?= Csrf::field() ?>
        <button class="btn btn-sm btn-<?= $windowOpen ? 'warning' : 'success' ?>">
            <?= $windowOpen ? 'Close Window' : 'Open Window' ?>
        </button>
    </form>
</div>
<?php elseif (!$windowOpen): ?>
<div class="alert alert-secondary">Promotion window is currently closed.</div>
<?php endif; ?>

<?php if (empty($batches)): ?>
    <div class="alert alert-info">No promotion batches found.</div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <?php if (Auth::role() === 'institution_admin'): ?><th>Department</th><?php endif; ?>
                    <th>Target Year</th>
                    <th>Status</th>
                    <th>Included</th>
                    <th>Initiated By</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batches as $b): ?>
                <?php
                    $statusBadge = match($b['status']) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'warning',
                    };
                ?>
                <tr>
                    <td><?= $b['id'] ?></td>
                    <?php if (Auth::role() === 'institution_admin'): ?>
                    <td><?= htmlspecialchars($b['dept_name']) ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($b['target_year_label'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= $statusBadge ?>"><?= ucwords(str_replace('_', ' ', $b['status'])) ?></span>
                        <?php if ($b['requires_inst_admin'] && $b['status'] === 'pending_approval'): ?>
                            <span class="badge bg-info text-dark ms-1">Inst. Admin required</span>
                        <?php endif; ?>
                    </td>
                    <td>—</td>
                    <td><?= htmlspecialchars($b['initiated_by_name']) ?></td>
                    <td><?= substr($b['created_at'], 0, 10) ?></td>
                    <td>
                        <a href="/promotion/<?= $b['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
