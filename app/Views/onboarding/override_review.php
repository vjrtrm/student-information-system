<?php
use App\Helpers\Csrf;
use App\Helpers\View;
$overrides = $data['overrides'] ?? [];
$title = 'Pending Override Requests';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Pending Override Requests</h1>
    <a href="/onboarding" class="btn btn-outline-secondary btn-sm">Back to Students</a>
</div>

<?php if (empty($overrides)): ?>
    <div class="alert alert-success">No pending override requests.</div>
<?php else: ?>
<div class="alert alert-info">
    Review each request. <strong>Approve</strong> to create the student record,
    or <strong>Reject</strong> to discard.
</div>

<?php foreach ($overrides as $or):
    $sd = json_decode($or['student_data'] ?? '{}', true) ?? [];
    $orId = (int)$or['id'];
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Override Request #<?= $orId ?></span>
        <span class="badge bg-warning text-dark"><?= View::e($or['flagged_reason']) ?></span>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-5">
                <h6 class="text-muted">Proposed Student</h6>
                <table class="table table-sm table-bordered mb-0">
                    <tr><th>Name</th><td><?= View::e(($sd['first_name'] ?? '') . ' ' . ($sd['last_name'] ?? '')) ?></td></tr>
                    <tr><th>Mobile</th><td><?= View::e($sd['mobile'] ?? '') ?></td></tr>
                    <tr><th>DOB</th><td><?= View::e($sd['dob'] ?? '') ?></td></tr>
                    <tr><th>Gender</th><td><?= View::e($sd['gender'] ?? '') ?></td></tr>
                    <tr><th>Admission</th><td><?= View::e($sd['admission_date'] ?? '') ?></td></tr>
                </table>
            </div>
            <div class="col-md-4">
                <h6 class="text-muted">Existing Record</h6>
                <table class="table table-sm table-bordered mb-0">
                    <tr><th>Name</th><td><?= View::e($or['ex_first'] . ' ' . $or['ex_last']) ?></td></tr>
                    <tr><th>Mobile</th><td><?= View::e($or['ex_mobile']) ?></td></tr>
                </table>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted">Request Info</h6>
                <p class="small mb-1"><strong>Requested by:</strong> <?= View::e($or['requester_name'] ?? '') ?></p>
                <p class="small mb-1"><strong>Reason:</strong><br><?= nl2br(View::e($or['reason_note'])) ?></p>
                <p class="small mb-0 text-muted"><?= View::e($or['created_at']) ?></p>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success btn-sm"
                    data-bs-toggle="modal" data-bs-target="#approveModal<?= $orId ?>">
                Approve — Create Student
            </button>
            <button type="button" class="btn btn-danger btn-sm"
                    data-bs-toggle="modal" data-bs-target="#rejectModal<?= $orId ?>">
                Reject
            </button>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal<?= $orId ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Approve this override and create the student record for
                <strong><?= View::e(($sd['first_name'] ?? '') . ' ' . ($sd['last_name'] ?? '')) ?></strong>?
            </div>
            <div class="modal-footer">
                <form method="POST" action="/onboarding/overrides/<?= $orId ?>/approve">
                    <?= Csrf::field() ?>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal<?= $orId ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Rejection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Reject this override request for
                <strong><?= View::e(($sd['first_name'] ?? '') . ' ' . ($sd['last_name'] ?? '')) ?></strong>?
                The student will not be created.
            </div>
            <div class="modal-footer">
                <form method="POST" action="/onboarding/overrides/<?= $orId ?>/reject">
                    <?= Csrf::field() ?>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endforeach; ?>
<?php endif; ?>
