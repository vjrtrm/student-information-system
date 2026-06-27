<?php
namespace App\Controllers;

use App\Helpers\Auth;

/** Minimal post-login landing (full dashboards arrive in Module 8). */
class DashboardController extends Controller
{
    public function index(): void
    {
        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'role'  => Auth::role(),
            'user'  => Auth::user(),
        ]);
    }
}
