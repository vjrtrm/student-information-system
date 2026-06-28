<?php
use App\Helpers\Csrf;
use App\Helpers\View;
$batch = $data['batch'];
$duplicates = $data['duplicates'] ?? [];
$title = 'Review Held Rows';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Review Held Rows — Batch #<?= (int)$batch['id'] ?></h1>
    <a href="/onboarding/result/<?= (int)$batch['id'] ?>" class="btn btn-outline-secondary btn-sm">Back to Result</a>
</div>

<div class="alert alert-warning">
    The following rows were held because they matched an existing student record. For each row,
    choose <strong>Skip</strong> (do not import) or <strong>Override</strong> (request admin approval to create anyway).
    Override requests require a reason note and will be reviewed by the Department Admin.
</div>

<?php if (empty($duplicates)): ?>
    <div class="alert alert-success">No pending duplicate rows for this batch.</div>
<?php else: ?>
<form method="POST" action="/onboarding/duplicates/<?= (int)$batch['id'] ?>">
    <?= Csrf::field() ?>
    <?php foreach ($duplicates as $dup):
        $sd = json_decode($dup['student_data'] ?? '{}', true) ?? [];
        $dupId = (int)$dup['id'];
    ?>
    <div class="card mb-3 border-warning">
        <div class="card-header bg-warning-subtle fw-semibold">
            Row <?= View::e($dup['source_row_number'] ?? '?') ?>
            — Flagged: <span class="badge bg-warning text-dark"><?= View::e($dup['flagged_reason']) ?></span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-muted">New Record (from file)</h6>
                    <table class="table table-sm table-bordered">
                        <tr><th>Name</th><td><?= View::e(($sd['first_name'] ?? '') . ' ' . ($sd['last_name'] ?? '')) ?></td></tr>
                        <tr><th>Mobile</th><td><?= View::e($sd['mobile'] ?? '') ?></td></tr>
                        <tr><th>DOB</th><td><?= View::e($sd['dob'] ?? '') ?></td></tr>
                        <tr><th>Gender</th><td><?= View::e($sd['gender'] ?? '') ?></td></tr>
                        <tr><th>Admission Date</th><td><?= View::e($sd['admission_date'] ?? '') ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted">Existing Record</h6>
                    <table class="table table-sm table-bordered">
                        <tr><th>Name</th><td><?= View::e($dup['ex_first'] . ' ' . $dup['ex_last']) ?></td></tr>
                        <tr><th>Mobile</th><td><?= View::e($dup['ex_mobile']) ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="mt-2">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="resolution[<?= $dupId ?>]"
                           id="skip_<?= $dupId ?>" value="skip" checked>
                    <label class="form-check-label" for="skip_<?= $dupId ?>">Skip (do not import)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="resolution[<?= $dupId ?>]"
                           id="override_<?= $dupId ?>" value="override"
                           onchange="toggleReason(<?= $dupId ?>, this.value)">
                    <label class="form-check-label" for="override_<?= $dupId ?>">Override (request admin approval)</label>
                </div>
                <div id="reason_box_<?= $dupId ?>" class="mt-2 d-none">
                    <label for="reason_<?= $dupId ?>" class="form-label form-label-sm">Reason for override <span class="text-danger">*</span></label>
                    <textarea class="form-control form-control-sm" id="reason_<?= $dupId ?>"
                              name="reason[<?= $dupId ?>]" rows="2"
                              placeholder="Explain why this is not a duplicate..."></textarea>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">Save Decisions</button>
        <a href="/onboarding/result/<?= (int)$batch['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
<?php endif; ?>

<script>
function toggleReason(id, val) {
    const box = document.getElementById('reason_box_' + id);
    if (val === 'override') {
        box.classList.remove('d-none');
        box.querySelector('textarea').required = true;
    } else {
        box.classList.add('d-none');
        box.querySelector('textarea').required = false;
    }
}
// Wire up skip radios too
document.querySelectorAll('input[type=radio][value=skip]').forEach(function(el) {
    el.addEventListener('change', function() {
        const id = this.name.match(/\[(\d+)\]/)[1];
        toggleReason(id, 'skip');
    });
});
document.querySelectorAll('input[type=radio][value=override]').forEach(function(el) {
    el.addEventListener('change', function() {
        const id = this.name.match(/\[(\d+)\]/)[1];
        toggleReason(id, 'override');
    });
});
</script>
