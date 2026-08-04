<?php

namespace App\Policies;

use App\Models\BudgetPeriod;
use App\Models\User;

class BudgetPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('budget.view');
    }

    public function view(User $user, BudgetPeriod $period): bool
    {
        return $user->can('budget.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('finance_director');
    }

    public function manage(User $user, ?BudgetPeriod $period = null): bool
    {
        return $user->hasRole('finance_director');
    }

    public function carryForward(User $user, BudgetPeriod $period): bool
    {
        return $user->hasRole('finance_director');
    }
}
