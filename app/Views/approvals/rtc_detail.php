<?php $title = 'Change Request #' . $rtc['id']; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Change Request #<?= (int)$rtc['id'] ?></h4>
    <a href="/approvals" class="btn btn-outline-secondary btn-sm">← Back to Queue</a>
</div>

<div class="card mb-4">
    <div class="card-body row g-3">
        <div class="col-md-4">
            <small class="text-muted d-block">Student</small>
            <strong><?= htmlspecialchars($rtc['student_name']) ?></strong>
        </div>
        <div class="col-md-4">
            <small class="text-muted d-block">Initiator</small>
            <span class="badge <?= $rtc['initiator_type'] === 'student' ? 'bg-info text-dark' : 'bg-secondary' ?>"><?= ucfirst($rtc['initiator_type']) ?></span>
            <?= htmlspecialchars($rtc['initiator_name']) ?>
        </div>
        <div class="col-md-4">
            <small class="text-muted d-block">Status</small>
            <?php $bc = match($rtc['status']) { 'pending'=>'bg-warning text-dark','approved'=>'bg-success','rejected'=>'bg-danger',default=>'bg-secondary' }; ?>
            <span class="badge <?= $bc ?>"><?= ucfirst($rtc['status']) ?></span>
        </div>
        <div class="col-12">
            <small class="text-muted d-block">Reason</small>
            <?= htmlspecialchars($rtc['reason']) ?>
        </div>
        <div class="col-md-4">
            <small class="text-muted d-block">Raised At</small>
            <?= date('d M Y H:i', strtotime($rtc['created_at'])) ?>
        </div>
        <?php if ($rtc['status'] !== 'pending'): ?>
            <div class="col-md-4">
                <small class="text-muted d-block">Reviewed At</small>
                <?= $rtc['reviewed_at'] ? date('d M Y H:i', strtotime($rtc['reviewed_at'])) : '—' ?>
            </div>
            <?php if ($rtc['status'] === 'rejected' && $rtc['rejection_reason']): ?>
                <div class="col-12">
                    <small class="text-muted d-block">Rejection Reason</small>
                    <span class="text-danger"><?= htmlspecialchars($rtc['rejection_reason']) ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<h6 class="mb-2">Proposed Changes</h6>
<div class="table-responsive mb-4">
    <table class="table table-bordered align-middle">
        <thead class="table-light">
            <tr><th>Field</th><th>Current Value</th><th class="text-success">Proposed Value</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rtc['proposed_changes'] as $entry): ?>
                <?php
                $fieldLabel = $entry['label'] ?? $entry['field_key'];
                // Resolve custom field labels
                if (\App\Helpers\FieldRegistry::isCustomKey($entry['field_key'] ?? '')) {
                    $fieldLabel = ($customFieldLabels[$entry['field_key']] ?? $fieldLabel);
                }
                ?>
                <tr>
                    <td><?= htmlspecialchars($fieldLabel) ?></td>
                    <td class="text-muted">
                        <?php if (!empty($entry['is_file'])): ?>
                            <?php if ($entry['current_value']): ?><a href="/<?= htmlspecialchars($entry['current_value']) ?>" target="_blank">Current file</a><?php else: ?><em>None</em><?php endif; ?>
                        <?php else: ?>
                            <?= htmlspecialchars((string)($entry['current_value'] ?? '')) ?: '<em class="text-muted">—</em>' ?>
                        <?php endif; ?>
                    </td>
                    <td class="fw-semibold">
                        <?php if (!empty($entry['is_file'])): ?>
                            <span class="text-success">New file uploaded</span>
                        <?php else: ?>
                            <?= htmlspecialchars((string)($entry['proposed_value'] ?? '')) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($rtc['status'] === 'pending'): ?>
    <div class="d-flex gap-2">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">Approve Changes</button>
        <button class="btn btn-danger"  data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
    </div>

    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Confirm Approval</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">Apply all proposed changes to <strong><?= htmlspecialchars($rtc['student_name']) ?></strong>'s profile?</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="/rtc/<?= (int)$rtc['id'] ?>/approve" class="d-inline">
                    <?= \App\Helpers\Csrf::field() ?>
                    <button class="btn btn-success">Yes, Apply Changes</button>
                </form>
            </div>
        </div></div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="/rtc/<?= (int)$rtc['id'] ?>/reject">
                <?= \App\Helpers\Csrf::field() ?>
                <div class="modal-header"><h5 class="modal-title">Reject Change Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Request</button>
                </div>
            </form>
        </div></div>
    </div>
<?php endif; ?>
