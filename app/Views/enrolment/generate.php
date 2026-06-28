<?php
use App\Helpers\View;
use App\Helpers\Csrf;
$title = 'Generate Enrolment Numbers';
?>
<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div class="alert alert-<?= View::e($f['type']) ?> alert-dismissible fade show" role="alert">
    <?= View::e($f['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Generate Enrolment Numbers</h4>
    <a href="/enrolment" class="btn btn-outline-secondary btn-sm">← Back to Batches</a>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">New Enrolment Batch</h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            Select an academic year to generate provisional enrolment numbers for all students with
            <strong>Pending Enrolment</strong> status. Numbers are assigned sequentially and held for
            admin approval before becoming visible to students.
        </p>
        <form method="POST" action="/enrolment/generate" id="generateForm">
            <?= Csrf::field() ?>
            <div class="mb-4" style="max-width: 400px;">
                <label for="aySelect" class="form-label fw-semibold">
                    Academic Year <span class="text-danger">*</span>
                </label>
                <select name="academic_year_id" id="aySelect" class="form-select" required>
                    <option value="">— Select Academic Year —</option>
                    <?php foreach ($academicYears as $ay): ?>
                        <option value="<?= View::e($ay['id']) ?>"><?= View::e($ay['display']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="eligibleMsg" class="alert d-none mb-4" style="max-width: 400px;"></div>
            <div class="d-flex gap-2">
                <button type="submit" id="submitBtn" class="btn btn-primary" disabled>
                    Generate Enrolment Numbers
                </button>
                <a href="/enrolment" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('aySelect').addEventListener('change', function () {
    const ayId = this.value;
    const btn  = document.getElementById('submitBtn');
    const msg  = document.getElementById('eligibleMsg');

    if (!ayId) {
        btn.disabled = true;
        msg.className = 'alert d-none';
        msg.textContent = '';
        return;
    }

    msg.className = 'alert alert-secondary mb-4';
    msg.textContent = 'Checking eligible students…';

    fetch('/enrolment/eligible-count?ay_id=' + encodeURIComponent(ayId))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.count === 0) {
                msg.className = 'alert alert-warning mb-4';
                msg.textContent = 'No students with Pending Enrolment status found for this academic year.';
                btn.disabled = true;
            } else {
                msg.className = 'alert alert-success mb-4';
                msg.textContent = data.count + ' student(s) eligible for enrolment number generation.';
                btn.disabled = false;
            }
        })
        .catch(function () {
            msg.className = 'alert alert-danger mb-4';
            msg.textContent = 'Failed to fetch eligible count. Please try again.';
            btn.disabled = true;
        });
});
</script>
