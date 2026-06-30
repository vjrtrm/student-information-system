<?php
/** @var array $data */
use App\Helpers\Auth;
use App\Helpers\Csrf;

$staff      = $data['staff']       ?? [];
$departments= $data['departments'] ?? [];
$deptFilter = $data['dept_filter'] ?? null;
$ownId      = (int)Auth::id();

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Staff Management</h4>
    <a href="/staff/create" class="btn btn-primary btn-sm">+ Add Staff</a>
</div>

<?php if (Auth::role() === 'institution_admin' && !empty($departments)): ?>
<form method="GET" action="/staff" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="department_id" class="form-select form-select-sm"
                onchange="this.form.submit()">
            <option value="0">All Departments</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= (int)$dept['id'] ?>"
                    <?= ((int)$deptFilter === (int)$dept['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
    </div>
</form>
<?php endif; ?>

<div class="mb-3">
    <input type="search" id="staffSearch" class="form-control form-control-sm w-25"
           placeholder="Search by name or email&hellip;" aria-label="Search staff">
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="staffTable">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Staff Code</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($staff)): ?>
                    <tr>
                        <td colspan="7" class="text-muted text-center py-3">No staff members found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($staff as $member): ?>
                    <?php $isOwn = (int)$member['id'] === $ownId; ?>
                    <tr class="staff-row">
                        <td>
                            <?= htmlspecialchars($member['name'], ENT_QUOTES) ?>
                            <?php if ($isOwn): ?>
                                <span class="badge bg-primary ms-1">You</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($member['email'], ENT_QUOTES) ?></td>
                        <td>
                            <span class="badge <?= $member['role'] === 'dept_admin' ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                                <?= $member['role'] === 'dept_admin' ? 'Dept Admin' : 'Staff' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $member['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                <?= ucfirst(htmlspecialchars($member['status'], ENT_QUOTES)) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($member['staff_code'] ?? '—', ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars(substr($member['created_at'] ?? '', 0, 10), ENT_QUOTES) ?></td>
                        <td>
                            <?php if (!$isOwn): ?>
                            <a href="/staff/<?= (int)$member['id'] ?>/edit"
                               class="btn btn-xs btn-outline-secondary btn-sm me-1">Edit</a>
                            <a href="/staff/<?= (int)$member['id'] ?>/reset-password"
                               class="btn btn-xs btn-outline-warning btn-sm me-1">Reset PW</a>
                            <form method="POST" action="/staff/<?= (int)$member['id'] ?>/toggle-status"
                                  class="d-inline"
                                  onsubmit="return confirm('Toggle status for <?= htmlspecialchars($member['name'], ENT_QUOTES) ?>?')">
                                <?= Csrf::field() ?>
                                <button type="submit"
                                    class="btn btn-sm <?= $member['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                    <?= $member['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('staffSearch').addEventListener('keyup', function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#staffTable tbody .staff-row').forEach(function (row) {
        var name  = row.cells[0].textContent.toLowerCase();
        var email = row.cells[1].textContent.toLowerCase();
        row.style.display = (name.includes(q) || email.includes(q)) ? '' : 'none';
    });
});
</script>
