<?php

use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CannibalController;
use App\Http\Controllers\CancellationController;
use App\Http\Controllers\ComponentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DmbdController;
use App\Http\Controllers\InterchangeController;
use App\Http\Controllers\OverbudgetController;
use App\Http\Controllers\PlantRequestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Sap\SyncDashboardController;
use App\Http\Controllers\Sap\TestConnectionController;
use App\Http\Controllers\TabulationBidController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('can:budget.view')->prefix('budget')->name('budget.')->group(function () {
        Route::get('/', [BudgetController::class, 'index'])->name('index');
        Route::get('/variance', [BudgetController::class, 'variance'])->name('variance');

        Route::middleware('role:finance_director')->group(function () {
            Route::get('/setting', [BudgetController::class, 'setting'])->name('setting');
            Route::post('/', [BudgetController::class, 'store'])->name('store');
            Route::post('/{period}/carry-forward', [BudgetController::class, 'carryForward'])->name('carry-forward');
            Route::patch('/allocations/{allocation}', [BudgetController::class, 'revise'])->name('allocations.revise');
        });
    });

    Route::middleware('EnsureProjectScope')->group(function () {
        Route::get('/plant-requests', [PlantRequestController::class, 'index'])->name('plant-requests.index');
        Route::get('/plant-requests/create', [PlantRequestController::class, 'create'])->name('plant-requests.create');
        Route::post('/plant-requests', [PlantRequestController::class, 'store'])->name('plant-requests.store');
        Route::get('/plant-requests/{plantRequest}', [PlantRequestController::class, 'show'])->name('plant-requests.show');
        Route::post('/plant-requests/{plantRequest}/submit', [PlantRequestController::class, 'submit'])->name('plant-requests.submit');
        Route::post('/plant-requests/{plantRequest}/cancel', [CancellationController::class, 'store'])->name('plant-requests.cancel');

        Route::get('/dmbd', [DmbdController::class, 'index'])->name('dmbd.index');
        Route::post('/dmbd', [DmbdController::class, 'store'])->name('dmbd.store');
        Route::get('/dmbd/{dmbdEntry}/prefill-request', [DmbdController::class, 'prefillRequest'])->name('dmbd.prefill-request');
    });

    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/{approval}/decide', [ApprovalController::class, 'decide'])->name('approvals.decide');

    Route::get('/tabulation-bids', [TabulationBidController::class, 'index'])->name('tabulation-bids.index');
    Route::get('/tabulation-bids/create', [TabulationBidController::class, 'create'])->name('tabulation-bids.create');
    Route::post('/tabulation-bids', [TabulationBidController::class, 'store'])->name('tabulation-bids.store');
    Route::get('/tabulation-bids/{tabulationBid}', [TabulationBidController::class, 'show'])->name('tabulation-bids.show');
    Route::get('/tabulation-bids/{tabulationBid}/review', [TabulationBidController::class, 'review'])->name('tabulation-bids.review');
    Route::post('/tabulation-bids/{tabulationBid}/award', [TabulationBidController::class, 'award'])->name('tabulation-bids.award');
    Route::post('/tabulation-bids/{tabulationBid}/create-po', [TabulationBidController::class, 'createPo'])->name('tabulation-bids.create-po');

    Route::get('/overbudget', [OverbudgetController::class, 'index'])->name('overbudget.index');
    Route::get('/overbudget/create', [OverbudgetController::class, 'create'])->name('overbudget.create');
    Route::post('/overbudget', [OverbudgetController::class, 'store'])->name('overbudget.store');

    Route::get('/cancellation', [CancellationController::class, 'index'])->name('cancellation.index');
    Route::post('/cancellation-requests/{cancellationRequest}/agree', [CancellationController::class, 'agree'])->name('cancellation.agree');

    Route::get('/interchange', [InterchangeController::class, 'index'])->name('interchange.index');
    Route::post('/interchange', [InterchangeController::class, 'store'])->name('interchange.store');
    Route::post('/interchange/{interchangeMap}/signoff', [InterchangeController::class, 'signoff'])->name('interchange.signoff');

    Route::middleware('can:viewSapDashboard')->prefix('sap')->name('sap.')->group(function () {
        Route::get('/sync-dashboard', [SyncDashboardController::class, 'index'])->name('sync-dashboard');
        Route::post('/sync-dashboard/{sapSyncLog}/retry', [SyncDashboardController::class, 'retry'])->name('sync-dashboard.retry');
        Route::get('/test-connection', [TestConnectionController::class, 'index'])->name('test-connection');
    });

    Route::middleware('can:reports.view')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/budget-consumption', [ReportController::class, 'budgetConsumption'])->name('budget-consumption');
        Route::get('/vendor-performance', [ReportController::class, 'vendorPerformance'])->name('vendor-performance');
        Route::get('/equipment-cost', [ReportController::class, 'equipmentCost'])->name('equipment-cost');
        Route::get('/{reportType}/export/pdf', [ReportController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/{reportType}/export/csv', [ReportController::class, 'exportCsv'])->name('export.csv');
    });

    Route::middleware('feature:cannibal_beta')->group(function () {
        Route::get('/components', [ComponentController::class, 'index'])->name('components.index');
        Route::post('/components', [ComponentController::class, 'store'])->name('components.store');
        Route::patch('/components/{component}/status', [ComponentController::class, 'updateStatus'])->name('components.update-status');

        Route::get('/cannibal-requests', [CannibalController::class, 'index'])->name('cannibal-requests.index');
        Route::get('/cannibal-requests/create', [CannibalController::class, 'create'])->name('cannibal-requests.create');
        Route::post('/cannibal-requests', [CannibalController::class, 'store'])->name('cannibal-requests.store');
    });

    Route::middleware('can:manage,'.\Spatie\Permission\Models\Role::class)->prefix('admin')->name('admin.')->group(function () {
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/projects/sync', [ProjectController::class, 'sync'])->name('projects.sync');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/roles', [UserController::class, 'assignRole'])->name('users.assign-role');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.sync-permissions');
    });
});
