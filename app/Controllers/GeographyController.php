<?php
namespace App\Controllers;

use App\Helpers\MasterAuditLogger;
use App\Helpers\SpreadsheetImport;
use App\Helpers\View;
use App\Middleware\RoleMiddleware;
use App\Models\District;
use App\Models\State;
use App\Models\Taluk;

class GeographyController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::handle(['institution_admin']);

        $states       = State::all();
        $districts    = District::all();
        $taluks       = Taluk::all();
        $statesActive = State::allActive();

        $this->render('master-data/geography/index', [
            'states'       => $states,
            'districts'    => $districts,
            'taluks'       => $taluks,
            'statesActive' => $statesActive,
            'title'        => 'Geography',
        ]);
    }

    // ---- States ----

    public function storeState(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        $name = (string)$this->input('name', '');

        if ($name === '' || strlen($name) > 100) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'State name is required and must not exceed 100 characters.'];
            $this->redirect('/master-data/geography');
            return;
        }

        if (State::findByName($name)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'A state with that name already exists.'];
            $this->redirect('/master-data/geography');
            return;
        }

        $id = State::create($name);
        MasterAuditLogger::log('create', 'state', $id, ['name' => $name]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'State created successfully.'];
        $this->redirect('/master-data/geography');
    }

    public function updateState(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        $state = State::find($id);
        if (!$state) {
            $this->render('errors/404', ['title' => 'Not Found'], 404);
            exit;
        }

        $name = (string)$this->input('name', '');

        if ($name === '' || strlen($name) > 100) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'State name is required and must not exceed 100 characters.'];
            $this->redirect('/master-data/geography');
            return;
        }

        State::update($id, $name, (string)$state['status']);
        MasterAuditLogger::log('update', 'state', $id, ['name' => $name]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'State updated successfully.'];
        $this->redirect('/master-data/geography');
    }

    public function deactivateState(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        if (State::inUse($id)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Cannot deactivate — state has districts.'];
            $this->redirect('/master-data/geography');
            return;
        }

        State::deactivate($id);
        MasterAuditLogger::log('deactivate', 'state', $id);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'State deactivated.'];
        $this->redirect('/master-data/geography');
    }

    public function reactivateState(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        State::reactivate($id);
        MasterAuditLogger::log('reactivate', 'state', $id);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'State reactivated.'];
        $this->redirect('/master-data/geography');
    }

    // ---- Districts ----

    public function storeDistrict(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        $stateId = (int)$this->input('state_id', 0);
        $name    = (string)$this->input('name', '');

        if ($stateId <= 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'A valid state is required.'];
            $this->redirect('/master-data/geography');
            return;
        }

        if ($name === '' || strlen($name) > 100) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'District name is required and must not exceed 100 characters.'];
            $this->redirect('/master-data/geography');
            return;
        }

        if (District::findByNameAndState($name, $stateId)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'A district with that name already exists in the selected state.'];
            $this->redirect('/master-data/geography');
            return;
        }

        $id = District::create($stateId, $name);
        MasterAuditLogger::log('create', 'district', $id, ['state_id' => $stateId, 'name' => $name]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'District created successfully.'];
        $this->redirect('/master-data/geography');
    }

    public function updateDistrict(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        $district = District::find($id);
        if (!$district) {
            $this->render('errors/404', ['title' => 'Not Found'], 404);
            exit;
        }

        $stateId = (int)$this->input('state_id', 0);
        $name    = (string)$this->input('name', '');

        if ($stateId <= 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'A valid state is required.'];
            $this->redirect('/master-data/geography');
            return;
        }

        if ($name === '' || strlen($name) > 100) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'District name is required and must not exceed 100 characters.'];
            $this->redirect('/master-data/geography');
            return;
        }

        District::update($id, $stateId, $name, (string)$district['status']);
        MasterAuditLogger::log('update', 'district', $id, ['state_id' => $stateId, 'name' => $name]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'District updated successfully.'];
        $this->redirect('/master-data/geography');
    }

    public function deactivateDistrict(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        if (District::inUse($id)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Cannot deactivate — district has taluks.'];
            $this->redirect('/master-data/geography');
            return;
        }

        District::deactivate($id);
        MasterAuditLogger::log('deactivate', 'district', $id);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'District deactivated.'];
        $this->redirect('/master-data/geography');
    }

    // ---- Taluks ----

    public function storeTaluk(): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        $districtId = (int)$this->input('district_id', 0);
        $name       = (string)$this->input('name', '');

        if ($districtId <= 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'A valid district is required.'];
            $this->redirect('/master-data/geography');
            return;
        }

        if ($name === '' || strlen($name) > 100) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Taluk name is required and must not exceed 100 characters.'];
            $this->redirect('/master-data/geography');
            return;
        }

        if (Taluk::findByNameAndDistrict($name, $districtId)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'A taluk with that name already exists in the selected district.'];
            $this->redirect('/master-data/geography');
            return;
        }

        $id = Taluk::create($districtId, $name);
        MasterAuditLogger::log('create', 'taluk', $id, ['district_id' => $districtId, 'name' => $name]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Taluk created successfully.'];
        $this->redirect('/master-data/geography');
    }

    public function updateTaluk(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        $taluk = Taluk::find($id);
        if (!$taluk) {
            $this->render('errors/404', ['title' => 'Not Found'], 404);
            exit;
        }

        $districtId = (int)$this->input('district_id', 0);
        $name       = (string)$this->input('name', '');

        if ($districtId <= 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'A valid district is required.'];
            $this->redirect('/master-data/geography');
            return;
        }

        if ($name === '' || strlen($name) > 100) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Taluk name is required and must not exceed 100 characters.'];
            $this->redirect('/master-data/geography');
            return;
        }

        Taluk::update($id, $districtId, $name, (string)$taluk['status']);
        MasterAuditLogger::log('update', 'taluk', $id, ['district_id' => $districtId, 'name' => $name]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Taluk updated successfully.'];
        $this->redirect('/master-data/geography');
    }

    public function deactivateTaluk(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        Taluk::deactivate($id);
        MasterAuditLogger::log('deactivate', 'taluk', $id);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Taluk deactivated.'];
        $this->redirect('/master-data/geography');
    }

    // ---- Import ----

    public function import(): void
    {
        RoleMiddleware::handle(['institution_admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->render('master-data/geography/import', [
                'title'  => 'Import Geography',
                'result' => null,
            ]);
            return;
        }

        $this->requireCsrf();

        // Validate upload
        $file = $_FILES['geography_file'] ?? null;

        if (
            !$file
            || !isset($file['error'])
            || $file['error'] !== UPLOAD_ERR_OK
            || pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION) !== 'xlsx'
            || (int)($file['size'] ?? 0) > 5 * 1024 * 1024
        ) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please upload a valid .xlsx file (max 5 MB).'];
            $this->redirect('/master-data/geography/import');
            return;
        }

        $tmp = sys_get_temp_dir() . '/' . uniqid('geo_') . '.xlsx';
        move_uploaded_file($file['tmp_name'], $tmp);

        $result = SpreadsheetImport::parseGeography($tmp);
        @unlink($tmp);

        $created      = 0;
        $skipped      = 0;
        $importErrors = $result['errors'];

        foreach ($result['rows'] as $row) {
            // Upsert state
            $state = State::findByName($row['state']);
            if (!$state) {
                $stateId = State::create($row['state']);
                $created++;
            } else {
                $stateId = (int)$state['id'];
                $skipped++;
            }

            if ($row['district'] !== '') {
                $district = District::findByNameAndState($row['district'], $stateId);
                if (!$district) {
                    $districtId = District::create($stateId, $row['district']);
                    $created++;
                } else {
                    $districtId = (int)$district['id'];
                    $skipped++;
                }

                if ($row['taluk'] !== '') {
                    $taluk = Taluk::findByNameAndDistrict($row['taluk'], $districtId);
                    if (!$taluk) {
                        Taluk::create($districtId, $row['taluk']);
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        $this->render('master-data/geography/import', [
            'title'  => 'Import Geography',
            'result' => compact('created', 'skipped', 'importErrors'),
        ]);
    }
}
