<?php
/** @var array $data */
use App\Helpers\Csrf;

$mustChange = $data['must_change'] ?? false;
$errors     = $data['errors']      ?? [];

ob_start();
?>
<h4 class="mb-4">Change Password</h4>

<?php if ($mustChange): ?>
<div class="alert alert-warning">
    <strong>Action required:</strong> You must set a new password before continuing.
</div>
<?php endif; ?>

<div class="card" style="max-width:480px;">
    <div class="card-body">
        <form method="POST" action="/staff/change-password">
            <?= Csrf::field() ?>

            <?php if (!$mustChange): ?>
            <div class="mb-3">
                <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                <input type="password" id="current_password" name="current_password"
                       class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>"
                       autocomplete="current-password" required>
                <?php if (isset($errors['current_password'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['current_password'], ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                <input type="password" id="new_password" name="new_password"
                       class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                       autocomplete="new-password" required minlength="8">
                <?php if (isset($errors['new_password'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['new_password'], ENT_QUOTES) ?></div>
                <?php endif; ?>
                <div class="form-text">Minimum 8 characters.</div>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password"
                       class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                       autocomplete="new-password" required minlength="8">
                <?php if (isset($errors['confirm_password'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['confirm_password'], ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>
</div>
