<?php
use App\Helpers\Csrf;
use App\Helpers\View;
$tab = $tab ?? 'student';
?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-6 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h1 class="h4 mb-3">Sign in</h1>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger py-2"><?= View::e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($notice)): ?>
          <div class="alert alert-success py-2"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-3" role="tablist">
          <li class="nav-item"><button class="nav-link <?= $tab==='student'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#student" type="button">Student</button></li>
          <li class="nav-item"><button class="nav-link <?= $tab==='staff'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#staff" type="button">Staff / Admin</button></li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade <?= $tab==='student'?'show active':'' ?>" id="student">
            <form method="post" action="/login/student" novalidate>
              <?= Csrf::field() ?>
              <div class="mb-3">
                <label class="form-label" for="mobile">Mobile number</label>
                <input class="form-control" id="mobile" name="mobile" inputmode="numeric"
                       pattern="\d{10}" maxlength="10" required autocomplete="username">
              </div>
              <div class="mb-3">
                <label class="form-label" for="dob">Date of birth</label>
                <input class="form-control" id="dob" name="dob" type="date" required>
              </div>
              <button class="btn btn-primary w-100" type="submit">Sign in</button>
            </form>
          </div>

          <div class="tab-pane fade <?= $tab==='staff'?'show active':'' ?>" id="staff">
            <form method="post" action="/login/staff" novalidate>
              <?= Csrf::field() ?>
              <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" required autocomplete="username">
              </div>
              <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" id="password" name="password" type="password" required autocomplete="current-password">
              </div>
              <button class="btn btn-primary w-100" type="submit">Sign in</button>
              <div class="text-center mt-3">
                <a href="/forgot-password">Forgot password?</a>
              </div>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
