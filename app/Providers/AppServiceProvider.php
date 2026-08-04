<?php

namespace App\Providers;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\CannibalRequest;
use App\Models\CancellationRequest;
use App\Models\Component;
use App\Models\DmbdEntry;
use App\Models\InterchangeMap;
use App\Models\OverbudgetRequest;
use App\Models\PlantRequest;
use App\Models\RequestApproval;
use App\Models\TabulationBid;
use App\Models\User;
use App\Policies\CancellationRequestPolicy;
use App\Policies\CannibalRequestPolicy;
use App\Policies\ComponentPolicy;
use App\Policies\DmbdEntryPolicy;
use App\Policies\InterchangeMapPolicy;
use App\Policies\OverbudgetRequestPolicy;
use App\Policies\PlantRequestPolicy;
use App\Policies\ReportPolicy;
use App\Policies\RequestApprovalPolicy;
use App\Policies\TabulationBidPolicy;
use App\Services\Sap\SapService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SapService::class);
    }

    public function boot(): void
    {
        Gate::policy(Role::class, \App\Policies\RolePolicy::class);
        Gate::policy(BudgetPeriod::class, \App\Policies\BudgetPeriodPolicy::class);
        Gate::policy(BudgetAllocation::class, \App\Policies\BudgetAllocationPolicy::class);
        Gate::policy(PlantRequest::class, PlantRequestPolicy::class);
        Gate::policy(RequestApproval::class, RequestApprovalPolicy::class);
        Gate::policy(TabulationBid::class, TabulationBidPolicy::class);
        Gate::policy(DmbdEntry::class, DmbdEntryPolicy::class);
        Gate::policy(OverbudgetRequest::class, OverbudgetRequestPolicy::class);
        Gate::policy(InterchangeMap::class, InterchangeMapPolicy::class);
        Gate::policy(CancellationRequest::class, CancellationRequestPolicy::class);
        Gate::policy(Component::class, ComponentPolicy::class);
        Gate::policy(CannibalRequest::class, CannibalRequestPolicy::class);

        Gate::define('viewSapDashboard', fn (User $user) => $user->hasRole('it_manager')
            || $user->hasRole('procurement_manager')
            || $user->hasRole('finance_director'));

        Gate::define('viewBudgetConsumption', [ReportPolicy::class, 'viewBudgetConsumption']);
        Gate::define('exportBudgetConsumption', [ReportPolicy::class, 'exportBudgetConsumption']);
        Gate::define('viewVendorPerformance', [ReportPolicy::class, 'viewVendorPerformance']);
        Gate::define('exportVendorPerformance', [ReportPolicy::class, 'exportVendorPerformance']);
        Gate::define('viewEquipmentCost', [ReportPolicy::class, 'viewEquipmentCost']);
        Gate::define('exportEquipmentCost', [ReportPolicy::class, 'exportEquipmentCost']);
    }
}
