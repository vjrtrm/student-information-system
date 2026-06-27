<?php
use App\Helpers\Csrf;
use App\Helpers\View;
?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-6 col-lg-4">
    <div class="card shadow-sm"><div class="card-body p-4">
      <h1 class="h4 mb-3">Set a new password</h1>
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2"><?= View::e($error) ?></div>
      <?php endif; ?>
      <form method="post" action="/reset-password" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="uid" value="<?= (int)($uid ?? 0) ?>">
        <input type="hidden" name="token" value="<?= View::e($token ?? '') ?>">
        <div class="mb-3">
          <label class="form-label" for="password">New password</label>
          <input class="form-control" id="password" name="password" type="password" required autocomplete="new-password">
          <div class="form-text">At least 8 characters and one number.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="password_confirm">Confirm password</label>
          <input class="form-control" id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary w-100" type="submit">Update password</button>
      </form>
    </div></div>
  </div>
</div>
