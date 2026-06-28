<?php
use App\Helpers\View;
/** @var array $lists */
/** @var string $title */
?>
<div class="mb-4">
    <h1 class="h3 mb-1">Option Lists</h1>
    <p class="text-muted mb-0">
        Predefined lists used in student forms. Click a list to manage its values.
        Lists are seeded by the system; individual values can be added, edited, or deactivated.
    </p>
</div>

<?php if (empty($lists)): ?>
<div class="alert alert-info">No option lists found.</div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Label</th>
                    <th>Key</th>
                    <th class="text-center">Active Values</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lists as $list): ?>
            <tr>
                <td><?= View::e($list['label']) ?></td>
                <td><code><?= View::e($list['list_key']) ?></code></td>
                <td class="text-center">
                    <span class="badge bg-primary rounded-pill">
                        <?= (int)($list['value_count'] ?? 0) ?>
                    </span>
                </td>
                <td class="text-end">
                    <a href="/master-data/option-lists/<?= (int)$list['id'] ?>"
                       class="btn btn-sm btn-outline-primary">
                        Manage Values
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
