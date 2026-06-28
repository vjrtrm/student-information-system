<?php
use App\Helpers\Auth;

// Helper: build sort URL
$sortUrl = function(string $col) use ($filters): string {
    $newDir = ($filters['sort'] === $col && $filters['dir'] === 'ASC') ? 'DESC' : 'ASC';
    $p = array_merge($filters, ['sort' => $col, 'dir' => $newDir, 'page' => 1]);
    return '/students?' . http_build_query($p);
};
$sortIcon = function(string $col) use ($filters): string {
    if ($filters['sort'] !== $col) return '<span class="text-muted">⇅</span>';
    return $filters['dir'] === 'ASC' ? '▲' : '▼';
};

// Export link (preserve current filters, drop page/per_page)
$exportFilters = $filters;
unset($exportFilters['page'], $exportFilters['per_page']);
$exportUrl = '/students/export?' . http_build_query($exportFilters);

$showFrom = $total === 0 ? 0 : ($offset + 1);
$showTo   = min($offset + $perPage, $total);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Students</h4>
    <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-success btn-sm">
        &#11015; Export to Excel (.xlsx)
    </a>
</div>

<!-- Stat chips -->
<div class="d-flex gap-3 mb-3">
    <span class="badge bg-secondary fs-6">Total: <?= (int)$chips['total'] ?></span>
    <span class="badge bg-primary fs-6">Submitted: <?= (int)$chips['submitted'] ?></span>
    <span class="badge bg-success fs-6">Approved: <?= (int)$chips['approved'] ?></span>
</div>

