<?php
use App\Helpers\Csrf;
use App\Helpers\View;
/** @var array $departments */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $q */
/** @var string $title */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Departments</h1>
    <a href="/master-data/departments/create" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg me-1" viewBox="0 0 16 16">
            <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
        </svg>
        Add Department
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="/master-data/departments" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label for="q" class="form-label">Search</label>
                <input type="text" id="q" name="q" class="form-control"
                       placeholder="Search by name or code…"
                       value="<?= View::e($q) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if ($q !== ''): ?>
                <a href="/master-data/departments" class="btn btn-outline-secondary ms-1">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (empty($departments)): ?>
<div class="alert alert-info">No departments found.</div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Level</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($departments as $dept): ?>
            <tr>
                <td><?= View::e($dept['name']) ?></td>
                <td><code><?= View::e($dept['code']) ?></code></td>
                <td>
                    <?php if ($dept['level'] === 'UG'): ?>
                        <span class="badge bg-primary">UG</span>
                    <?php else: ?>
                        <span class="badge bg-success">PG</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($dept['status'] === 'active'): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <a href="/master-data/departments/<?= (int)$dept['id'] ?>/edit"
                       class="btn btn-sm btn-outline-primary me-1">Edit</a>

                    <?php if ($dept['status'] === 'active'): ?>
                    <form method="POST"
                          action="/master-data/departments/<?= (int)$dept['id'] ?>/deactivate"
                          class="d-inline"
                          onsubmit="return confirm('Deactivate this department?')">
                        <?= Csrf::field() ?>
                        <button class="btn btn-sm btn-danger">Deactivate</button>
                    </form>
                    <?php else: ?>
                    <form method="POST"
                          action="/master-data/departments/<?= (int)$dept['id'] ?>/reactivate"
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

<?php if ($total > $perPage): ?>
<?php
$totalPages = (int)ceil($total / $perPage);
$qParam = $q !== '' ? '&q=' . urlencode($q) : '';
?>
<nav class="mt-3" aria-label="Departments pagination">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page - 1 . $qParam ?>">Previous</a>
        </li>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $p . $qParam ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page + 1 . $qParam ?>">Next</a>
        </li>
    </ul>
</nav>
<p class="text-center text-muted small">
    Showing <?= count($departments) ?> of <?= $total ?> departments
</p>
<?php endif; ?>
<?php endif; ?>
