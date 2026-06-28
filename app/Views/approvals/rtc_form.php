<?php $title = 'Request a Change'; ?>
<?php ob_start(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Request a Change</h4>
    <small class="text-muted">Student: <strong><?= htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name'])) ?></strong></small>
</div>

<form method="POST" action="/rtc/create" enctype="multipart/form-data">
    <?= \App\Helpers\View::csrfField() ?>
    <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">

    <div class="mb-4">
        <label class="form-label fw-semibold">Reason for Change Request <span class="text-danger">*</span></label>
        <textarea name="reason" class="form-control" rows="3" required placeholder="Describe why these changes are needed…"></textarea>
    </div>

    <h6 class="mb-3">Select Fields to Change</h6>
    <p class="text-muted small">Tick each field you want to change and enter the corrected value.</p>

    <?php
    $sections  = \App\Helpers\FormFieldRules::sectionLabels();
    $bySection = [];
    $fileFields = [];
    foreach ($rules as $f) {
        if (!$f['visible']) continue;
        if (in_array($f['type'], ['file', 'photo'], true)) {
            $fileFields[] = $f;
            continue;
        }
        $bySection[$f['section']][] = $f;
    }
    ?>

    <?php foreach ($bySection as $sec => $fields): ?>
        <div class="card mb-3">
            <div class="card-header py-2 fw-semibold"><?= htmlspecialchars($sections[$sec] ?? "Section {$sec}") ?></div>
            <div class="card-body p-0">
                <?php foreach ($fields as $f): ?>
                    <?php
                    $curRaw = $profile[$f['key']] ?? null;
                    $curVal = is_array($curRaw) ? json_encode($curRaw) : (string)($curRaw ?? '');
                    ?>
                    <div class="border-bottom p-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input field-chk" type="checkbox" id="chk_<?= $f['key'] ?>" data-target="row_<?= $f['key'] ?>">
                            <label class="form-check-label fw-semibold" for="chk_<?= $f['key'] ?>"><?= htmlspecialchars($f['label']) ?></label>
                        </div>
                        <div id="row_<?= $f['key'] ?>" class="row g-2 d-none">
                            <div class="col-md-5">
                                <label class="form-label text-muted small">Current value</label>
                                <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($curVal) ?>" readonly>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small">New value</label>
                                <?php if ($f['type'] === 'textarea'): ?>
                                    <textarea name="fields[<?= $f['key'] ?>]" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($curVal) ?></textarea>
                                <?php elseif ($f['type'] === 'date'): ?>
                                    <input type="date" name="fields[<?= $f['key'] ?>]" class="form-control form-control-sm" value="<?= htmlspecialchars($curVal) ?>">
                                <?php elseif ($f['type'] === 'number'): ?>
                                    <input type="number" name="fields[<?= $f['key'] ?>]" class="form-control form-control-sm" value="<?= htmlspecialchars($curVal) ?>">
                                <?php else: ?>
                                    <input type="text" name="fields[<?= $f['key'] ?>]" class="form-control form-control-sm" value="<?= htmlspecialchars($curVal) ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($customFields ?? [])): ?>
        <div class="card mb-3">
            <div class="card-header py-2 fw-semibold">Custom Fields</div>
            <div class="card-body p-0">
                <?php foreach (($customFields ?? []) as $cf): ?>
                    <?php
                    $cfKey    = 'custom_' . $cf['id'];
                    $curVal   = htmlspecialchars($customData[(int)$cf['id']] ?? '');
                    ?>
                    <div class="border-bottom p-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input field-chk" type="checkbox" id="chk_<?= $cfKey ?>" data-target="row_<?= $cfKey ?>">
                            <label class="form-check-label fw-semibold" for="chk_<?= $cfKey ?>"><?= htmlspecialchars($cf['label']) ?></label>
                        </div>
                        <div id="row_<?= $cfKey ?>" class="row g-2 d-none">
                            <div class="col-md-5">
                                <label class="form-label text-muted small">Current value</label>
                                <input type="text" class="form-control form-control-sm" value="<?= $curVal ?>" readonly>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small">New value</label>
                                <?php if ($cf['field_type'] === 'textarea'): ?>
                                    <textarea name="fields[<?= $cfKey ?>]" class="form-control form-control-sm" rows="2"><?= $curVal ?></textarea>
                                <?php elseif ($cf['field_type'] === 'select'): ?>
                                    <?php $cfOpts = json_decode($cf['options'] ?? '[]', true) ?? []; ?>
                                    <select name="fields[<?= $cfKey ?>]" class="form-select form-select-sm">
                                        <option value="">-- Select --</option>
                                        <?php foreach ($cfOpts as $opt): ?>
                                            <option value="<?= htmlspecialchars($opt) ?>" <?= ($customData[(int)$cf['id']] ?? '') === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($cf['field_type'] === 'date'): ?>
                                    <input type="date" name="fields[<?= $cfKey ?>]" class="form-control form-control-sm" value="<?= $curVal ?>">
                                <?php elseif ($cf['field_type'] === 'number'): ?>
                                    <input type="number" name="fields[<?= $cfKey ?>]" class="form-control form-control-sm" value="<?= $curVal ?>">
                                <?php else: ?>
                                    <input type="text" name="fields[<?= $cfKey ?>]" class="form-control form-control-sm" value="<?= $curVal ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($fileFields)): ?>
        <div class="card mb-4">
            <div class="card-header py-2 fw-semibold">Documents &amp; Photos</div>
            <div class="card-body p-0">
                <?php foreach ($fileFields as $f): ?>
                    <div class="border-bottom p-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input field-chk" type="checkbox" id="chk_<?= $f['key'] ?>" data-target="row_<?= $f['key'] ?>">
                            <label class="form-check-label fw-semibold" for="chk_<?= $f['key'] ?>"><?= htmlspecialchars($f['label']) ?></label>
                            <?php if (!empty($profile[$f['key']])): ?>
                                <a href="/<?= htmlspecialchars($profile[$f['key']]) ?>" target="_blank" class="ms-2 small">View current</a>
                            <?php endif; ?>
                        </div>
                        <div id="row_<?= $f['key'] ?>" class="d-none">
                            <input type="file" name="<?= $f['key'] ?>"
                                   accept="<?= $f['type'] === 'photo' ? 'image/jpeg,image/png' : 'application/pdf,image/jpeg,image/png,image/webp' ?>"
                                   class="form-control form-control-sm">
                            <small class="text-muted"><?= $f['type'] === 'photo' ? 'Image only (JPEG/PNG), max 2 MB' : 'PDF or image, max 2 MB' ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Submit Change Request</button>
        <a href="<?= $role === 'student' ? '/student/form/view' : '/approvals' ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
document.querySelectorAll('.field-chk').forEach(chk => {
    chk.addEventListener('change', () => {
        const el = document.getElementById(chk.dataset.target);
        if (el) el.classList.toggle('d-none', !chk.checked);
    });
});
</script>

<?php $content = ob_get_clean(); ?>
<?php require dirname(__DIR__) . '/layouts/app.php'; ?>
