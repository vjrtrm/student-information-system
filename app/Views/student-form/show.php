<?php
use App\Helpers\View;
$pct      = $summary['pct'] ?? 0;
$barClass = $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger');
$level    = $student['programme_level'] ?? 'UG';
$isPG     = $level === 'PG';
$admType  = $profile['admission_type'] ?? '';
$isLateral = $admType === 'lateral_entry';
?>
<h2 class="mb-1">My Information Form</h2>
<p class="text-muted mb-3">Fill in all required fields (*) and save. Submit when 100% complete.</p>

<?php include __DIR__ . '/_section_status.php'; ?>

<div class="mb-3">
  <div class="d-flex justify-content-between mb-1">
    <span class="fw-semibold">Overall Completion</span>
    <span><?= $pct ?>%</span>
  </div>
  <div class="progress" style="height:20px;">
    <div class="progress-bar <?= $barClass ?>" role="progressbar"
         style="width:<?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
      <?= $pct ?>%
    </div>
  </div>
</div>

<form method="POST" action="/student/form/save" enctype="multipart/form-data" id="profileForm">
  <?= \App\Helpers\Csrf::field() ?>

  <div class="accordion" id="formAccordion">

    <!-- ═══════════════════════════════════════════════════════════
         Section 1: Personal Details
    ═══════════════════════════════════════════════════════════ -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="sec1Heading">
        <button class="accordion-button" type="button" data-bs-toggle="collapse"
                data-bs-target="#sec1" aria-expanded="true" aria-controls="sec1">
          1. Personal Details
          <?php if ($summary['sections'][1] ?? false): ?>
            <span class="badge bg-success ms-2">&#10003; Complete</span>
          <?php else: ?>
            <span class="badge bg-secondary ms-2">Incomplete</span>
          <?php endif; ?>
        </button>
      </h2>
      <div id="sec1" class="accordion-collapse collapse show" aria-labelledby="sec1Heading">
        <div class="accordion-body">
          <div class="row g-3">
            <!-- Read-only fields from M3 -->
            <div class="col-md-4">
              <label class="form-label">First Name</label>
              <input type="text" class="form-control-plaintext border-bottom" readonly
                     value="<?= View::e($student['first_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Last Name</label>
              <input type="text" class="form-control-plaintext border-bottom" readonly
                     value="<?= View::e($student['last_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Date of Birth</label>
              <input type="text" class="form-control-plaintext border-bottom" readonly
                     value="<?= View::e($student['dob'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Gender</label>
              <input type="text" class="form-control-plaintext border-bottom" readonly
                     value="<?= View::e(ucfirst($student['gender'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Mobile</label>
              <input type="text" class="form-control-plaintext border-bottom" readonly
                     value="<?= View::e($student['mobile'] ?? '') ?>">
            </div>

            <!-- Blood Group -->
            <div class="col-md-4">
              <label class="form-label">Blood Group <span class="text-danger">*</span></label>
              <select name="blood_group" class="form-select" required>
                <option value="">— Select —</option>
                <?php foreach ($bloodGroups as $bg): ?>
                  <option value="<?= View::e($bg) ?>"
                    <?= ($profile['blood_group'] ?? '') === $bg ? 'selected' : '' ?>>
                    <?= View::e($bg) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Mother Tongue -->
            <div class="col-md-4">
              <label class="form-label">Mother Tongue <span class="text-danger">*</span></label>
              <input type="text" name="mother_tongue" class="form-control" required
                     value="<?= View::e($profile['mother_tongue'] ?? '') ?>">
            </div>

            <!-- Religion -->
            <div class="col-md-4">
              <label class="form-label">Religion <span class="text-danger">*</span></label>
              <select name="religion" class="form-select" required>
                <option value="">— Select —</option>
                <?php foreach ($religions as $rel): ?>
                  <?php $rv = is_array($rel) ? ($rel['value'] ?? $rel['display'] ?? '') : $rel; ?>
                  <?php $rl = is_array($rel) ? ($rel['display'] ?? $rel['value'] ?? '') : $rel; ?>
                  <option value="<?= View::e($rv) ?>"
                    <?= ($profile['religion'] ?? '') === $rv ? 'selected' : '' ?>>
                    <?= View::e($rl) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Caste -->
            <div class="col-md-4">
              <label class="form-label">Caste <span class="text-danger">*</span></label>
              <input type="text" name="caste" class="form-control" required
                     value="<?= View::e($profile['caste'] ?? '') ?>">
            </div>

            <!-- Caste Category -->
            <div class="col-md-4">
              <label class="form-label">Caste Category <span class="text-danger">*</span></label>
              <select name="caste_category" class="form-select" required>
                <option value="">— Select —</option>
                <?php foreach ($casteCategories as $cc): ?>
                  <option value="<?= View::e($cc) ?>"
                    <?= ($profile['caste_category'] ?? '') === $cc ? 'selected' : '' ?>>
                    <?= View::e($cc) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Sub-Caste (optional) -->
            <div class="col-md-4">
              <label class="form-label">Sub-Caste</label>
              <input type="text" name="sub_caste" class="form-control"
                     value="<?= View::e($profile['sub_caste'] ?? '') ?>">
            </div>

            <!-- Nationality -->
            <div class="col-md-4">
              <label class="form-label">Nationality <span class="text-danger">*</span></label>
              <input type="text" name="nationality" class="form-control" required
                     value="<?= View::e($profile['nationality'] ?? 'Indian') ?>">
            </div>

            <!-- Place of Birth -->
            <div class="col-md-4">
              <label class="form-label">Place of Birth <span class="text-danger">*</span></label>
              <input type="text" name="place_of_birth" class="form-control" required
                     value="<?= View::e($profile['place_of_birth'] ?? '') ?>">
            </div>

            <!-- Aadhaar Number -->
            <div class="col-md-4">
              <label class="form-label">Aadhaar Number <span class="text-danger">*</span></label>
              <input type="text" name="aadhaar_number" class="form-control" required
                     maxlength="12" pattern="\d{12}" placeholder="12-digit number"
                     value="<?= View::e($profile['aadhaar_number'] ?? '') ?>">
            </div>

            <!-- Passport Photo -->
            <div class="col-md-4">
              <label class="form-label">Passport Photo <span class="text-danger">*</span></label>
              <?php if (!empty($profile['passport_photo_path'])): ?>
                <div class="mb-1">
                  <img src="/uploads/<?= View::e($profile['passport_photo_path']) ?>"
                       class="img-thumbnail" style="width:80px;height:80px;object-fit:cover;" alt="Photo">
                </div>
              <?php endif; ?>
              <input type="file" name="passport_photo_path" class="form-control"
                     accept="image/jpeg,image/png"
                     <?= empty($profile['passport_photo_path']) ? 'required' : '' ?>>
              <div class="form-text">JPEG or PNG, max 2 MB.</div>
            </div>

            <!-- Student Email -->
            <div class="col-md-4">
              <label class="form-label">Student Email <span class="text-danger">*</span></label>
              <input type="email" name="student_email" class="form-control" required
                     value="<?= View::e($profile['student_email'] ?? '') ?>">
            </div>

            <!-- Alternate Mobile (optional) -->
            <div class="col-md-4">
              <label class="form-label">Alternate Mobile</label>
              <input type="text" name="alternate_mobile" class="form-control" maxlength="10"
                     value="<?= View::e($profile['alternate_mobile'] ?? '') ?>">
            </div>

            <!-- Marital Status -->
            <div class="col-md-4">
              <label class="form-label">Marital Status <span class="text-danger">*</span></label>
              <select name="marital_status" class="form-select" required>
                <option value="">— Select —</option>
                <?php foreach ($maritalStatuses as $ms): ?>
                  <option value="<?= View::e($ms) ?>"
                    <?= ($profile['marital_status'] ?? '') === $ms ? 'selected' : '' ?>>
                    <?= View::e($ms) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Physically Challenged -->
            <div class="col-md-4">
              <label class="form-label d-block">Physically Challenged <span class="text-danger">*</span></label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="physically_challenged"
                       id="pc_yes" value="1"
                       <?= ($profile['physically_challenged'] ?? 0) == 1 ? 'checked' : '' ?>
                       onchange="togglePhysical()">
                <label class="form-check-label" for="pc_yes">Yes</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="physically_challenged"
                       id="pc_no" value="0"
                       <?= ($profile['physically_challenged'] ?? 0) == 0 ? 'checked' : '' ?>
                       onchange="togglePhysical()">
                <label class="form-check-label" for="pc_no">No</label>
              </div>
            </div>

            <!-- Disability Nature (conditional) -->
            <div class="col-md-4" id="disabilityRow"
                 style="<?= ($profile['physically_challenged'] ?? 0) ? '' : 'display:none;' ?>">
              <label class="form-label">Nature of Disability <span class="text-danger">*</span></label>
              <input type="text" name="disability_nature" class="form-control"
                     value="<?= View::e($profile['disability_nature'] ?? '') ?>">
            </div>

            <!-- First Graduate -->
            <div class="col-md-4">
              <label class="form-label d-block">First Graduate <span class="text-danger">*</span></label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="first_graduate"
                       id="fg_yes" value="1"
                       <?= ($profile['first_graduate'] ?? '') == '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="fg_yes">Yes</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="first_graduate"
                       id="fg_no" value="0"
                       <?= ($profile['first_graduate'] ?? '') === '0' ? 'checked' : '' ?>>
                <label class="form-check-label" for="fg_no">No</label>
              </div>
            </div>

            <!-- Annual Family Income -->
            <div class="col-md-4">
              <label class="form-label">Annual Family Income (₹) <span class="text-danger">*</span></label>
              <input type="number" name="annual_family_income" class="form-control" required min="0"
                     value="<?= View::e($profile['annual_family_income'] ?? '') ?>">
            </div>
          </div>

          <div class="text-end mt-3">
            <button type="submit" class="btn btn-primary">Save Section 1</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         Section 2: Address Details
    ═══════════════════════════════════════════════════════════ -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="sec2Heading">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#sec2" aria-expanded="false" aria-controls="sec2">
          2. Address Details
          <?php if ($summary['sections'][2] ?? false): ?>
            <span class="badge bg-success ms-2">&#10003; Complete</span>
          <?php else: ?>
            <span class="badge bg-secondary ms-2">Incomplete</span>
          <?php endif; ?>
        </button>
      </h2>
      <div id="sec2" class="accordion-collapse collapse" aria-labelledby="sec2Heading">
        <div class="accordion-body">
          <h6 class="fw-bold mb-3">Permanent Address</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Address Line 1 <span class="text-danger">*</span></label>
              <input type="text" name="perm_address1" class="form-control" required
                     value="<?= View::e($profile['perm_address1'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Address Line 2</label>
              <input type="text" name="perm_address2" class="form-control"
                     value="<?= View::e($profile['perm_address2'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">City / Town <span class="text-danger">*</span></label>
              <input type="text" name="perm_city" class="form-control" required
                     value="<?= View::e($profile['perm_city'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">State <span class="text-danger">*</span></label>
              <select name="perm_state_id" class="form-select" required id="perm_state_id"
                      onchange="loadDistricts('perm','perm_district_id','perm_taluk_id')">
                <option value="">— Select State —</option>
                <?php foreach ($states as $st): ?>
                  <option value="<?= View::e($st['id']) ?>"
                    <?= ($profile['perm_state_id'] ?? '') == $st['id'] ? 'selected' : '' ?>>
                    <?= View::e($st['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">District <span class="text-danger">*</span></label>
              <select name="perm_district_id" class="form-select" required id="perm_district_id"
                      onchange="loadTaluks('perm_district_id','perm_taluk_id')">
                <option value="">— Select District —</option>
                <?php if (!empty($profile['perm_district_id'])): ?>
                  <option value="<?= View::e($profile['perm_district_id']) ?>" selected>
                    <?= View::e($profile['perm_district_name'] ?? $profile['perm_district_id']) ?>
                  </option>
                <?php endif; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Taluk <span class="text-danger">*</span></label>
              <select name="perm_taluk_id" class="form-select" required id="perm_taluk_id">
                <option value="">— Select Taluk —</option>
                <?php if (!empty($profile['perm_taluk_id'])): ?>
                  <option value="<?= View::e($profile['perm_taluk_id']) ?>" selected>
                    <?= View::e($profile['perm_taluk_name'] ?? $profile['perm_taluk_id']) ?>
                  </option>
                <?php endif; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">PIN Code <span class="text-danger">*</span></label>
              <input type="text" name="perm_pincode" class="form-control" required
                     maxlength="6" pattern="\d{6}"
                     value="<?= View::e($profile['perm_pincode'] ?? '') ?>">
            </div>
          </div>

          <hr class="my-4">

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="comm_same_as_perm"
                   id="comm_same_as_perm" value="1"
                   <?= !empty($profile['comm_same_as_perm']) ? 'checked' : '' ?>
                   onchange="toggleCommAddress()">
            <label class="form-check-label fw-bold" for="comm_same_as_perm">
              Communication Address same as Permanent Address
            </label>
          </div>

          <div id="commAddressSection" style="<?= !empty($profile['comm_same_as_perm']) ? 'display:none;' : '' ?>">
            <h6 class="fw-bold mb-3">Communication Address</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Address Line 1 <span class="text-danger">*</span></label>
                <input type="text" name="comm_address1" class="form-control"
                       value="<?= View::e($profile['comm_address1'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Address Line 2</label>
                <input type="text" name="comm_address2" class="form-control"
                       value="<?= View::e($profile['comm_address2'] ?? '') ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">City / Town <span class="text-danger">*</span></label>
                <input type="text" name="comm_city" class="form-control"
                       value="<?= View::e($profile['comm_city'] ?? '') ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">State <span class="text-danger">*</span></label>
                <select name="comm_state_id" class="form-select" id="comm_state_id"
                        onchange="loadDistricts('comm','comm_district_id','comm_taluk_id')">
                  <option value="">— Select State —</option>
                  <?php foreach ($states as $st): ?>
                    <option value="<?= View::e($st['id']) ?>"
                      <?= ($profile['comm_state_id'] ?? '') == $st['id'] ? 'selected' : '' ?>>
                      <?= View::e($st['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">District <span class="text-danger">*</span></label>
                <select name="comm_district_id" class="form-select" id="comm_district_id"
                        onchange="loadTaluks('comm_district_id','comm_taluk_id')">
                  <option value="">— Select District —</option>
                  <?php if (!empty($profile['comm_district_id'])): ?>
                    <option value="<?= View::e($profile['comm_district_id']) ?>" selected>
                      <?= View::e($profile['comm_district_name'] ?? $profile['comm_district_id']) ?>
                    </option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Taluk <span class="text-danger">*</span></label>
                <select name="comm_taluk_id" class="form-select" id="comm_taluk_id">
                  <option value="">— Select Taluk —</option>
                  <?php if (!empty($profile['comm_taluk_id'])): ?>
                    <option value="<?= View::e($profile['comm_taluk_id']) ?>" selected>
                      <?= View::e($profile['comm_taluk_name'] ?? $profile['comm_taluk_id']) ?>
                    </option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">PIN Code <span class="text-danger">*</span></label>
                <input type="text" name="comm_pincode" class="form-control"
                       maxlength="6" pattern="\d{6}"
                       value="<?= View::e($profile['comm_pincode'] ?? '') ?>">
              </div>
            </div>
          </div>

          <div class="text-end mt-3">
            <button type="submit" class="btn btn-primary">Save Section 2</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         Section 3: Parent / Guardian Details
    ═══════════════════════════════════════════════════════════ -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="sec3Heading">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#sec3" aria-expanded="false" aria-controls="sec3">
          3. Parent / Guardian Details
          <?php if ($summary['sections'][3] ?? false): ?>
            <span class="badge bg-success ms-2">&#10003; Complete</span>
          <?php else: ?>
            <span class="badge bg-secondary ms-2">Incomplete</span>
          <?php endif; ?>
        </button>
      </h2>
      <div id="sec3" class="accordion-collapse collapse" aria-labelledby="sec3Heading">
        <div class="accordion-body">
          <div class="row g-3">
            <!-- Family Situation -->
            <div class="col-md-4">
              <label class="form-label">Family Situation <span class="text-danger">*</span></label>
              <select name="family_situation" class="form-select" required id="family_situation"
                      onchange="toggleFamilyFields()">
                <option value="">— Select —</option>
                <option value="both_parents" <?= ($profile['family_situation'] ?? '') === 'both_parents' ? 'selected' : '' ?>>Both Parents</option>
                <option value="single_parent_father" <?= ($profile['family_situation'] ?? '') === 'single_parent_father' ? 'selected' : '' ?>>Single Parent (Father)</option>
                <option value="single_parent_mother" <?= ($profile['family_situation'] ?? '') === 'single_parent_mother' ? 'selected' : '' ?>>Single Parent (Mother)</option>
                <option value="guardian" <?= ($profile['family_situation'] ?? '') === 'guardian' ? 'selected' : '' ?>>Guardian</option>
              </select>
            </div>

            <!-- Father's Name (always shown) -->
            <div class="col-md-4">
              <label class="form-label">Father's Name <span class="text-danger">*</span></label>
              <input type="text" name="father_name" class="form-control" required
                     value="<?= View::e($profile['father_name'] ?? '') ?>">
            </div>

            <!-- Father Occupation -->
            <div class="col-md-4" id="fatherOccRow">
              <label class="form-label">Father's Occupation <span class="text-danger" id="fatherOccStar">*</span></label>
              <input type="text" name="father_occupation" class="form-control"
                     value="<?= View::e($profile['father_occupation'] ?? '') ?>">
            </div>

            <!-- Father Qualification -->
            <div class="col-md-4" id="fatherQualRow">
              <label class="form-label">Father's Qualification <span class="text-danger" id="fatherQualStar">*</span></label>
              <input type="text" name="father_qualification" class="form-control"
                     value="<?= View::e($profile['father_qualification'] ?? '') ?>">
            </div>

            <!-- Father Annual Income -->
            <div class="col-md-4" id="fatherIncRow">
              <label class="form-label">Father's Annual Income (₹) <span class="text-danger" id="fatherIncStar">*</span></label>
              <input type="number" name="father_annual_income" class="form-control" min="0"
                     value="<?= View::e($profile['father_annual_income'] ?? '') ?>">
            </div>

            <!-- Father Mobile -->
            <div class="col-md-4" id="fatherMobRow">
              <label class="form-label">Father's Mobile <span class="text-danger" id="fatherMobStar">*</span></label>
              <input type="text" name="father_mobile" class="form-control" maxlength="10"
                     value="<?= View::e($profile['father_mobile'] ?? '') ?>">
            </div>

            <!-- Father Email (optional) -->
            <div class="col-md-4">
              <label class="form-label">Father's Email</label>
              <input type="email" name="father_email" class="form-control"
                     value="<?= View::e($profile['father_email'] ?? '') ?>">
            </div>

            <!-- Mother fields (visible unless guardian) -->
            <div class="col-12 mt-2" id="motherSection">
              <h6 class="fw-bold">Mother's Details</h6>
            </div>
            <div class="col-md-4" id="motherNameRow">
              <label class="form-label">Mother's Name <span class="text-danger" id="motherNameStar">*</span></label>
              <input type="text" name="mother_name" class="form-control"
                     value="<?= View::e($profile['mother_name'] ?? '') ?>">
            </div>
            <div class="col-md-4" id="motherOccRow">
              <label class="form-label">Mother's Occupation <span class="text-danger" id="motherOccStar">*</span></label>
              <input type="text" name="mother_occupation" class="form-control"
                     value="<?= View::e($profile['mother_occupation'] ?? '') ?>">
            </div>
            <div class="col-md-4" id="motherQualRow">
              <label class="form-label">Mother's Qualification <span class="text-danger" id="motherQualStar">*</span></label>
              <input type="text" name="mother_qualification" class="form-control"
                     value="<?= View::e($profile['mother_qualification'] ?? '') ?>">
            </div>
            <div class="col-md-4" id="motherIncRow">
              <label class="form-label">Mother's Annual Income (₹) <span class="text-danger" id="motherIncStar">*</span></label>
              <input type="number" name="mother_annual_income" class="form-control" min="0"
                     value="<?= View::e($profile['mother_annual_income'] ?? '') ?>">
            </div>
            <div class="col-md-4" id="motherMobRow">
              <label class="form-label">Mother's Mobile <span class="text-danger" id="motherMobStar">*</span></label>
              <input type="text" name="mother_mobile" class="form-control" maxlength="10"
                     value="<?= View::e($profile['mother_mobile'] ?? '') ?>">
            </div>
            <div class="col-md-4" id="motherEmailRow">
              <label class="form-label">Mother's Email</label>
              <input type="email" name="mother_email" class="form-control"
                     value="<?= View::e($profile['mother_email'] ?? '') ?>">
            </div>

            <!-- Guardian fields (hidden for both_parents) -->
            <div class="col-12 mt-2" id="guardianSection" style="display:none;">
              <h6 class="fw-bold">Guardian Details</h6>
            </div>
            <div class="col-md-4" id="guardianNameRow" style="display:none;">
              <label class="form-label">Guardian Name <span class="text-danger" id="guardianNameStar">*</span></label>
              <input type="text" name="guardian_name" class="form-control"
                     value="<?= View::e($profile['guardian_name'] ?? '') ?>">
            </div>
            <div class="col-md-4" id="guardianRelRow" style="display:none;">
              <label class="form-label">Guardian Relationship <span class="text-danger" id="guardianRelStar">*</span></label>
              <input type="text" name="guardian_relationship" class="form-control"
                     value="<?= View::e($profile['guardian_relationship'] ?? '') ?>">
            </div>
            <div class="col-md-4" id="guardianMobRow" style="display:none;">
              <label class="form-label">Guardian Mobile <span class="text-danger" id="guardianMobStar">*</span></label>
              <input type="text" name="guardian_mobile" class="form-control" maxlength="10"
                     value="<?= View::e($profile['guardian_mobile'] ?? '') ?>">
            </div>
            <div class="col-md-8" id="guardianAddrRow" style="display:none;">
              <label class="form-label">Guardian Address <span class="text-danger" id="guardianAddrStar">*</span></label>
              <textarea name="guardian_address" class="form-control" rows="2"><?= View::e($profile['guardian_address'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4" id="guardianEmailRow" style="display:none;">
              <label class="form-label">Guardian Email</label>
              <input type="email" name="guardian_email" class="form-control"
                     value="<?= View::e($profile['guardian_email'] ?? '') ?>">
            </div>
          </div>

          <div class="text-end mt-3">
            <button type="submit" class="btn btn-primary">Save Section 3</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         Section 4: Academic Background
    ═══════════════════════════════════════════════════════════ -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="sec4Heading">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#sec4" aria-expanded="false" aria-controls="sec4">
          4. Academic Background
          <?php if ($summary['sections'][4] ?? false): ?>
            <span class="badge bg-success ms-2">&#10003; Complete</span>
          <?php else: ?>
            <span class="badge bg-secondary ms-2">Incomplete</span>
          <?php endif; ?>
        </button>
      </h2>
      <div id="sec4" class="accordion-collapse collapse" aria-labelledby="sec4Heading">
        <div class="accordion-body">

          <?php
          $qualFields = [
            ['key'=>'qual_sslc',    'label'=>'SSLC / 10th Standard',   'required'=>true,  'docKey'=>'qual_sslc_doc_path',    'alwaysShow'=>true],
            ['key'=>'qual_hsc',     'label'=>'HSC / 12th / Diploma',   'required'=>true,  'docKey'=>'qual_hsc_doc_path',     'alwaysShow'=>true],
            ['key'=>'qual_ug',      'label'=>'UG Degree',               'required'=>true,  'docKey'=>'qual_ug_doc_path',      'alwaysShow'=>false, 'showId'=>'qualUgRow',      'showCond'=>$isPG],
            ['key'=>'qual_diploma', 'label'=>'Diploma / Lateral Entry', 'required'=>true,  'docKey'=>'qual_diploma_doc_path', 'alwaysShow'=>false, 'showId'=>'qualDiplomaRow', 'showCond'=>$isLateral],
            ['key'=>'qual_other_1', 'label'=>'Other Qualification 1',   'required'=>false, 'docKey'=>null, 'alwaysShow'=>true],
            ['key'=>'qual_other_2', 'label'=>'Other Qualification 2',   'required'=>false, 'docKey'=>null, 'alwaysShow'=>true],
          ];
          foreach ($qualFields as $qf):
            $k    = $qf['key'];
            $qval = $profile[$k] ?? [];
            if (!is_array($qval)) $qval = [];
            $show = $qf['alwaysShow'] || ($qf['showCond'] ?? false);
            $rowId = $qf['showId'] ?? '';
          ?>
          <div class="card mb-3<?= $show ? '' : ' d-none' ?>"
               <?= $rowId ? "id=\"{$rowId}\"" : '' ?>>
            <div class="card-header fw-semibold">
              <?= View::e($qf['label']) ?>
              <?php if ($qf['required'] && $show): ?>
                <span class="text-danger">*</span>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <div class="row g-2">
                <div class="col-md-3">
                  <label class="form-label">Exam / Course</label>
                  <input type="text" name="<?= View::e($k) ?>[exam]" class="form-control"
                         value="<?= View::e($qval['exam'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Board / University</label>
                  <input type="text" name="<?= View::e($k) ?>[board]" class="form-control"
                         value="<?= View::e($qval['board'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Institution</label>
                  <input type="text" name="<?= View::e($k) ?>[institution]" class="form-control"
                         value="<?= View::e($qval['institution'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Year of Pass</label>
                  <input type="text" name="<?= View::e($k) ?>[year]" class="form-control"
                         maxlength="4" placeholder="YYYY"
                         value="<?= View::e($qval['year'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                  <label class="form-label">% / CGPA</label>
                  <input type="text" name="<?= View::e($k) ?>[percentage]" class="form-control"
                         value="<?= View::e($qval['percentage'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Stream</label>
                  <input type="text" name="<?= View::e($k) ?>[stream]" class="form-control"
                         value="<?= View::e($qval['stream'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Medium</label>
                  <input type="text" name="<?= View::e($k) ?>[medium]" class="form-control"
                         value="<?= View::e($qval['medium'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                  <label class="form-label">State</label>
                  <input type="text" name="<?= View::e($k) ?>[state]" class="form-control"
                         value="<?= View::e($qval['state'] ?? '') ?>">
                </div>
                <?php if ($qf['docKey']): ?>
                <div class="col-md-4">
                  <label class="form-label">Mark Sheet / Certificate</label>
                  <?php if (!empty($profile[$qf['docKey']])): ?>
                    <div class="mb-1">
                      <a href="/uploads/<?= View::e($profile[$qf['docKey']]) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Uploaded</a>
                    </div>
                  <?php endif; ?>
                  <input type="file" name="<?= View::e($qf['docKey']) ?>"
                         class="form-control" accept=".pdf,image/jpeg,image/png">
                  <div class="form-text">PDF/JPEG/PNG, max 2 MB.</div>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>

          <div class="text-end mt-3">
            <button type="submit" class="btn btn-primary">Save Section 4</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         Section 5: Entrance & Admission Details
    ═══════════════════════════════════════════════════════════ -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="sec5Heading">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#sec5" aria-expanded="false" aria-controls="sec5">
          5. Entrance &amp; Admission Details
          <?php if ($summary['sections'][5] ?? false): ?>
            <span class="badge bg-success ms-2">&#10003; Complete</span>
          <?php else: ?>
            <span class="badge bg-secondary ms-2">Incomplete</span>
          <?php endif; ?>
        </button>
      </h2>
      <div id="sec5" class="accordion-collapse collapse" aria-labelledby="sec5Heading">
        <div class="accordion-body">
          <div class="row g-3">
            <!-- Admission Type -->
            <div class="col-md-4">
              <label class="form-label">Admission Type <span class="text-danger">*</span></label>
              <select name="admission_type" class="form-select" required id="admission_type"
                      onchange="toggleLateralEntry()">
                <option value="">— Select —</option>
                <?php foreach ($admissionTypes as $av => $al): ?>
                  <option value="<?= View::e($av) ?>"
                    <?= ($profile['admission_type'] ?? '') === $av ? 'selected' : '' ?>>
                    <?= View::e($al) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Entrance Exam fields (optional) -->
            <div class="col-md-4">
              <label class="form-label">Entrance Exam Name</label>
              <input type="text" name="entrance_exam_name" class="form-control"
                     value="<?= View::e($profile['entrance_exam_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Entrance Hall Ticket No.</label>
              <input type="text" name="entrance_hall_ticket" class="form-control"
                     value="<?= View::e($profile['entrance_hall_ticket'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Entrance Rank / Score</label>
              <input type="text" name="entrance_rank_score" class="form-control"
                     value="<?= View::e($profile['entrance_rank_score'] ?? '') ?>">
            </div>

            <!-- Admission Number -->
            <div class="col-md-4">
              <label class="form-label">Admission / Application No. <span class="text-danger">*</span></label>
              <input type="text" name="admission_number" class="form-control" required
                     value="<?= View::e($profile['admission_number'] ?? '') ?>">
            </div>

            <!-- Community Certificate -->
            <div class="col-md-4">
              <label class="form-label">Community Certificate No.</label>
              <input type="text" name="community_cert_number" class="form-control"
                     value="<?= View::e($profile['community_cert_number'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Community Certificate</label>
              <?php if (!empty($profile['community_cert_path'])): ?>
                <div class="mb-1"><a href="/uploads/<?= View::e($profile['community_cert_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Uploaded</a></div>
              <?php endif; ?>
              <input type="file" name="community_cert_path" class="form-control"
                     accept=".pdf,image/jpeg,image/png">
            </div>

            <!-- Transfer Certificate -->
            <div class="col-md-4">
              <label class="form-label">Transfer Certificate No.</label>
              <input type="text" name="transfer_cert_number" class="form-control"
                     value="<?= View::e($profile['transfer_cert_number'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Transfer Certificate</label>
              <?php if (!empty($profile['transfer_cert_path'])): ?>
                <div class="mb-1"><a href="/uploads/<?= View::e($profile['transfer_cert_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Uploaded</a></div>
              <?php endif; ?>
              <input type="file" name="transfer_cert_path" class="form-control"
                     accept=".pdf,image/jpeg,image/png">
            </div>

            <!-- Conduct Certificate -->
            <div class="col-md-4">
              <label class="form-label">Conduct Certificate</label>
              <?php if (!empty($profile['conduct_cert_path'])): ?>
                <div class="mb-1"><a href="/uploads/<?= View::e($profile['conduct_cert_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Uploaded</a></div>
              <?php endif; ?>
              <input type="file" name="conduct_cert_path" class="form-control"
                     accept=".pdf,image/jpeg,image/png">
            </div>

            <!-- Migration Certificate (PG only) -->
            <?php if ($isPG): ?>
            <div class="col-md-4">
              <label class="form-label">Migration Certificate</label>
              <?php if (!empty($profile['migration_cert_path'])): ?>
                <div class="mb-1"><a href="/uploads/<?= View::e($profile['migration_cert_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Uploaded</a></div>
              <?php endif; ?>
              <input type="file" name="migration_cert_path" class="form-control"
                     accept=".pdf,image/jpeg,image/png">
            </div>
            <?php endif; ?>

            <!-- Income Certificate -->
            <div class="col-md-4">
              <label class="form-label">Income Certificate</label>
              <?php if (!empty($profile['income_cert_path'])): ?>
                <div class="mb-1"><a href="/uploads/<?= View::e($profile['income_cert_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Uploaded</a></div>
              <?php endif; ?>
              <input type="file" name="income_cert_path" class="form-control"
                     accept=".pdf,image/jpeg,image/png">
            </div>

            <!-- Nativity Certificate -->
            <div class="col-md-4">
              <label class="form-label">Nativity Certificate</label>
              <?php if (!empty($profile['nativity_cert_path'])): ?>
                <div class="mb-1"><a href="/uploads/<?= View::e($profile['nativity_cert_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Uploaded</a></div>
              <?php endif; ?>
              <input type="file" name="nativity_cert_path" class="form-control"
                     accept=".pdf,image/jpeg,image/png">
            </div>

            <!-- Aadhaar Copy (required) -->
            <div class="col-md-4">
              <label class="form-label">Aadhaar Card Copy <span class="text-danger">*</span></label>
              <?php if (!empty($profile['aadhaar_copy_path'])): ?>
                <div class="mb-1"><a href="/uploads/<?= View::e($profile['aadhaar_copy_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Uploaded</a></div>
              <?php endif; ?>
              <input type="file" name="aadhaar_copy_path" class="form-control"
                     accept=".pdf,image/jpeg,image/png"
                     <?= empty($profile['aadhaar_copy_path']) ? 'required' : '' ?>>
              <div class="form-text">PDF/JPEG/PNG, max 2 MB.</div>
            </div>
          </div>

          <div class="text-end mt-3">
            <button type="submit" class="btn btn-primary">Save Section 5</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         Section 6: Bank & Scholarship Details
    ═══════════════════════════════════════════════════════════ -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="sec6Heading">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#sec6" aria-expanded="false" aria-controls="sec6">
          6. Bank &amp; Scholarship Details
          <?php if ($summary['sections'][6] ?? false): ?>
            <span class="badge bg-success ms-2">&#10003; Complete</span>
          <?php else: ?>
            <span class="badge bg-secondary ms-2">Optional</span>
          <?php endif; ?>
        </button>
      </h2>
      <div id="sec6" class="accordion-collapse collapse" aria-labelledby="sec6Heading">
        <div class="accordion-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Account Holder Name</label>
              <input type="text" name="bank_account_holder" class="form-control"
                     value="<?= View::e($profile['bank_account_holder'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Bank Name</label>
              <input type="text" name="bank_name" class="form-control"
                     value="<?= View::e($profile['bank_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Branch Name</label>
              <input type="text" name="bank_branch" class="form-control"
                     value="<?= View::e($profile['bank_branch'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Account Number</label>
              <input type="text" name="bank_account_number" class="form-control"
                     value="<?= View::e($profile['bank_account_number'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">IFSC Code</label>
              <input type="text" name="bank_ifsc" class="form-control" maxlength="11"
                     value="<?= View::e($profile['bank_ifsc'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Bank Passbook / Statement</label>
              <?php if (!empty($profile['bank_passbook_path'])): ?>
                <div class="mb-1"><a href="/uploads/<?= View::e($profile['bank_passbook_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Uploaded</a></div>
              <?php endif; ?>
              <input type="file" name="bank_passbook_path" class="form-control"
                     accept=".pdf,image/jpeg,image/png">
            </div>

            <!-- Scholarship -->
            <div class="col-md-4">
              <label class="form-label d-block">Scholarship Applied?</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="scholarship_applied"
                       id="sch_yes" value="1"
                       <?= ($profile['scholarship_applied'] ?? 0) == 1 ? 'checked' : '' ?>
                       onchange="toggleScholarship()">
                <label class="form-check-label" for="sch_yes">Yes</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="scholarship_applied"
                       id="sch_no" value="0"
                       <?= ($profile['scholarship_applied'] ?? 0) == 0 ? 'checked' : '' ?>
                       onchange="toggleScholarship()">
                <label class="form-check-label" for="sch_no">No</label>
              </div>
            </div>

            <div id="scholarshipFields"
                 style="<?= ($profile['scholarship_applied'] ?? 0) ? '' : 'display:none;' ?>"
                 class="col-12">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Scholarship Scheme Name</label>
                  <input type="text" name="scholarship_scheme" class="form-control"
                         value="<?= View::e($profile['scholarship_scheme'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Scholarship Application No.</label>
                  <input type="text" name="scholarship_app_number" class="form-control"
                         value="<?= View::e($profile['scholarship_app_number'] ?? '') ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="text-end mt-3">
            <button type="submit" class="btn btn-primary">Save Section 6</button>
          </div>
        </div>
      </div>
    </div>

  </div><!-- end accordion -->

  <!-- Final Submit button + modal -->
  <div class="d-flex justify-content-end mt-4 gap-2">
    <button type="submit" class="btn btn-outline-primary">Save Progress</button>
    <?php if ($pct >= 100): ?>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#submitModal">
        Submit Form
      </button>
    <?php else: ?>
      <button type="button" class="btn btn-success" disabled
              title="Complete all required fields (<?= $pct ?>% done)">
        Submit Form
      </button>
    <?php endif; ?>
  </div>
</form>

<!-- Submit Confirmation Modal -->
<div class="modal fade" id="submitModal" tabindex="-1" aria-labelledby="submitModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="submitModalLabel">Confirm Form Submission</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Once submitted, you will not be able to edit your form unless you raise a Request to Change.</p>
        <p class="mb-0">Are you sure you want to submit?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" action="/student/form/submit" class="d-inline">
          <?= \App\Helpers\Csrf::field() ?>
          <button type="submit" class="btn btn-success">Yes, Submit</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// ── Physical challenge toggle ────────────────────────────────
function togglePhysical() {
  var yes = document.getElementById('pc_yes');
  var row = document.getElementById('disabilityRow');
  row.style.display = yes && yes.checked ? '' : 'none';
}

// ── Communication address toggle ─────────────────────────────
function toggleCommAddress() {
  var cb = document.getElementById('comm_same_as_perm');
  var section = document.getElementById('commAddressSection');
  section.style.display = cb.checked ? 'none' : '';
}

// ── Family situation toggle ──────────────────────────────────
function toggleFamilyFields() {
  var sit = document.getElementById('family_situation').value;
  var fatherReq   = sit === 'both_parents' || sit === 'single_parent_father';
  var motherVis   = sit !== 'guardian';
  var motherReq   = sit === 'both_parents' || sit === 'single_parent_mother';
  var guardianVis = sit !== 'both_parents';
  var guardianReq = sit === 'guardian';

  // Father required indicator
  ['fatherOccStar','fatherQualStar','fatherIncStar','fatherMobStar'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = fatherReq ? '' : 'none';
  });

  // Mother section visibility
  var motherIds = ['motherSection','motherNameRow','motherOccRow','motherQualRow','motherIncRow','motherMobRow','motherEmailRow'];
  motherIds.forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = motherVis ? '' : 'none';
  });
  // Mother required stars
  ['motherNameStar','motherOccStar','motherQualStar','motherIncStar','motherMobStar'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = (motherVis && motherReq) ? '' : 'none';
  });

  // Guardian section
  var guardianIds = ['guardianSection','guardianNameRow','guardianRelRow','guardianMobRow','guardianAddrRow','guardianEmailRow'];
  guardianIds.forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = guardianVis ? '' : 'none';
  });
  ['guardianNameStar','guardianRelStar','guardianMobStar','guardianAddrStar'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = (guardianVis && guardianReq) ? '' : 'none';
  });
}

