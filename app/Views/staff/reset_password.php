<?php
/** @var array $data */
use App\Helpers\Csrf;

$user   = $data['user']   ?? [];
$errors = $data['errors'] ?? [];

ob_start();
?>
<h4 class="mb-4">Reset Password for <?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES) ?></h4>

<div class="card" style="max-width:480px;">
    <div class="card-body">
        <p class="text-muted small">
            The staff member will be required to set a new password on next login.
        </p>
        <form method="POST" action="/staff/<?= (int)($user['id'] ?? 0) ?>/reset-password">
            <?= Csrf::field() ?>

            <div class="mb-3">
                <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                <input type="password" id="new_password" name="new_password"
                       class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                       autocomplete="new-password" required minlength="8">
                <?php if (isset($errors['new_password'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['new_password'], ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password"
                       class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                       autocomplete="new-password" required minlength="8">
                <?php if (isset($errors['confirm_password'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['confirm_password'], ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning">Reset Password</button>
                <a href="/staff" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