<!-- Filter bar -->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer"
         data-bs-toggle="collapse" data-bs-target="#filterBar">
        <span>Filters</span>
        <?php if (!empty($filters['search']) || !empty($filters['dept_id']) || !empty($filters['year_id']) || !empty($filters['prog_level']) || !empty($filters['form_status']) || !empty($filters['enrol_status'])): ?>
            <span class="badge bg-warning text-dark">Active</span>
        <?php endif; ?>
    </div>
    <div class="collapse show" id="filterBar">
        <div class="card-body">
            <form method="GET" action="/students">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($filters['sort']) ?>">
                <input type="hidden" name="dir" value="<?= htmlspecialchars($filters['dir']) ?>">
                <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="Name, enrolment, mobile"
                               value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                    <?php if (Auth::role() === 'institution_admin'): ?>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">Department</label>
                        <select name="dept_id" class="form-select form-select-sm">
                            <option value="">All</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" <?= (int)$filters['dept_id'] === (int)$d['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">Academic Year</label>
                        <select name="year_id" class="form-select form-select-sm">
                            <option value="">All</option>
                            <?php foreach ($academicYears as $ay): ?>
                                <option value="<?= (int)$ay['id'] ?>" <?= (int)$filters['year_id'] === (int)$ay['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ay['display']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">Programme Level</label>
                        <select name="prog_level" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="UG" <?= $filters['prog_level'] === 'UG' ? 'selected' : '' ?>>UG</option>
                            <option value="PG" <?= $filters['prog_level'] === 'PG' ? 'selected' : '' ?>>PG</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">Enrolment Status</label>
                        <select name="enrol_status" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="pending" <?= $filters['enrol_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $filters['enrol_status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="not_generated" <?= $filters['enrol_status'] === 'not_generated' ? 'selected' : '' ?>>Not Generated</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label form-label-sm">Form Status</label>
                        <div class="d-flex gap-3">
                            <?php foreach (['incomplete' => 'Incomplete', 'complete' => 'Complete', 'submitted' => 'Submitted', 'approved' => 'Approved'] as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="form_status[]"
                                           value="<?= $val ?>" id="fs_<?= $val ?>"
                                           <?= in_array($val, $filters['form_status'], true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="fs_<?= $val ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                        <a href="/students" class="btn btn-outline-secondary btn-sm">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Table controls -->
<div class="d-flex justify-content-between align-items-center mb-2">
    <small class="text-muted">
        <?php if ($total === 0): ?>
            No students found.
        <?php else: ?>
            Showing <?= (int)$showFrom ?>&#8211;<?= (int)$showTo ?> of <?= (int)$total ?> students
        <?php endif; ?>
    </small>
    <form method="GET" action="/students" class="d-flex align-items-center gap-2">
        <?php foreach ($filters as $k => $v): ?>
            <?php if ($k === 'per_page') continue; ?>
            <?php if (is_array($v)): foreach ($v as $item): ?>
                <input type="hidden" name="<?= htmlspecialchars($k) ?>[]" value="<?= htmlspecialchars((string)$item) ?>">
            <?php endforeach; else: ?>
                <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <label class="form-label mb-0 me-1 text-muted small">Per page:</label>
        <select name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            <?php foreach ([25, 50, 100] as $pp): ?>
                <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- Data table -->
<?php if (empty($rows)): ?>
    <div class="alert alert-info">No students match the current filters. <a href="/students">Clear filters</a></div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th><a href="<?= htmlspecialchars($sortUrl('enrolment_number')) ?>" class="text-decoration-none text-dark">Enrolment No. <?= $sortIcon('enrolment_number') ?></a></th>
                        <th><a href="<?= htmlspecialchars($sortUrl('name')) ?>" class="text-decoration-none text-dark">Name <?= $sortIcon('name') ?></a></th>
                        <th><a href="<?= htmlspecialchars($sortUrl('programme_level')) ?>" class="text-decoration-none text-dark">Level <?= $sortIcon('programme_level') ?></a></th>
                        <?php if (Auth::role() === 'institution_admin'): ?>
                        <th>Department</th>
                        <?php endif; ?>
                        <th>Academic Year</th>
                        <th><a href="<?= htmlspecialchars($sortUrl('form_status')) ?>" class="text-decoration-none text-dark">Form Status <?= $sortIcon('form_status') ?></a></th>
                        <th>Enrolment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $s): ?>
                    <?php
                        $enrolDisplay = $s['enrolment_number']
                            ?: ($s['enrolment_serial'] ? '#' . $s['enrolment_serial'] : '&#8212;');
                        $fsBadge = match($s['form_status'] ?? '') {
                            'submitted' => 'primary',
                            'approved'  => 'success',
                            'complete'  => 'info',
                            default     => 'secondary',
                        };
                        $fsLabel = $s['form_status'] ? ucfirst($s['form_status']) : 'Incomplete';
                        $enrolBadge = match($s['enrolment_approval_status'] ?? '') {
                            'approved' => 'success',
                            'pending'  => 'warning',
                            default    => 'secondary',
                        };
                        $enrolLabel = $s['enrolment_approval_status']
                            ? ucfirst($s['enrolment_approval_status']) : 'Not Generated';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($enrolDisplay) ?></td>
                        <td>
                            <a href="/student/form/<?= (int)$s['id'] ?>/view">
                                <?= htmlspecialchars(trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''))) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($s['programme_level'] ?? '&#8212;') ?></td>
                        <?php if (Auth::role() === 'institution_admin'): ?>
                        <td><?= htmlspecialchars($s['dept_name'] ?? '&#8212;') ?></td>
                        <?php endif; ?>
                        <td><?= htmlspecialchars($s['academic_year_label'] ?? '&#8212;') ?></td>
                        <td><span class="badge bg-<?= $fsBadge ?>"><?= htmlspecialchars($fsLabel) ?></span></td>
                        <td><span class="badge bg-<?= $enrolBadge ?>"><?= htmlspecialchars($enrolLabel) ?></span></td>
                        <td>
                            <a href="/student/form/<?= (int)$s['id'] ?>/view" class="btn btn-sm btn-outline-secondary">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <?php $prevFilters = array_merge($filters, ['page' => $page - 1]); ?>
            <a class="page-link" href="/students?<?= htmlspecialchars(http_build_query($prevFilters)) ?>">Prev</a>
        </li>
        <?php
        $start = max(1, $page - 2);
        $end   = min($pages, $page + 2);
        for ($p = $start; $p <= $end; $p++):
            $pFilters = array_merge($filters, ['page' => $p]);
        ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="/students?<?= htmlspecialchars(http_build_query($pFilters)) ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
            <?php $nextFilters = array_merge($filters, ['page' => $page + 1]); ?>
            <a class="page-link" href="/students?<?= htmlspecialchars(http_build_query($nextFilters)) ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>
