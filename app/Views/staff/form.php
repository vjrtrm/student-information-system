<?php
/** @var array $data */
use App\Helpers\Auth;
use App\Helpers\Csrf;

$mode        = $data['mode']        ?? 'create';
$user        = $data['user']        ?? null;
$departments = $data['departments'] ?? [];
$errors      = $data['errors']      ?? [];
$old         = $data['old']         ?? ($user ?? []);

$isInstAdmin = Auth::role() === 'institution_admin';
$isCreate    = $mode === 'create';
$title       = $isCreate ? 'Add Staff Member' : 'Edit Staff Member';

ob_start();
?>
<h4 class="mb-4"><?= htmlspecialchars($title, ENT_QUOTES) ?></h4>

<div class="card" style="max-width:640px;">
    <div class="card-body">
        <form method="POST" action="<?= $isCreate ? '/staff/create' : '/staff/' . (int)($user['id'] ?? 0) . '/edit' ?>">
            <?= Csrf::field() ?>

            <!-- Name -->
            <div class="mb-3">
                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($old['name'] ?? '', ENT_QUOTES) ?>" required>
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['name'], ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>

            <!-- Email — editable on create, or for inst_admin on edit -->
            <?php if ($isCreate || $isInstAdmin): ?>
            <div class="mb-3">
                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" id="email" name="email"
                       class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES) ?>" required>
                <?php if (isset($errors['email'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['email'], ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?>" readonly disabled>
            </div>
            <?php endif; ?>

            <!-- Password — create only -->
            <?php if ($isCreate): ?>
            <div class="mb-3">
                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" id="password" name="password"
                       class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                       autocomplete="new-password" required minlength="8">
                <?php if (isset($errors['password'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['password'], ENT_QUOTES) ?></div>
                <?php endif; ?>
                <div class="form-text">Minimum 8 characters. User must change on first login.</div>
            </div>
            <?php endif; ?>

            <!-- Staff Code -->
            <div class="mb-3">
                <label for="staff_code" class="form-label">Staff Code</label>
                <input type="text" id="staff_code" name="staff_code" class="form-control"
                       value="<?= htmlspecialchars($old['staff_code'] ?? '', ENT_QUOTES) ?>">
            </div>

            <!-- Role — inst_admin only -->
            <?php if ($isInstAdmin): ?>
            <div class="mb-3">
                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                <select id="role" name="role" class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>">
                    <option value="staff"      <?= ($old['role'] ?? '') === 'staff'      ? 'selected' : '' ?>>Staff</option>
                    <option value="dept_admin" <?= ($old['role'] ?? '') === 'dept_admin' ? 'selected' : '' ?>>Department Admin</option>
                </select>
                <?php if (isset($errors['role'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['role'], ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                <select id="department_id" name="department_id"
                        class="form-select <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>">
                    <option value="">— Select —</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= (int)$dept['id'] ?>"
                            <?= (int)($old['department_id'] ?? 0) === (int)$dept['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['department_id'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['department_id'], ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Status — edit only -->
            <?php if (!$isCreate): ?>
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="active"   <?= ($old['status'] ?? '') === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <?= $isCreate ? 'Create Staff Member' : 'Save Changes' ?>
                </button>
                <a href="/staff" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
