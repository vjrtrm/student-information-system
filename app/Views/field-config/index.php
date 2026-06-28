<?php
// Variables: $bySection (section_name => [key => field_data]), $departments, $sections, $title
use App\Helpers\Csrf;
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Field Configuration — Institution Defaults</h4>
        <div class="d-flex gap-2 align-items-center">
            <label class="form-label mb-0 me-2">Jump to department:</label>
            <select class="form-select form-select-sm w-auto" onchange="if(this.value) window.location='/field-config/'+this.value">
                <option value="">Select department...</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <a href="/field-config/custom" class="btn btn-outline-secondary btn-sm">Custom Fields</a>
        </div>
    </div>

    <form method="POST" action="/field-config">
        <?= Csrf::field() ?>

        <?php foreach ($sections as $sectionName): ?>
            <?php if (empty($bySection[$sectionName])) continue; ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center"
                     style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#section-<?= md5($sectionName) ?>">
                    <strong><?= htmlspecialchars($sectionName) ?></strong>
                    <span class="badge bg-secondary"><?= count($bySection[$sectionName]) ?> fields</span>
                </div>
                <div class="collapse show" id="section-<?= md5($sectionName) ?>">
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Field Label</th>
                                    <th width="160">Current Default</th>
                                    <th width="200">New Setting</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bySection[$sectionName] as $key => $field): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($field['label']) ?></td>
                                        <td>
                                            <?php $curMode = $field['current_mode']; ?>
                                            <span class="badge bg-<?= $curMode === 'required' ? 'danger' : ($curMode === 'hidden' ? 'secondary' : 'success') ?>">
                                                <?= ucfirst($curMode) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <select name="mode[<?= htmlspecialchars($key) ?>]" class="form-select form-select-sm">
                                                <option value="required" <?= $curMode === 'required' ? 'selected' : '' ?>>Required</option>
                                                <option value="optional" <?= $curMode === 'optional' ? 'selected' : '' ?>>Optional</option>
                                                <option value="hidden"   <?= $curMode === 'hidden'   ? 'selected' : '' ?>>Hidden</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary">Save All Defaults</button>
        </div>
    </form>
</div>
