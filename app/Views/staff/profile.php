<?php
/** @var array $data */
use App\Helpers\Csrf;

$user     = $data['user']      ?? [];
$deptName = $data['dept_name'] ?? null;
$errors   = $data['errors']    ?? [];

ob_start();
?>
<h4 class="mb-4">My Profile</h4>

<div class="card" style="max-width:560px;">
    <div class="card-body">
        <form method="POST" action="/staff/profile">
            <?= Csrf::field() ?>

            <div class="mb-3">
                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name"
                       class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES) ?>" required>
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['name'], ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?>" readonly disabled>
                <div class="form-text">Email cannot be changed here.</div>
            </div>

            <div class="mb-3">
                <label for="staff_code" class="form-label">Staff Code</label>
                <input type="text" id="staff_code" name="staff_code" class="form-control"
                       value="<?= htmlspecialchars($user['staff_code'] ?? '', ENT_QUOTES) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Role</label>
                <input type="text" class="form-control"
                       value="<?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'] ?? '')), ENT_QUOTES) ?>"
                       readonly disabled>
            </div>

            <div class="mb-3">
                <label class="form-label">Department</label>
                <input type="text" class="form-control"
                       value="<?= htmlspecialchars($deptName ?? 'N/A', ENT_QUOTES) ?>"
                       readonly disabled>
            </div>

            <div class="d-flex gap-2 align-items-center">
                <button type="submit" class="btn btn-primary">Save Profile</button>
                <a href="/staff/change-password" class="btn btn-outline-secondary">Change Password</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
