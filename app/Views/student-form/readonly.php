<?php
use App\Helpers\View;
$pct       = $profile['form_completion_pct'] ?? 0;
$status    = $profile['form_status'] ?? 'incomplete';
$submitted = $profile['form_submitted_at'] ?? null;

// Helper: render a label/value row
function roRow(string $label, ?string $value): void {
    $v = $value !== null && $value !== '' ? View::e($value) : '<span class="text-muted">—</span>';
    echo "<div class=\"col-md-4 mb-2\"><dt class=\"text-muted small\">{$label}</dt><dd class=\"mb-0 fw-semibold\">{$v}</dd></div>";
}

// Helper: render a document link
function roDoc(string $label, ?string $path): void {
    if (!empty($path)) {
        $link = '<a href="/uploads/' . View::e($path) . '" target="_blank" class="btn btn-sm btn-outline-secondary">View Document</a>';
    } else {
        $link = '<span class="text-muted">—</span>';
    }
    echo "<div class=\"col-md-4 mb-2\"><dt class=\"text-muted small\">{$label}</dt><dd class=\"mb-0\">{$link}</dd></div>";
}

// Helper: render JSON qual block
function roQual(string $label, mixed $val): void {
    $data = is_array($val) ? $val : (is_string($val) ? json_decode($val, true) : null);
    echo "<div class=\"col-12 mb-3\">";
    echo "<dt class=\"text-muted small\">{$label}</dt>";
    if ($data && is_array($data)) {
        echo "<dd><table class=\"table table-sm table-bordered w-auto mb-0\">";
        echo "<tr><th>Exam/Course</th><th>Board/University</th><th>Institution</th><th>Year</th><th>%/CGPA</th><th>Stream</th><th>Medium</th><th>State</th></tr>";
        echo "<tr>";
        foreach (['exam','board','institution','year','percentage','stream','medium','state'] as $k) {
            echo "<td>" . View::e($data[$k] ?? '—') . "</td>";
        }
        echo "</tr></table></dd>";
    } else {
        echo "<dd class=\"text-muted\">—</dd>";
    }
    echo "</div>";
}
?>

