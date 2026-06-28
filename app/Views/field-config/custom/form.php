<?php
use App\Helpers\Csrf;
use App\Helpers\FieldRegistry;
// Variables: $mode ('create'|'edit'), $field (?array), $departments, $sections, $errors, $old, $title
$isEdit = $mode === 'edit';
$v = function($name) use ($old, $field, $isEdit) {
    return $old[$name] ?? ($isEdit && $field ? $field[$name] : '');
};
?>
<div class="container-fluid py-4" style="max-width:720px">
    <h4 class="mb-4"><?= $isEdit ? 'Edit Custom Field' : 'Add Custom Field' ?></h4>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $isEdit ? '/field-config/custom/' . (int)$field['id'] . '/edit' : '/field-config/custom/create' ?>">
        <?= Csrf::field() ?>

        <div class="mb-3">
            <label class="form-label">Label <span class="text-danger">*</span></label>
            <input type="text" name="label" class="form-control <?= isset($errors['label']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($v('label')) ?>" maxlength="150" required>
            <?php if (isset($errors['label'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['label']) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label">Field Type <span class="text-danger">*</span></label>
            <?php if ($isEdit): ?>
                <input type="text" class="form-control" value="<?= htmlspecialchars($field['field_type']) ?>" readonly>
            <?php else: ?>
                <select name="field_type" id="fieldType" class="form-select <?= isset($errors['field_type']) ? 'is-invalid' : '' ?>">
                    <option value="">Select type...</option>
                    <?php foreach (['text','textarea','number','date','select'] as $ft): ?>
                        <option value="<?= $ft ?>" <?= $v('field_type') === $ft ? 'selected' : '' ?>><?= ucfirst($ft) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['field_type'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['field_type']) ?></div><?php endif; ?>
            <?php endif; ?>
        </div>

        <?php
        $showOptions = ($isEdit && $field && $field['field_type'] === 'select') || (!$isEdit && $v('field_type') === 'select');
        ?>
        <div class="mb-3" id="optionsGroup" style="<?= $showOptions ? '' : 'display:none' ?>">
            <label class="form-label">Options <span class="text-danger">*</span> <small class="text-muted">(one per line, minimum 2)</small></label>
            <?php
            $optionsVal = '';
            if ($isEdit && $field && $field['options']) {
                $opts = json_decode($field['options'], true) ?? [];
                $optionsVal = implode("\n", $opts);
            } elseif (!empty($old['options'])) {
                $optionsVal = $old['options'];
            }
            ?>
            <textarea name="options" class="form-control <?= isset($errors['options']) ? 'is-invalid' : '' ?>" rows="4"><?= htmlspecialchars($optionsVal) ?></textarea>
            <?php if (isset($errors['options'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['options']) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label">Section <span class="text-danger">*</span></label>
            <select name="section" class="form-select <?= isset($errors['section']) ? 'is-invalid' : '' ?>" <?= $isEdit ? 'disabled' : '' ?>>
                <option value="">Select section...</option>
                <?php foreach ($sections as $sec): ?>
                    <option value="<?= htmlspecialchars($sec) ?>" <?= $v('section') === $sec ? 'selected' : '' ?>><?= htmlspecialchars($sec) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($isEdit && $field): ?><input type="hidden" name="section" value="<?= htmlspecialchars($field['section']) ?>"><?php endif; ?>
            <?php if (isset($errors['section'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['section']) ?></div><?php endif; ?>
        </div>

        <?php if (!$isEdit): ?>
        <div class="mb-3">
            <label class="form-label">Scope <span class="text-danger">*</span></label>
            <div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="scope" id="scopeInst" value="institution" <?= ($v('scope') !== 'department') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="scopeInst">Institution (all departments)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="scope" id="scopeDept" value="department" <?= ($v('scope') === 'department') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="scopeDept">Department</label>
                </div>
            </div>
            <?php if (isset($errors['scope'])): ?><div class="text-danger small"><?= htmlspecialchars($errors['scope']) ?></div><?php endif; ?>
        </div>

        <div class="mb-3" id="deptSelectorGroup" style="<?= $v('scope') !== 'department' ? 'display:none' : '' ?>">
            <label class="form-label">Department <span class="text-danger">*</span></label>
            <select name="department_id" class="form-select <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>">
                <option value="">Select department...</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= (int)$v('department_id') === (int)$d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['department_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['department_id']) ?></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label">Mode <span class="text-danger">*</span></label>
            <select name="mode" class="form-select <?= isset($errors['mode']) ? 'is-invalid' : '' ?>">
                <?php foreach (['required', 'optional', 'hidden'] as $m): ?>
                    <option value="<?= $m ?>" <?= $v('mode') === $m ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['mode'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['mode']) ?></div><?php endif; ?>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Custom Field' ?></button>
            <a href="/field-config/custom" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
<?php if (!$isEdit): ?>
document.getElementById('fieldType').addEventListener('change', function() {
    document.getElementById('optionsGroup').style.display = this.value === 'select' ? '' : 'none';
});
document.querySelectorAll('input[name="scope"]').forEach(function(r) {
    r.addEventListener('change', function() {
        document.getElementById('deptSelectorGroup').style.display = this.value === 'department' ? '' : 'none';
    });
});
<?php endif; ?>
</script>
