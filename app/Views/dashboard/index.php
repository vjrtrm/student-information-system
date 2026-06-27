<?php
use App\Helpers\Csrf;
use App\Helpers\View;
$role = $role ?? 'user';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">Dashboard</h1>
  <form method="post" action="/logout" class="mb-0">
    <?= Csrf::field() ?>
    <button class="btn btn-outline-secondary btn-sm" type="submit">Sign out</button>
  </form>
</div>
<div class="alert alert-info">
  You are signed in as <strong><?= View::e($role) ?></strong>.
  Role-specific dashboards arrive in Module 8.
</div>
