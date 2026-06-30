<?php $title = 'My Change Requests'; ?>

<h4 class="mb-4">My Change Requests</h4>

<?php if (empty($rtcs)): ?>
    <p class="text-muted">No change requests submitted yet.</p>
<?php else: ?>
    <?php foreach ($rtcs as $rtc): ?>
        <?php
            $bc = match($rtc['status']) { 'pending'=>'bg-warning text-dark','approved'=>'bg-success','rejected'=>'bg-danger',default=>'bg-secondary' };
            $labels = array_column($rtc['proposed_changes'], 'label');
        ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <div>
                    <span class="badge <?= $bc ?> me-2"><?= ucfirst($rtc['status']) ?></span>
                    <small class="text-muted">Raised <?= date('d M Y H:i', strtotime($rtc['created_at'])) ?></small>
                </div>
                <?php if ($rtc['reviewed_at']): ?>
                    <small class="text-muted">Reviewed <?= date('d M Y H:i', strtotime($rtc['reviewed_at'])) ?></small>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Reason:</strong> <?= htmlspecialchars($rtc['reason']) ?></p>
                <p class="mb-1"><strong>Fields requested:</strong> <?= $labels ? htmlspecialchars(implode(', ', $labels)) : '<em class="text-muted">—</em>' ?></p>
                <?php if ($rtc['status'] === 'rejected' && $rtc['rejection_reason']): ?>
                    <p class="mb-0 text-danger"><strong>Rejection reason:</strong> <?= htmlspecialchars($rtc['rejection_reason']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<a href="/student/form/view" class="btn btn-outline-secondary btn-sm mt-2">← Back to Form</a>
