<?php $title = 'Approvals Queue'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Approvals Queue</h4>
    <?php if ($role === 'institution_admin' && !empty($departments)): ?>
        <form method="GET" action="/approvals" class="d-flex gap-2 align-items-center">
            <select name="department_id" class="form-select form-select-sm" style="width:220px" onchange="this.form.submit()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($filterDeptId == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>

<ul class="nav nav-tabs mb-3" id="queueTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-approvals" type="button">
            Pending Approvals <span class="badge bg-danger ms-1"><?= count($pendingStudents) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-rtcs" type="button">
            Pending RTCs <span class="badge bg-warning text-dark ms-1"><?= count($pendingRtcs) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-approvals">
        <?php if (empty($pendingStudents)): ?>
            <p class="text-muted py-3">No pending approvals.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Enrolment No.</th>
                            <?php if ($role === 'institution_admin'): ?><th>Department</th><?php endif; ?>
                            <th>Programme</th>
                            <th>Class</th>
                            <th>Submitted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingStudents as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars(trim($s['first_name'] . ' ' . $s['last_name'])) ?></td>
                                <td><?= htmlspecialchars($s['enrolment_number'] ?? ('S-' . ($s['enrolment_serial'] ?? '—'))) ?></td>
                                <?php if ($role === 'institution_admin'): ?>
                                    <td><?= htmlspecialchars($s['department_name'] ?? '') ?></td>
                                <?php endif; ?>
                                <td><?= htmlspecialchars($s['programme_level'] ?? '') ?></td>
                                <td><?= htmlspecialchars($s['class_name'] ?? '') ?></td>
                                <td><?= $s['form_submitted_at'] ? date('d M Y H:i', strtotime($s['form_submitted_at'])) : '—' ?></td>
                                <td>
                                    <a href="/student/form/<?= (int)$s['id'] ?>/view" class="btn btn-sm btn-outline-primary me-1">View</a>
                                    <form method="POST" action="/approvals/<?= (int)$s['id'] ?>/approve" class="d-inline">
                                        <?= \App\Helpers\View::csrfField() ?>
                                        <button class="btn btn-sm btn-success" onclick="return confirm('Approve submission for <?= htmlspecialchars($s['first_name']) ?>?')">Approve</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="tab-rtcs">
        <?php if (empty($pendingRtcs)): ?>
            <p class="text-muted py-3">No pending change requests.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <?php if ($role === 'institution_admin'): ?><th>Department</th><?php endif; ?>
                            <th>Initiator</th>
                            <th>Reason</th>
                            <th>Raised By</th>
                            <th>Raised At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingRtcs as $rtc): ?>
                            <tr>
                                <td><?= htmlspecialchars($rtc['student_name']) ?></td>
                                <?php if ($role === 'institution_admin'): ?>
                                    <td><?= htmlspecialchars($rtc['department_name'] ?? '') ?></td>
                                <?php endif; ?>
                                <td><span class="badge <?= $rtc['initiator_type'] === 'student' ? 'bg-info text-dark' : 'bg-secondary' ?>"><?= ucfirst($rtc['initiator_type']) ?></span></td>
                                <td><?= htmlspecialchars(mb_strimwidth($rtc['reason'], 0, 60, '…')) ?></td>
                                <td><?= htmlspecialchars($rtc['initiator_name']) ?></td>
                                <td><?= date('d M Y H:i', strtotime($rtc['created_at'])) ?></td>
                                <td>
                                    <a href="/rtc/<?= (int)$rtc['id'] ?>" class="btn btn-sm btn-outline-primary">View RTC</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
