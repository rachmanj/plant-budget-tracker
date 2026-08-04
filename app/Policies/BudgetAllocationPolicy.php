<?php

namespace App\Policies;

use App\Models\BudgetAllocation;
use App\Models\User;

class BudgetAllocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('budget.view');
    }

    public function view(User $user, BudgetAllocation $allocation): bool
    {
        return $user->can('budget.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('finance_director');
    }

    public function update(User $user, BudgetAllocation $allocation): bool
    {
        return $user->hasRole('finance_director')
            && $allocation->period->isEditableBy($user);
    }

    public function revise(User $user, BudgetAllocation $allocation): bool
    {
        return $this->update($user, $allocation);
    }
}
