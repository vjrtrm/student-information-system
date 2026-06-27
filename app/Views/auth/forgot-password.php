<?php
use App\Helpers\Csrf;
use App\Helpers\View;
?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-6 col-lg-4">
    <div class="card shadow-sm"><div class="card-body p-4">
      <h1 class="h4 mb-3">Forgot password</h1>
      <?php if (!empty($notice)): ?>
        <div class="alert alert-success py-2"><?= View::e($notice) ?></div>
      <?php endif; ?>
      <p class="text-muted">Enter your account email and we'll send a reset link.</p>
      <form method="post" action="/forgot-password" novalidate>
        <?= Csrf::field() ?>
        <div class="mb-3">
          <label class="form-label" for="email">Email</label>
          <input class="form-control" id="email" name="email" type="email" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">Send reset link</button>
      </form>
      <div class="text-center mt-3"><a href="/login">Back to sign in</a></div>
    </div></div>
  </div>
</div>