<?php if ($isStaff): ?>
  <!-- Staff header -->
  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <h2 class="mb-1"><?= View::e(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?></h2>
      <div class="text-muted">
        <?php if (!empty($student['enrolment_number'])): ?>
          <span class="badge bg-primary me-2"><?= View::e($student['enrolment_number']) ?></span>
        <?php else: ?>
          <span class="badge bg-secondary me-2">Enrolment Pending</span>
        <?php endif; ?>
        Department: <?= View::e($student['department_id'] ?? '—') ?>
      </div>
    </div>
    <div>
      <?php if ($status === 'submitted'): ?>
        <span class="badge bg-success fs-6">&#10003; Submitted <?= $submitted ? '(' . View::e(date('d M Y', strtotime($submitted))) . ')' : '' ?></span>
      <?php else: ?>
        <span class="badge bg-warning text-dark fs-6">Incomplete (<?= $pct ?>%)</span>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <!-- Student header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-1">My Submitted Form</h2>
      <?php if ($status === 'submitted'): ?>
        <span class="badge bg-success fs-6">&#10003; Submitted <?= $submitted ? '(' . View::e(date('d M Y', strtotime($submitted))) . ')' : '' ?></span>
      <?php else: ?>
        <span class="badge bg-warning text-dark fs-6">Incomplete (<?= $pct ?>%)</span>
        <a href="/student/form" class="btn btn-primary btn-sm ms-2">Continue Filling</a>
      <?php endif; ?>
    </div>
    <?php if ($status === 'submitted'): ?>
    <button type="button" class="btn btn-outline-secondary" disabled
            title="Request to Change will be available in a future update">
      Request a Change
    </button>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Section 1: Personal Details -->
<div class="card mb-3">
  <div class="card-header fw-bold">1. Personal Details</div>
  <div class="card-body">
    <dl class="row mb-0">
      <?php roRow('First Name', $student['first_name'] ?? null); ?>
      <?php roRow('Last Name', $student['last_name'] ?? null); ?>
      <?php roRow('Date of Birth', $student['dob'] ?? null); ?>
      <?php roRow('Gender', ucfirst($student['gender'] ?? '')); ?>
      <?php roRow('Mobile', $student['mobile'] ?? null); ?>
      <?php roRow('Blood Group', $profile['blood_group'] ?? null); ?>
      <?php roRow('Mother Tongue', $profile['mother_tongue'] ?? null); ?>
      <?php roRow('Religion', $profile['religion'] ?? null); ?>
      <?php roRow('Caste', $profile['caste'] ?? null); ?>
      <?php roRow('Caste Category', $profile['caste_category'] ?? null); ?>
      <?php roRow('Sub-Caste', $profile['sub_caste'] ?? null); ?>
      <?php roRow('Nationality', $profile['nationality'] ?? null); ?>
      <?php roRow('Place of Birth', $profile['place_of_birth'] ?? null); ?>
      <div class="col-md-4 mb-2">
        <dt class="text-muted small">Aadhaar Number</dt>
        <dd class="mb-0 fw-semibold"><?= View::maskAadhaar($profile['aadhaar_number'] ?? null) ?></dd>
      </div>
      <div class="col-md-4 mb-2">
        <dt class="text-muted small">Passport Photo</dt>
        <dd class="mb-0">
          <?php if (!empty($profile['passport_photo_path'])): ?>
            <img src="/uploads/<?= View::e($profile['passport_photo_path']) ?>"
                 class="img-thumbnail" style="width:100px;height:100px;object-fit:cover;" alt="Passport Photo">
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </dd>
      </div>
      <?php roRow('Student Email', $profile['student_email'] ?? null); ?>
      <?php roRow('Alternate Mobile', $profile['alternate_mobile'] ?? null); ?>
      <?php roRow('Marital Status', $profile['marital_status'] ?? null); ?>
      <?php roRow('Physically Challenged', isset($profile['physically_challenged']) ? ($profile['physically_challenged'] ? 'Yes' : 'No') : null); ?>
      <?php roRow('Nature of Disability', $profile['disability_nature'] ?? null); ?>
      <?php roRow('First Graduate', isset($profile['first_graduate']) ? ($profile['first_graduate'] ? 'Yes' : 'No') : null); ?>
      <?php roRow('Annual Family Income (₹)', isset($profile['annual_family_income']) ? number_format((int)$profile['annual_family_income']) : null); ?>
      <?php foreach (($customFields ?? []) as $cf): if ($cf['section'] !== 'Personal Details') continue; roRow(htmlspecialchars($cf['label']), $customData[(int)$cf['id']] ?? null); endforeach; ?>
    </dl>
  </div>
</div>

<!-- Section 2: Address -->
<div class="card mb-3">
  <div class="card-header fw-bold">2. Address Details</div>
  <div class="card-body">
    <h6 class="fw-semibold mb-2">Permanent Address</h6>
    <dl class="row mb-0">
      <?php roRow('Address Line 1', $profile['perm_address1'] ?? null); ?>
      <?php roRow('Address Line 2', $profile['perm_address2'] ?? null); ?>
      <?php roRow('City / Town', $profile['perm_city'] ?? null); ?>
      <?php roRow('Taluk', $profile['perm_taluk_name'] ?? null); ?>
      <?php roRow('District', $profile['perm_district_name'] ?? null); ?>
      <?php roRow('State', $profile['perm_state_name'] ?? null); ?>
      <?php roRow('PIN Code', $profile['perm_pincode'] ?? null); ?>
    </dl>
    <h6 class="fw-semibold mt-3 mb-2">Communication Address
      <?php if (!empty($profile['comm_same_as_perm'])): ?>
        <span class="badge bg-info text-dark ms-1">Same as Permanent</span>
      <?php endif; ?>
    </h6>
    <?php if (empty($profile['comm_same_as_perm'])): ?>
    <dl class="row mb-0">
      <?php roRow('Address Line 1', $profile['comm_address1'] ?? null); ?>
      <?php roRow('Address Line 2', $profile['comm_address2'] ?? null); ?>
      <?php roRow('City / Town', $profile['comm_city'] ?? null); ?>
      <?php roRow('Taluk', $profile['comm_taluk_name'] ?? null); ?>
      <?php roRow('District', $profile['comm_district_name'] ?? null); ?>
      <?php roRow('State', $profile['comm_state_name'] ?? null); ?>
      <?php roRow('PIN Code', $profile['comm_pincode'] ?? null); ?>
    </dl>
    <?php endif; ?>
    <?php if (!empty($customFields)): ?>
    <dl class="row mb-0 mt-2">
      <?php foreach (($customFields ?? []) as $cf): if ($cf['section'] !== 'Address Details') continue; roRow(htmlspecialchars($cf['label']), $customData[(int)$cf['id']] ?? null); endforeach; ?>
    </dl>
    <?php endif; ?>
  </div>
</div>

<!-- Section 3: Parent / Guardian -->
<div class="card mb-3">
  <div class="card-header fw-bold">3. Parent / Guardian Details</div>
  <div class="card-body">
    <dl class="row mb-0">
      <?php roRow('Family Situation', $profile['family_situation'] ?? null); ?>
      <?php roRow("Father's Name", $profile['father_name'] ?? null); ?>
      <?php roRow("Father's Occupation", $profile['father_occupation'] ?? null); ?>
      <?php roRow("Father's Qualification", $profile['father_qualification'] ?? null); ?>
      <?php roRow("Father's Annual Income (₹)", isset($profile['father_annual_income']) && $profile['father_annual_income'] !== null ? number_format((int)$profile['father_annual_income']) : null); ?>
      <?php roRow("Father's Mobile", $profile['father_mobile'] ?? null); ?>
      <?php roRow("Father's Email", $profile['father_email'] ?? null); ?>
      <?php roRow("Mother's Name", $profile['mother_name'] ?? null); ?>
      <?php roRow("Mother's Occupation", $profile['mother_occupation'] ?? null); ?>
      <?php roRow("Mother's Qualification", $profile['mother_qualification'] ?? null); ?>
      <?php roRow("Mother's Annual Income (₹)", isset($profile['mother_annual_income']) && $profile['mother_annual_income'] !== null ? number_format((int)$profile['mother_annual_income']) : null); ?>
      <?php roRow("Mother's Mobile", $profile['mother_mobile'] ?? null); ?>
      <?php roRow("Mother's Email", $profile['mother_email'] ?? null); ?>
      <?php if (!empty($profile['guardian_name'])): ?>
        <?php roRow('Guardian Name', $profile['guardian_name'] ?? null); ?>
        <?php roRow('Guardian Relationship', $profile['guardian_relationship'] ?? null); ?>
        <?php roRow('Guardian Mobile', $profile['guardian_mobile'] ?? null); ?>
        <?php roRow('Guardian Address', $profile['guardian_address'] ?? null); ?>
        <?php roRow('Guardian Email', $profile['guardian_email'] ?? null); ?>
      <?php endif; ?>
      <?php foreach (($customFields ?? []) as $cf): if ($cf['section'] !== 'Parent / Guardian Details') continue; roRow(htmlspecialchars($cf['label']), $customData[(int)$cf['id']] ?? null); endforeach; ?>
    </dl>
  </div>
</div>

<!-- Section 4: Academic Background -->
<div class="card mb-3">
  <div class="card-header fw-bold">4. Academic Background</div>
  <div class="card-body">
    <dl class="row mb-0">
      <?php roQual('SSLC / 10th Standard', $profile['qual_sslc'] ?? null); ?>
      <?php roDoc('SSLC Mark Sheet', $profile['qual_sslc_doc_path'] ?? null); ?>
      <?php roQual('HSC / 12th / Diploma', $profile['qual_hsc'] ?? null); ?>
      <?php roDoc('HSC Mark Sheet', $profile['qual_hsc_doc_path'] ?? null); ?>
      <?php if (!empty($profile['qual_ug'])): ?>
        <?php roQual('UG Degree', $profile['qual_ug']); ?>
        <?php roDoc('UG Degree Mark Sheet', $profile['qual_ug_doc_path'] ?? null); ?>
      <?php endif; ?>
      <?php if (!empty($profile['qual_diploma'])): ?>
        <?php roQual('Diploma / Lateral Entry', $profile['qual_diploma']); ?>
        <?php roDoc('Diploma Mark Sheet', $profile['qual_diploma_doc_path'] ?? null); ?>
      <?php endif; ?>
      <?php if (!empty($profile['qual_other_1'])): ?>
        <?php roQual('Other Qualification 1', $profile['qual_other_1']); ?>
      <?php endif; ?>
      <?php if (!empty($profile['qual_other_2'])): ?>
        <?php roQual('Other Qualification 2', $profile['qual_other_2']); ?>
      <?php endif; ?>
      <?php foreach (($customFields ?? []) as $cf): if ($cf['section'] !== 'Academic Background') continue; roRow(htmlspecialchars($cf['label']), $customData[(int)$cf['id']] ?? null); endforeach; ?>
    </dl>
  </div>
</div>

<!-- Section 5: Entrance & Admission -->
<div class="card mb-3">
  <div class="card-header fw-bold">5. Entrance &amp; Admission Details</div>
  <div class="card-body">
    <dl class="row mb-0">
      <?php roRow('Admission Type', $profile['admission_type'] ?? null); ?>
      <?php roRow('Entrance Exam Name', $profile['entrance_exam_name'] ?? null); ?>
      <?php roRow('Entrance Hall Ticket No.', $profile['entrance_hall_ticket'] ?? null); ?>
      <?php roRow('Entrance Rank / Score', $profile['entrance_rank_score'] ?? null); ?>
      <?php roRow('Admission / Application No.', $profile['admission_number'] ?? null); ?>
      <?php roRow('Community Certificate No.', $profile['community_cert_number'] ?? null); ?>
      <?php roDoc('Community Certificate', $profile['community_cert_path'] ?? null); ?>
      <?php roRow('Transfer Certificate No.', $profile['transfer_cert_number'] ?? null); ?>
      <?php roDoc('Transfer Certificate', $profile['transfer_cert_path'] ?? null); ?>
      <?php roDoc('Conduct Certificate', $profile['conduct_cert_path'] ?? null); ?>
      <?php roDoc('Migration Certificate', $profile['migration_cert_path'] ?? null); ?>
      <?php roDoc('Income Certificate', $profile['income_cert_path'] ?? null); ?>
      <?php roDoc('Nativity Certificate', $profile['nativity_cert_path'] ?? null); ?>
      <?php roDoc('Aadhaar Card Copy', $profile['aadhaar_copy_path'] ?? null); ?>
      <?php foreach (($customFields ?? []) as $cf): if ($cf['section'] !== 'Entrance & Admission Details') continue; roRow(htmlspecialchars($cf['label']), $customData[(int)$cf['id']] ?? null); endforeach; ?>
    </dl>
  </div>
</div>

<!-- Section 6: Bank & Scholarship -->
<div class="card mb-3">
  <div class="card-header fw-bold">6. Bank &amp; Scholarship Details</div>
  <div class="card-body">
    <dl class="row mb-0">
      <?php roRow('Account Holder Name', $profile['bank_account_holder'] ?? null); ?>
      <?php roRow('Bank Name', $profile['bank_name'] ?? null); ?>
      <?php roRow('Branch Name', $profile['bank_branch'] ?? null); ?>
      <?php roRow('Account Number', $profile['bank_account_number'] ?? null); ?>
      <?php roRow('IFSC Code', $profile['bank_ifsc'] ?? null); ?>
      <?php roDoc('Bank Passbook / Statement', $profile['bank_passbook_path'] ?? null); ?>
      <?php roRow('Scholarship Applied?', isset($profile['scholarship_applied']) ? ($profile['scholarship_applied'] ? 'Yes' : 'No') : null); ?>
      <?php roRow('Scholarship Scheme', $profile['scholarship_scheme'] ?? null); ?>
      <?php roRow('Scholarship Application No.', $profile['scholarship_app_number'] ?? null); ?>
      <?php foreach (($customFields ?? []) as $cf): if ($cf['section'] !== 'Bank & Scholarship Details') continue; roRow(htmlspecialchars($cf['label']), $customData[(int)$cf['id']] ?? null); endforeach; ?>
    </dl>
  </div>
</div>
