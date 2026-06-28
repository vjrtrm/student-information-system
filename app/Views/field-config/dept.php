<?php
use App\Helpers\Auth;
use App\Helpers\Csrf;
// Variables: $bySection, $sections, $departments, $dept, $deptId, $editable, $customFields, $title
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Field Configuration — <?= htmlspecialchars($dept['name']) ?></h4>
        <div class="d-flex gap-2 align-items-center">
            <?php if (Auth::role() === 'institution_admin'): ?>
            <a href="/field-config" class="btn btn-outline-secondary btn-sm">Institution Defaults</a>
            <select class="form-select form-select-sm w-auto" onchange="if(this.value) window.location='/field-config/'+this.value">
                <option value="">Other department...</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= (int)$d['id'] === (int)$deptId ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" action="/field-config/<?= (int)$deptId ?>">
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
                                    <th width="150">Institution Default</th>
                                    <th width="230">Department Setting</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bySection[$sectionName] as $key => $field): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($field['label']) ?>
                                            <?php if ($field['has_override']): ?>
                                                <span class="badge bg-info text-dark ms-1">Dept override</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted ms-1">Institution default</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $im = $field['inst_mode']; ?>
                                            <span class="badge bg-<?= $im === 'required' ? 'danger' : ($im === 'hidden' ? 'secondary' : 'success') ?>">
                                                <?= ucfirst($im) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php $dm = $field['dept_mode']; ?>
                                            <?php if ($editable): ?>
                                                <select name="mode[<?= htmlspecialchars($key) ?>]" class="form-select form-select-sm">
                                                    <option value="use_default" <?= $dm === 'use_default' ? 'selected' : '' ?>>Use Default</option>
                                                    <option value="required"    <?= $dm === 'required'    ? 'selected' : '' ?>>Required</option>
                                                    <option value="optional"    <?= $dm === 'optional'    ? 'selected' : '' ?>>Optional</option>
                                                    <option value="hidden"      <?= $dm === 'hidden'      ? 'selected' : '' ?>>Hidden</option>
                                                </select>
                                            <?php else: ?>
                                                <span class="badge bg-<?= $dm === 'use_default' ? 'light text-muted' : ($dm === 'required' ? 'danger' : ($dm === 'hidden' ? 'secondary' : 'success')) ?>">
                                                    <?= $dm === 'use_default' ? 'Use Default' : ucfirst($dm) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($editable): ?>
        <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetModal">
                Reset to Defaults
            </button>
            <button type="submit" class="btn btn-primary">Save Department Settings</button>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php if ($editable): ?>
<!-- Reset confirmation modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Department Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to reset all department overrides for <strong><?= htmlspecialchars($dept['name']) ?></strong>?
                Fields will revert to institution-wide defaults.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="/field-config/<?= (int)$deptId ?>/reset" style="display:inline">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-danger">Reset to Defaults</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
