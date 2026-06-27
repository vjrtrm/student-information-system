<?php
use App\Helpers\Csrf;
use App\Helpers\View;
?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-6 col-lg-4">
    <div class="card shadow-sm"><div class="card-body p-4">
      <h1 class="h4 mb-3">Enter your code</h1>
      <p class="text-muted">We sent a one-time code to your registered email.</p>
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2"><?= View::e($error) ?></div>
      <?php endif; ?>
      <form method="post" action="/login/otp" novalidate>
        <?= Csrf::field() ?>
        <div class="mb-3">
          <label class="form-label" for="code">6-digit code</label>
          <input class="form-control" id="code" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">Verify</button>
      </form>
      <div class="text-center mt-3"><a href="/login">Back to sign in</a></div>
    </div></div>
  </div>
</div>
