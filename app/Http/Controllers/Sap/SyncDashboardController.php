<?php

namespace App\Http\Controllers\Sap;

use App\Http\Controllers\Controller;
use App\Models\SapSyncLog;
use App\Services\Sap\SapCircuitBreaker;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SyncDashboardController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewSapDashboard');

        $logs = SapSyncLog::query()->latest()->limit(50)->get();
        $breaker = app(SapCircuitBreaker::class);

        return Inertia::render('Sap/SyncDashboard', [
            'logs' => $logs,
            'circuitBreaker' => [
                'service_layer' => $breaker->isOpen('service_layer'),
                'sql_server' => $breaker->isOpen('sql_server'),
            ],
        ]);
    }

    public function retry(SapSyncLog $sapSyncLog): RedirectResponse
    {
        $this->authorize('viewSapDashboard');

        if ($sapSyncLog->status !== 'failed') {
            return back()->with('error', 'Only failed syncs can be retried.');
        }

        $sapSyncLog->update(['status' => 'pending', 'attempts' => 0]);

        return back()->with('success', 'Sync retry queued.');
    }
}
