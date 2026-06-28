<?php
use App\Helpers\Csrf;
// Variables: $fields (all custom fields with dept_name), $title
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Custom Fields</h4>
        <div class="d-flex gap-2">
            <a href="/field-config" class="btn btn-outline-secondary btn-sm">Built-in Field Config</a>
            <a href="/field-config/custom/create" class="btn btn-primary btn-sm">Add Custom Field</a>
        </div>
    </div>

    <?php if (empty($fields)): ?>
        <div class="alert alert-info">No custom fields defined yet. <a href="/field-config/custom/create">Add the first one.</a></div>
    <?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Label</th>
                        <th>Type</th>
                        <th>Section</th>
                        <th>Scope</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $f): ?>
                    <tr>
                        <td><?= htmlspecialchars($f['label']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($f['field_type']) ?></span></td>
                        <td><?= htmlspecialchars($f['section']) ?></td>
                        <td>
                            <?php if ($f['scope'] === 'institution'): ?>
                                <span class="badge bg-primary">Institution</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark"><?= htmlspecialchars($f['dept_name'] ?? 'Unknown dept') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $f['mode'] === 'required' ? 'danger' : ($f['mode'] === 'hidden' ? 'secondary' : 'success') ?>"><?= ucfirst($f['mode']) ?></span></td>
                        <td><span class="badge bg-<?= $f['status'] === 'active' ? 'success' : 'warning text-dark' ?>"><?= ucfirst($f['status']) ?></span></td>
                        <td>
                            <a href="/field-config/custom/<?= (int)$f['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="/field-config/custom/<?= (int)$f['id'] ?>/toggle" class="d-inline">
                                <?= Csrf::field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-<?= $f['status'] === 'active' ? 'warning' : 'success' ?>">
                                    <?= $f['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
