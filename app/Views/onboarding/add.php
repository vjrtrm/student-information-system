<?php
use App\Helpers\Csrf;
use App\Helpers\View;
$title      = $data['title'] ?? 'Add Student';
$errors     = $data['errors'] ?? [];
$old        = $data['old'] ?? [];
$academicYears = $data['academicYears'] ?? [];
$classes    = $data['classes'] ?? [];
$sections   = $data['sections'] ?? [];
$dupWarning = $data['dupWarning'] ?? null;
$dupExisting= $data['dupExisting'] ?? null;

function old(string $key, array $old, string $default = ''): string {
    return htmlspecialchars($old[$key] ?? $default, ENT_QUOTES);
}
function hasError(array $errors, string $key): string {
    return isset($errors[$key]) ? ' is-invalid' : '';
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Add Student</h1>
    <a href="/onboarding" class="btn btn-outline-secondary btn-sm">Back to Students</a>
</div>

<?php if ($dupWarning): ?>
<div class="alert alert-warning" id="dupAlert">
    <strong>Duplicate Detected</strong> — A record with the same
    <?= $dupWarning['type'] === 'mobile_exists' ? 'mobile number' :
       ($dupWarning['type'] === 'name_dob_exists' ? 'name and date of birth' : 'mobile number and name/DOB') ?>
    already exists.
    <?php if ($dupExisting): ?>
    <br><small>Existing record: <strong><?= View::e($dupExisting['first_name'] . ' ' . $dupExisting['last_name']) ?></strong>
    (Mobile: <?= View::e($dupExisting['mobile']) ?>)</small>
    <?php endif; ?>
    <hr>
    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="overrideCheck" name="override" value="1"
               onchange="document.getElementById('reasonBox').classList.toggle('d-none', !this.checked)">
        <label class="form-check-label" for="overrideCheck">
            I confirm this is a different student — request admin override
        </label>
    </div>
    <div id="reasonBox" class="d-none">
        <label for="reason_note" class="form-label form-label-sm">Reason <span class="text-danger">*</span></label>
        <textarea class="form-control form-control-sm" name="reason_note" id="reason_note" rows="2"
                  placeholder="Explain why this is a distinct student..."></textarea>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="/onboarding/add" novalidate>
            <?= Csrf::field() ?>

            <div class="row g-3">
                <!-- First Name -->
                <div class="col-md-4">
                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= hasError($errors, 'first_name') ?>"
                           id="first_name" name="first_name" value="<?= old('first_name', $old) ?>" required>
                    <?php if (isset($errors['first_name'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['first_name']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Last Name -->
                <div class="col-md-4">
                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= hasError($errors, 'last_name') ?>"
                           id="last_name" name="last_name" value="<?= old('last_name', $old) ?>" required>
                    <?php if (isset($errors['last_name'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['last_name']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Gender -->
                <div class="col-md-4">
                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                    <select class="form-select<?= hasError($errors, 'gender') ?>" id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="male"   <?= old('gender', $old) === 'male'   ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= old('gender', $old) === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other"  <?= old('gender', $old) === 'other'  ? 'selected' : '' ?>>Other</option>
                    </select>
                    <?php if (isset($errors['gender'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['gender']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Date of Birth -->
                <div class="col-md-4">
                    <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= hasError($errors, 'dob') ?>"
                           id="dob" name="dob" value="<?= old('dob', $old) ?>"
                           placeholder="DD/MM/YYYY" required>
                    <?php if (isset($errors['dob'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['dob']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Mobile -->
                <div class="col-md-4">
                    <label for="mobile" class="form-label">Mobile <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= hasError($errors, 'mobile') ?>"
                           id="mobile" name="mobile" value="<?= old('mobile', $old) ?>"
                           maxlength="10" pattern="\d{10}" required>
                    <?php if (isset($errors['mobile'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['mobile']) ?></div>
                    <?php else: ?>
                        <div class="form-text">10 digits, no spaces.</div>
                    <?php endif; ?>
                </div>

                <!-- Admission Date -->
                <div class="col-md-4">
                    <label for="admission_date" class="form-label">Admission Date <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= hasError($errors, 'admission_date') ?>"
                           id="admission_date" name="admission_date" value="<?= old('admission_date', $old) ?>"
                           placeholder="DD/MM/YYYY" required>
                    <?php if (isset($errors['admission_date'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['admission_date']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Academic Year -->
                <div class="col-md-4">
                    <label for="academic_year_id" class="form-label">Academic Year <span class="text-danger">*</span></label>
                    <select class="form-select<?= hasError($errors, 'academic_year_id') ?>"
                            id="academic_year_id" name="academic_year_id" required>
                        <option value="">Select Year</option>
                        <?php foreach ($academicYears as $ay): ?>
                            <option value="<?= (int)$ay['id'] ?>"
                                <?= (int)($old['academic_year_id'] ?? 0) === (int)$ay['id'] ? 'selected' : '' ?>>
                                <?= View::e($ay['display'] ?? $ay['value']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['academic_year_id'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['academic_year_id']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Class -->
                <div class="col-md-4">
                    <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                    <select class="form-select<?= hasError($errors, 'class_id') ?>"
                            id="class_id" name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= (int)$cls['id'] ?>"
                                <?= (int)($old['class_id'] ?? 0) === (int)$cls['id'] ? 'selected' : '' ?>>
                                <?= View::e($cls['display'] ?? $cls['value']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['class_id'])): ?>
                        <div class="invalid-feedback"><?= View::e($errors['class_id']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Section (optional) -->
                <div class="col-md-4">
                    <label for="section_id" class="form-label">Section <span class="text-muted small">(optional)</span></label>
                    <select class="form-select<?= hasError($errors, 'section_id') ?>"
                            id="section_id" name="section_id">
                        <option value="">None / Any</option>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?= (int)$sec['id'] ?>"
                                <?= (int)($old['section_id'] ?? 0) === (int)$sec['id'] ? 'selected' : '' ?>>
                                <?= View::e($sec['display'] ?? $sec['value']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Student</button>
                <a href="/onboarding" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
