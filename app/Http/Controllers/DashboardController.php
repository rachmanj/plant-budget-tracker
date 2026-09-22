<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardMetrics;
use App\Support\ProjectContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardMetrics $dashboardMetrics,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

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

        $projectCode = ProjectContext::resolve($request);

        return Inertia::render('Dashboard', [
            'widgets' => $widgets,
            'roleNames' => $user->getRoleNames(),
            'metrics' => $this->dashboardMetrics->for($user, $projectCode),
            'projectCode' => $projectCode,
            'today' => now()->locale('id')->translatedFormat('d M Y'),
            'can' => [
                'budget.view' => $user->can('budget.view'),
                'plant_request.create' => $user->can('plant_request.create'),
                'dmbd.view' => $user->can('dmbd.view'),
                'tabulation_bid.view' => $user->can('tabulation_bid.create') || $user->can('tabulation_bid.review'),
                'reports.view' => $user->can('reports.view'),
                'user.manage' => $user->can('user.manage'),
            ],
        ]);
    }
}
