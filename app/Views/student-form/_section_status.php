<?php use App\Helpers\View; ?>
<div class="d-flex flex-wrap gap-3 mb-3">
  <?php foreach ($sectionLabels as $num => $label): ?>
    <?php $ok = $summary['sections'][$num] ?? false; ?>
    <span class="badge <?= $ok ? 'bg-success' : 'bg-secondary' ?> fs-6">
      <?= $ok ? '&#10003;' : '&#10007;' ?> <?= View::e($label) ?>
    </span>
  <?php endforeach; ?>
</div>
