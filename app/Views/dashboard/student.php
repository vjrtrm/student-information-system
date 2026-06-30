<?php
/** @var array $data */
$statusLabels = [
    'pending_enrolment'   => 'Pending Enrolment',
    'form_incomplete'     => 'Form Incomplete',
    'form_submitted'      => 'Form Submitted',
    'enrolment_generated' => 'Enrolment Generated',
    'approved'            => 'Approved',
];
$statusBadge = [
    'pending_enrolment'   => 'warning',
    'form_incomplete'     => 'secondary',
    'form_submitted'      => 'info',
    'enrolment_generated' => 'primary',
    'approved'            => 'success',
];
$formStatusBadge = [
    'incomplete' => 'secondary',
    'complete'   => 'info',
    'submitted'  => 'success',
];

$onboardingStatus = $data['onboarding_status'] ?? '';
$formStatus       = $data['form_status'] ?? null;
$statusLabel      = $statusLabels[$onboardingStatus] ?? ucwords(str_replace('_', ' ', $onboardingStatus));
$badgeColor       = $statusBadge[$onboardingStatus] ?? 'secondary';

ob_start();
?>
<h4 class="mb-4">My Dashboard</h4>

<div class="row g-3">
    <!-- Enrolment Status -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-subtitle text-muted mb-2">Enrolment Status</h6>
                <span class="badge bg-<?= htmlspecialchars($badgeColor, ENT_QUOTES) ?> fs-6">
                    <?= htmlspecialchars($statusLabel, ENT_QUOTES) ?>
                </span>
                <div class="mt-3">
                    <?php if (!empty($data['enrolment_number'])): ?>
                        <p class="mb-0"><strong>Enrolment Number:</strong>
                            <?= htmlspecialchars($data['enrolment_number'], ENT_QUOTES) ?>
                        </p>
                    <?php elseif (!empty($data['enrolment_serial'])): ?>
                        <p class="mb-0 text-muted">
                            Serial #<?= htmlspecialchars((string)$data['enrolment_serial'], ENT_QUOTES) ?> &mdash; Pending release
                        </p>
                    <?php else: ?>
                        <p class="mb-0 text-muted">Enrolment number not yet assigned.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Status -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-subtitle text-muted mb-2">Student Information Form</h6>
                <?php if ($formStatus !== null): ?>
                    <span class="badge bg-<?= htmlspecialchars($formStatusBadge[$formStatus] ?? 'secondary', ENT_QUOTES) ?> fs-6">
                        <?= htmlspecialchars(ucfirst($formStatus), ENT_QUOTES) ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary fs-6">Not started</span>
                <?php endif; ?>
                <div class="mt-3">
                    <a href="/student/form" class="btn btn-sm btn-primary">Open my form</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending RTC -->
    <?php if (!empty($data['pending_rtc'])): ?>
    <div class="col-md-6">
        <div class="card h-100 border-warning">
            <div class="card-body">
                <h6 class="card-subtitle text-muted mb-2">Pending Change Request</h6>
                <span class="badge bg-warning text-dark fs-6">Pending</span>
                <div class="mt-3">
                    <a href="/rtc/history" class="btn btn-sm btn-outline-warning">View my change requests</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Notifications -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-subtitle text-muted mb-2">Recent Notifications</h6>
                <p class="mb-0">
                    <strong><?= (int)($data['recent_notifications'] ?? 0) ?></strong>
                    notification(s) in the last 30 days.
                </p>
            </div>
        </div>
    </div>
</div>
