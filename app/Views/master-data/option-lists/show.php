<?php
use App\Helpers\Csrf;
use App\Helpers\View;
/** @var array $list   — id, list_key, label */
/** @var array $values — id, value, display, sort_order, status */
/** @var string $title */

// Calculate next sort_order default
$maxSort = 0;
foreach ($values as $v) {
    if ((int)$v['sort_order'] > $maxSort) {
        $maxSort = (int)$v['sort_order'];
    }
}
$nextSort = $maxSort + 10;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/master-data/option-lists" class="text-muted small">&larr; Option Lists</a>
        <h1 class="h3 mb-0"><?= View::e($list['label']) ?> — Values</h1>
        <code class="text-muted"><?= View::e($list['list_key']) ?></code>
    </div>
</div>

<?php if (empty($values)): ?>
<div class="alert alert-info">No values yet. Add one below.</div>
<?php else: ?>
<div class="card mb-4">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Display</th>
                    <th>Value (Code)</th>
                    <th class="text-center">Sort Order</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($values as $val): ?>
            <tr>
                <td>
                    <?= View::e($val['display']) ?>
                    <!-- Inline edit form -->
                    <button class="btn btn-link btn-sm py-0 ms-1"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#val-edit-<?= (int)$val['id'] ?>"
                            aria-expanded="false">
                        Edit
                    </button>
                    <div class="collapse mt-2" id="val-edit-<?= (int)$val['id'] ?>">
                        <form method="POST"
                              action="/master-data/option-lists/<?= (int)$list['id'] ?>/values/<?= (int)$val['id'] ?>/edit"
                              class="row g-2 align-items-end">
                            <?= Csrf::field() ?>
                            <div class="col-md-4">
                                <label class="form-label form-label-sm">Display</label>
                                <input type="text" name="display" class="form-control form-control-sm"
                                       value="<?= View::e($val['display']) ?>" maxlength="100" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label form-label-sm">Value (Code)</label>
                                <input type="text" name="value" class="form-control form-control-sm"
                                       value="<?= View::e($val['value']) ?>" maxlength="50" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label form-label-sm">Sort Order</label>
                                <input type="number" name="sort_order" class="form-control form-control-sm"
                                       value="<?= (int)$val['sort_order'] ?>" min="0">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </td>
                <td><code><?= View::e($val['value']) ?></code></td>
                <td class="text-center"><?= (int)$val['sort_order'] ?></td>
                <td>
                    <?php if ($val['status'] === 'active'): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <?php if ($val['status'] === 'active'): ?>
                    <form method="POST"
                          action="/master-data/option-lists/<?= (int)$list['id'] ?>/values/<?= (int)$val['id'] ?>/deactivate"
                          class="d-inline"
                          onsubmit="return confirm('Deactivate this value?')">
                        <?= Csrf::field() ?>
                        <button class="btn btn-sm btn-danger">Deactivate</button>
                    </form>
                    <?php else: ?>
                    <form method="POST"
                          action="/master-data/option-lists/<?= (int)$list['id'] ?>/values/<?= (int)$val['id'] ?>/reactivate"
                          class="d-inline">
                        <?= Csrf::field() ?>
                        <button class="btn btn-sm btn-success">Reactivate</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Add New Value -->
<div class="card" style="max-width: 720px;">
    <div class="card-header fw-semibold">Add New Value</div>
    <div class="card-body">
        <form method="POST"
              action="/master-data/option-lists/<?= (int)$list['id'] ?>/values"
              class="row g-3 align-items-end">
            <?= Csrf::field() ?>
            <div class="col-md-4">
                <label for="new-display" class="form-label">Display Label <span class="text-danger">*</span></label>
                <input type="text" id="new-display" name="display"
                       class="form-control" maxlength="100" required
                       placeholder="e.g. Tamil Nadu">
            </div>
            <div class="col-md-3">
                <label for="new-value" class="form-label">Value / Code <span class="text-danger">*</span></label>
                <input type="text" id="new-value" name="value"
                       class="form-control" maxlength="50" required
                       placeholder="e.g. TN">
            </div>
            <div class="col-md-2">
                <label for="new-sort" class="form-label">Sort Order</label>
                <input type="number" id="new-sort" name="sort_order"
                       class="form-control" min="0"
                       value="<?= $nextSort ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Add Value</button>
            </div>
        </form>
    </div>
</div>
