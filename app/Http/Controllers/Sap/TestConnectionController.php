<?php

namespace App\Http\Controllers\Sap;

use App\Http\Controllers\Controller;
use App\Services\Sap\SapReadRepository;
use App\Services\Sap\SapService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TestConnectionController extends Controller
{
    public function index(SapService $sapService): Response
    {
        $this->authorize('viewSapDashboard');

        $serviceLayerOk = false;
        $sqlOk = false;

        try {
            $serviceLayerOk = $sapService->login();
        } catch (\Throwable) {
            $serviceLayerOk = false;
        }

        try {
            DB::connection('sap_sql')->select('SELECT 1 AS test');
            $sqlOk = true;
        } catch (\Throwable) {
            $sqlOk = false;
        }

        return Inertia::render('Sap/SyncDashboard', [
            'connectionTest' => [
                'service_layer' => $serviceLayerOk,
                'sql_server' => $sqlOk,
            ],
        ]);
    }
}
