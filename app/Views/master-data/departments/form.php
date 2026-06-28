<?php
use App\Helpers\Csrf;
use App\Helpers\View;
/** @var array|null $dept */
/** @var string $title */
/** @var array $errors */
/** @var bool $codeChangeWarning */
$dept              = $dept ?? null;
$errors            = $errors ?? [];
$codeChangeWarning = $codeChangeWarning ?? false;
$isEdit            = $dept !== null;
$action            = $isEdit
    ? '/master-data/departments/' . (int)$dept['id']
    : '/master-data/departments';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?= View::e($title) ?></h1>
    <a href="/master-data/departments" class="btn btn-outline-secondary btn-sm">
        &larr; Back to Departments
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $err): ?>
        <li><?= View::e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($isEdit && $codeChangeWarning): ?>
<div class="alert alert-warning">
    <strong>Warning:</strong> Changing the department code will affect new enrolment number generation.
    Existing enrolment numbers will <em>not</em> be renamed, but any new ones will use the updated code.
    <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" name="confirm_code_change"
               id="confirm_code_change" value="1">
        <label class="form-check-label" for="confirm_code_change">
            I understand changing the department code affects new enrolment number generation.
        </label>
    </div>
</div>
<?php endif; ?>

<div class="card" style="max-width: 640px;">
    <div class="card-body">
        <form method="POST" action="<?= View::e($action) ?>">
            <?= Csrf::field() ?>

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Department Name <span class="text-danger">*</span></label>
                <input type="text"
                       id="name"
                       name="name"
                       class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                       maxlength="100"
                       required
                       value="<?= View::e($dept['name'] ?? '') ?>">
                <?php if (isset($errors['name'])): ?>
                <div class="invalid-feedback"><?= View::e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="code" class="form-label fw-semibold">Department Code <span class="text-danger">*</span></label>
                <input type="text"
                       id="code"
                       name="code"
                       class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>"
                       maxlength="20"
                       required
                       oninput="this.value=this.value.toUpperCase()"
                       value="<?= View::e($dept['code'] ?? '') ?>">
                <div class="form-text">
                    Used in enrolment number generation (e.g. <code>BCA</code> → <code>24UBCA041</code>).
                    Uppercase only.
                </div>
                <?php if (isset($errors['code'])): ?>
                <div class="invalid-feedback"><?= View::e($errors['code']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label for="level" class="form-label fw-semibold">Level <span class="text-danger">*</span></label>
                <select id="level" name="level"
                        class="form-select <?= isset($errors['level']) ? 'is-invalid' : '' ?>"
                        required>
                    <option value="">-- Select Level --</option>
                    <option value="UG" <?= ($dept['level'] ?? '') === 'UG' ? 'selected' : '' ?>>UG (Under Graduate)</option>
                    <option value="PG" <?= ($dept['level'] ?? '') === 'PG' ? 'selected' : '' ?>>PG (Post Graduate)</option>
                </select>
                <?php if (isset($errors['level'])): ?>
                <div class="invalid-feedback"><?= View::e($errors['level']) ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? 'Update Department' : 'Create Department' ?>
                </button>
                <a href="/master-data/departments" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
