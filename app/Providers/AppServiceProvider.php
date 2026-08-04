<?php

namespace App\Providers;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Role::class, \App\Policies\RolePolicy::class);
        Gate::policy(BudgetPeriod::class, \App\Policies\BudgetPeriodPolicy::class);
        Gate::policy(BudgetAllocation::class, \App\Policies\BudgetAllocationPolicy::class);
    }
}
