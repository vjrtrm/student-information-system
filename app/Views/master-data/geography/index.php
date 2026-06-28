<?php
use App\Helpers\Csrf;
use App\Helpers\View;
/** @var array $states */
/** @var array $districts */
/** @var array $taluks */
/** @var array $statesActive */
/** @var string $title */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Geography</h1>
    <a href="/master-data/geography/import" class="btn btn-outline-secondary btn-sm">
        Import from Excel
    </a>
</div>

<ul class="nav nav-tabs mb-4" id="geoTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="states-tab" data-bs-toggle="tab"
                data-bs-target="#states-pane" type="button" role="tab"
                aria-controls="states-pane" aria-selected="true">
            States <span class="badge bg-secondary ms-1"><?= count($states) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="districts-tab" data-bs-toggle="tab"
                data-bs-target="#districts-pane" type="button" role="tab"
                aria-controls="districts-pane" aria-selected="false">
            Districts <span class="badge bg-secondary ms-1"><?= count($districts) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="taluks-tab" data-bs-toggle="tab"
                data-bs-target="#taluks-pane" type="button" role="tab"
                aria-controls="taluks-pane" aria-selected="false">
            Taluks <span class="badge bg-secondary ms-1"><?= count($taluks) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content" id="geoTabContent">

    <!-- ===== STATES TAB ===== -->
    <div class="tab-pane fade show active" id="states-pane" role="tabpanel" aria-labelledby="states-tab">

        <div class="card mb-3">
            <div class="card-header fw-semibold">Add State</div>
            <div class="card-body">
                <form method="POST" action="/master-data/geography/states" class="row g-2 align-items-end">
                    <?= Csrf::field() ?>
                    <div class="col-md-6">
                        <label for="state-name" class="form-label">State Name</label>
                        <input type="text" id="state-name" name="name" class="form-control"
                               maxlength="100" required placeholder="e.g. Tamil Nadu">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Add State</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($states)): ?>
        <div class="alert alert-info">No states found.</div>
        <?php else: ?>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($states as $state): ?>
                    <tr>
                        <td>
                            <?= View::e($state['name']) ?>
                            <!-- Inline edit toggle -->
                            <button class="btn btn-link btn-sm py-0 ms-1"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#state-edit-<?= (int)$state['id'] ?>"
                                    aria-expanded="false">
                                Edit
                            </button>
                            <div class="collapse mt-2" id="state-edit-<?= (int)$state['id'] ?>">
                                <form method="POST"
                                      action="/master-data/geography/states/<?= (int)$state['id'] ?>"
                                      class="row g-2 align-items-end">
                                    <?= Csrf::field() ?>
                                    <div class="col-md-6">
                                        <input type="text" name="name" class="form-control form-control-sm"
                                               value="<?= View::e($state['name']) ?>" maxlength="100" required>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                        <td>
                            <?php if ($state['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($state['status'] === 'active'): ?>
                            <form method="POST"
                                  action="/master-data/geography/states/<?= (int)$state['id'] ?>/deactivate"
                                  class="d-inline"
                                  onsubmit="return confirm('Deactivate this state?')">
                                <?= Csrf::field() ?>
                                <button class="btn btn-sm btn-danger">Deactivate</button>
                            </form>
                            <?php else: ?>
                            <form method="POST"
                                  action="/master-data/geography/states/<?= (int)$state['id'] ?>/reactivate"
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
        <?php endif; ?>
    </div>

    <!-- ===== DISTRICTS TAB ===== -->
    <div class="tab-pane fade" id="districts-pane" role="tabpanel" aria-labelledby="districts-tab">

        <div class="card mb-3">
            <div class="card-header fw-semibold">Add District</div>
            <div class="card-body">
                <form method="POST" action="/master-data/geography/districts" class="row g-2 align-items-end">
                    <?= Csrf::field() ?>
                    <div class="col-md-4">
                        <label for="district-state" class="form-label">State</label>
                        <select id="district-state" name="state_id" class="form-select" required>
                            <option value="">-- Select State --</option>
                            <?php foreach ($statesActive as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= View::e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="district-name" class="form-label">District Name</label>
                        <input type="text" id="district-name" name="name" class="form-control"
                               maxlength="100" required placeholder="e.g. Coimbatore">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Add District</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($districts)): ?>
        <div class="alert alert-info">No districts found.</div>
        <?php else: ?>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>State</th>
                            <th>District</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($districts as $dist): ?>
                    <tr>
                        <td><?= View::e($dist['state_name']) ?></td>
                        <td>
                            <?= View::e($dist['name']) ?>
                            <button class="btn btn-link btn-sm py-0 ms-1"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#dist-edit-<?= (int)$dist['id'] ?>"
                                    aria-expanded="false">
                                Edit
                            </button>
                            <div class="collapse mt-2" id="dist-edit-<?= (int)$dist['id'] ?>">
                                <form method="POST"
                                      action="/master-data/geography/districts/<?= (int)$dist['id'] ?>"
                                      class="row g-2 align-items-end">
                                    <?= Csrf::field() ?>
                                    <div class="col-md-5">
                                        <select name="state_id" class="form-select form-select-sm" required>
                                            <?php foreach ($statesActive as $s): ?>
                                            <option value="<?= (int)$s['id'] ?>"
                                                <?= $s['id'] == $dist['state_id'] ? 'selected' : '' ?>>
                                                <?= View::e($s['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" name="name" class="form-control form-control-sm"
                                               value="<?= View::e($dist['name']) ?>" maxlength="100" required>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                        <td>
                            <?php if ($dist['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($dist['status'] === 'active'): ?>
                            <form method="POST"
                                  action="/master-data/geography/districts/<?= (int)$dist['id'] ?>/deactivate"
                                  class="d-inline"
                                  onsubmit="return confirm('Deactivate this district?')">
                                <?= Csrf::field() ?>
                                <button class="btn btn-sm btn-danger">Deactivate</button>
                            </form>
                            <?php else: ?>
                            <form method="POST"
                                  action="/master-data/geography/districts/<?= (int)$dist['id'] ?>/reactivate"
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
        <?php endif; ?>
    </div>

    <!-- ===== TALUKS TAB ===== -->
    <div class="tab-pane fade" id="taluks-pane" role="tabpanel" aria-labelledby="taluks-tab">

        <div class="card mb-3">
            <div class="card-header fw-semibold">Add Taluk</div>
            <div class="card-body">
                <form method="POST" action="/master-data/geography/taluks" class="row g-2 align-items-end">
                    <?= Csrf::field() ?>
                    <div class="col-md-3">
                        <label for="taluk-state-select" class="form-label">State</label>
                        <select id="taluk-state-select" class="form-select" name="state_id">
                            <option value="">-- Select State --</option>
                            <?php foreach ($statesActive as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= View::e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="taluk-district-select" class="form-label">District</label>
                        <select id="taluk-district-select" name="district_id" class="form-select" required>
                            <option value="">-- Select District --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="taluk-name" class="form-label">Taluk Name</label>
                        <input type="text" id="taluk-name" name="name" class="form-control"
                               maxlength="100" required placeholder="e.g. Pollachi">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Add Taluk</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($taluks)): ?>
        <div class="alert alert-info">No taluks found.</div>
        <?php else: ?>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>District</th>
                            <th>Taluk</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($taluks as $taluk): ?>
                    <tr>
                        <td><?= View::e($taluk['district_name']) ?></td>
                        <td>
                            <?= View::e($taluk['name']) ?>
                            <button class="btn btn-link btn-sm py-0 ms-1"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#taluk-edit-<?= (int)$taluk['id'] ?>"
                                    aria-expanded="false">
                                Edit
                            </button>
                            <div class="collapse mt-2" id="taluk-edit-<?= (int)$taluk['id'] ?>">
                                <form method="POST"
                                      action="/master-data/geography/taluks/<?= (int)$taluk['id'] ?>"
                                      class="row g-2 align-items-end">
                                    <?= Csrf::field() ?>
                                    <div class="col-md-5">
                                        <input type="text" name="name" class="form-control form-control-sm"
                                               value="<?= View::e($taluk['name']) ?>" maxlength="100" required>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                        <td>
                            <?php if ($taluk['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($taluk['status'] === 'active'): ?>
                            <form method="POST"
                                  action="/master-data/geography/taluks/<?= (int)$taluk['id'] ?>/deactivate"
                                  class="d-inline"
                                  onsubmit="return confirm('Deactivate this taluk?')">
                                <?= Csrf::field() ?>
                                <button class="btn btn-sm btn-danger">Deactivate</button>
                            </form>
                            <?php else: ?>
                            <form method="POST"
                                  action="/master-data/geography/taluks/<?= (int)$taluk['id'] ?>/reactivate"
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
        <?php endif; ?>
    </div>

</div>

<script>
document.getElementById('taluk-state-select')?.addEventListener('change', function() {
    const stateId = this.value;
    const sel = document.getElementById('taluk-district-select');
    sel.innerHTML = '<option value="">-- Select District --</option>';
    if (!stateId) return;
    fetch('/lookup/districts?state_id=' + encodeURIComponent(stateId))
        .then(r => r.json())
        .then(data => {
            data.forEach(d => {
                sel.insertAdjacentHTML('beforeend',
                    `<option value="${d.id}">${d.name}</option>`
                );
            });
        })
        .catch(() => {
            sel.innerHTML = '<option value="">-- Error loading districts --</option>';
        });
});
</script>
