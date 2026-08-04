<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $widgets = [];

        if ($user->can('budget.view')) {
            $widgets[] = ['key' => 'budget', 'title' => 'Anggaran', 'description' => 'Ringkasan anggaran plant (Phase 1)'];
        }

        if ($user->can('plant_request.create')) {
            $widgets[] = ['key' => 'plant_request', 'title' => 'Plant Request', 'description' => 'Permintaan suku cadang (Phase 2)'];
        }

        if ($user->can('user.manage')) {
            $widgets[] = ['key' => 'admin', 'title' => 'Administrasi', 'description' => 'Pengguna, role, dan proyek'];
        }

        if ($user->can('reports.view')) {
            $widgets[] = ['key' => 'reports', 'title' => 'Laporan', 'description' => 'Analitik & laporan (Phase 7)'];
        }

        return Inertia::render('Dashboard', [
            'widgets' => $widgets,
            'roleNames' => $user->getRoleNames(),
        ]);
    }
}
