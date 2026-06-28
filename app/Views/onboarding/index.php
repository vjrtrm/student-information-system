<?php
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\View;
$students         = $data['students'] ?? [];
$total            = $data['total'] ?? 0;
$page             = $data['page'] ?? 1;
$pages            = $data['pages'] ?? 1;
$filters          = $data['filters'] ?? [];
$academicYears    = $data['academicYears'] ?? [];
$departments      = $data['departments'] ?? [];
$pendingOverrides = $data['pendingOverrides'] ?? 0;
$role             = $data['role'] ?? '';
$title            = 'Students';

$statusLabels = [
    'pending_enrolment'   => ['label' => 'Pending Enrolment', 'class' => 'bg-secondary'],
    'enrolment_assigned'  => ['label' => 'Enrolment Assigned', 'class' => 'bg-info text-dark'],
    'form_submitted'      => ['label' => 'Form Submitted', 'class' => 'bg-primary'],
    'approved'            => ['label' => 'Approved', 'class' => 'bg-success'],
];

function qstring(array $filters, array $extra = []): string {
    $p = array_merge($filters, $extra);
    return $p ? '?' . http_build_query($p) : '';
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">
        Students
        <?php if ($pendingOverrides > 0): ?>
            <a href="/onboarding/overrides" class="badge bg-warning text-dark text-decoration-none ms-2">
                <?= $pendingOverrides ?> Pending Override<?= $pendingOverrides > 1 ? 's' : '' ?>
            </a>
        <?php endif; ?>
    </h1>
    <?php if (in_array($role, ['staff', 'dept_admin'], true)): ?>
    <div class="d-flex gap-2">
        <a href="/onboarding/upload" class="btn btn-outline-primary btn-sm">Upload Students</a>
        <a href="/onboarding/add" class="btn btn-primary btn-sm">Add Student</a>
    </div>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<form method="GET" action="/onboarding" class="card card-body mb-3 p-2">
    <div class="row g-2 align-items-end">
        <?php if ($role === 'institution_admin'): ?>
        <div class="col-md-2">
            <label class="form-label form-label-sm mb-1">Department</label>
            <select name="department_id" class="form-select form-select-sm">
                <option value="">All Depts</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= (int)$dept['id'] ?>"
                        <?= (int)($filters['department_id'] ?? 0) === (int)$dept['id'] ? 'selected' : '' ?>>
                        <?= View::e($dept['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="col-md-2">
            <label class="form-label form-label-sm mb-1">Status</label>
            <select name="onboarding_status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="pending_enrolment"  <?= ($filters['onboarding_status'] ?? '') === 'pending_enrolment'  ? 'selected' : '' ?>>Pending Enrolment</option>
                <option value="enrolment_assigned" <?= ($filters['onboarding_status'] ?? '') === 'enrolment_assigned' ? 'selected' : '' ?>>Enrolment Assigned</option>
                <option value="form_submitted"     <?= ($filters['onboarding_status'] ?? '') === 'form_submitted'     ? 'selected' : '' ?>>Form Submitted</option>
                <option value="approved"           <?= ($filters['onboarding_status'] ?? '') === 'approved'           ? 'selected' : '' ?>>Approved</option>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label form-label-sm mb-1">Academic Year</label>
            <select name="academic_year_id" class="form-select form-select-sm">
                <option value="">All Years</option>
                <?php foreach ($academicYears as $ay): ?>
                    <option value="<?= (int)$ay['id'] ?>"
                        <?= (int)($filters['academic_year_id'] ?? 0) === (int)$ay['id'] ? 'selected' : '' ?>>
                        <?= View::e($ay['display'] ?? $ay['value']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label form-label-sm mb-1">Search</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Name or mobile..." value="<?= View::e($filters['search'] ?? '') ?>">
        </div>

        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
        </div>
        <div class="col-md-1">
            <a href="/onboarding" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($students)): ?>
            <div class="text-center py-5 text-muted">No students found matching these filters.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Mobile</th>
                        <?php if ($role === 'institution_admin'): ?><th>Department</th><?php endif; ?>
                        <th>Level</th>
                        <th>Acad Year</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s):
                        $statusInfo = $statusLabels[$s['onboarding_status']] ?? ['label' => $s['onboarding_status'], 'class' => 'bg-light text-dark'];
                    ?>
                    <tr>
                        <td><?= View::e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                        <td><?= View::e($s['mobile']) ?></td>
                        <?php if ($role === 'institution_admin'): ?>
                            <td><?= View::e($s['dept_code'] ?? $s['dept_name'] ?? '') ?></td>
                        <?php endif; ?>
                        <td><?= View::e($s['programme_level'] ?? '') ?></td>
                        <td><?= View::e($s['academic_year_id'] ?? '') ?></td>
                        <td><?= View::e($s['class_id'] ?? '') ?></td>
                        <td><span class="badge <?= $statusInfo['class'] ?>"><?= View::e($statusInfo['label']) ?></span></td>
                        <td class="text-muted small"><?= View::e(substr($s['created_at'] ?? '', 0, 10)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($pages > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="/onboarding<?= qstring($filters, ['page' => $p]) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <div class="text-center text-muted small mt-1"><?= $total ?> student(s) total</div>
    </div>
    <?php endif; ?>
</div>
