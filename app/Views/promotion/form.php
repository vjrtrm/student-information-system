<?php use App\Helpers\Csrf; ?>
<h4 class="mb-4"><?= $mode === 'edit' ? 'Edit Promotion Batch #' . $batch['id'] : 'Create Promotion Batch' ?></h4>

<form method="POST" action="<?= $mode === 'edit' ? '/promotion/' . $batch['id'] . '/edit' : '/promotion/create' ?>">
    <?= Csrf::field() ?>

    <div class="card mb-4">
        <div class="card-header"><strong>Target Values</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Target Academic Year <span class="text-danger">*</span></label>
                <select name="target_academic_year_id" class="form-select" required>
                    <option value="">Select...</option>
                    <?php foreach ($academicYears as $ay): ?>
                        <option value="<?= $ay['id'] ?>"
                            <?= isset($batch) && $batch['target_academic_year_id'] == $ay['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ay['display']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Target Class <span class="text-danger">*</span></label>
                <select name="target_class_id" class="form-select" required>
                    <option value="">Select...</option>
                    <?php foreach ($classes as $cl): ?>
                        <option value="<?= $cl['id'] ?>"
                            <?= isset($batch) && $batch['target_class_id'] == $cl['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['display']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Target Section <span class="text-danger">*</span></label>
                <select name="target_section_id" class="form-select" required>
                    <option value="">Select...</option>
                    <?php foreach ($sections as $sec): ?>
                        <option value="<?= $sec['id'] ?>"
                            <?= isset($batch) && $batch['target_section_id'] == $sec['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sec['display']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Students</strong>
            <small class="text-muted ms-2">Uncheck to exclude. Ineligible students cannot be included.</small>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="40">Include</th>
                        <th>Name</th>
                        <th>Enrolment No.</th>
                        <th>Current Year</th>
                        <th>Form Status</th>
                        <th>Eligible</th>
                        <th>Exclusion Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                    <?php
                        $isChecked = $s['eligible'] && (empty($exclusions) || !isset($exclusions[$s['id']]));
                        if ($mode === 'edit') {
                            $isChecked = in_array($s['id'], $included ?? [], true);
                        }
                        $rowClass = $s['eligible'] ? '' : 'table-secondary text-muted';
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="text-center">
                            <input type="checkbox"
                                   name="included[]"
                                   value="<?= $s['id'] ?>"
                                   class="form-check-input student-check"
                                   data-sid="<?= $s['id'] ?>"
                                   <?= $s['eligible'] ? '' : 'disabled' ?>
                                   <?= ($s['eligible'] && $isChecked) ? 'checked' : '' ?>>
                        </td>
                        <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                        <td><?= htmlspecialchars($s['enrolment_number'] ?? ('Serial #' . ($s['enrolment_serial'] ?? '—'))) ?></td>
                        <td><?= htmlspecialchars($s['academic_year_label'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($s['form_status'] ?? 'incomplete') ?></td>
                        <td>
                            <?php if ($s['eligible']): ?>
                                <span class="text-success">&#10003;</span>
                            <?php else: ?>
                                <span class="text-danger" title="<?= htmlspecialchars($s['ineligible_reason']) ?>">&#10007; <?= htmlspecialchars($s['ineligible_reason']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($s['eligible']): ?>
                                <input type="text"
                                       name="exclusion_reason[<?= $s['id'] ?>]"
                                       id="reason_<?= $s['id'] ?>"
                                       class="form-control form-control-sm"
                                       placeholder="Reason for exclusion"
                                       value="<?= htmlspecialchars($exclusions[$s['id']] ?? '') ?>"
                                       style="display:<?= (!$isChecked) ? 'block' : 'none' ?>">
                            <?php else: ?>
                                <span class="text-muted small"><?= htmlspecialchars($s['ineligible_reason']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <?= $mode === 'edit' ? 'Resubmit for Institution Admin Approval' : 'Submit for Approval' ?>
        </button>
        <a href="/promotion" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
document.querySelectorAll('.student-check').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var reasonField = document.getElementById('reason_' + this.dataset.sid);
        if (reasonField) {
            reasonField.style.display = this.checked ? 'none' : 'block';
            if (this.checked) reasonField.value = '';
        }
    });
});
</script>
