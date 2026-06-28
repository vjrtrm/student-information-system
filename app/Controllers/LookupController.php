<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\District;
use App\Models\Taluk;

class LookupController extends Controller
{
    public function districts(): void
    {
        AuthMiddleware::handle();
        $stateId = (int)($_GET['state_id'] ?? 0);
        $rows    = District::byState($stateId, true);
        header('Content-Type: application/json');
        echo json_encode($rows);
        exit;
    }

    public function taluks(): void
    {
        AuthMiddleware::handle();
        $districtId = (int)($_GET['district_id'] ?? 0);
        $rows       = Taluk::byDistrict($districtId, true);
        header('Content-Type: application/json');
        echo json_encode($rows);
        exit;
    }
}