// ── Scholarship toggle ──────────────────────────────────────
function toggleScholarship() {
  var yes = document.getElementById('sch_yes');
  var section = document.getElementById('scholarshipFields');
  section.style.display = yes && yes.checked ? '' : 'none';
}

// ── Lateral entry toggle for qual_diploma ───────────────────
function toggleLateralEntry() {
  var adm = document.getElementById('admission_type').value;
  var row = document.getElementById('qualDiplomaRow');
  if (row) row.classList.toggle('d-none', adm !== 'lateral_entry');
}

// ── Geography dropdowns ──────────────────────────────────────
function loadDistricts(prefix, districtSelectId, talukSelectId) {
  var stateId = document.getElementById(prefix + '_state_id').value;
  var distSel = document.getElementById(districtSelectId);
  var talukSel = document.getElementById(talukSelectId);
  distSel.innerHTML = '<option value="">— Select District —</option>';
  talukSel.innerHTML = '<option value="">— Select Taluk —</option>';
  if (!stateId) return;
  fetch('/lookup/districts?state_id=' + stateId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      data.forEach(function(d) {
        var opt = document.createElement('option');
        opt.value = d.id;
        opt.textContent = d.name;
        distSel.appendChild(opt);
      });
    });
}

function loadTaluks(districtSelectId, talukSelectId) {
  var districtId = document.getElementById(districtSelectId).value;
  var talukSel = document.getElementById(talukSelectId);
  talukSel.innerHTML = '<option value="">— Select Taluk —</option>';
  if (!districtId) return;
  fetch('/lookup/taluks?district_id=' + districtId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      data.forEach(function(t) {
        var opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = t.name;
        talukSel.appendChild(opt);
      });
    });
}

// Run on page load to restore state
document.addEventListener('DOMContentLoaded', function() {
  toggleFamilyFields();
});
</script>
