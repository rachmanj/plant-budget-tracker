<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    public function viewBudgetConsumption(User $user): bool
    {
        return $user->can('reports.view');
    }

    public function exportBudgetConsumption(User $user): bool
    {
        return $user->can('reports.export');
    }

    public function viewVendorPerformance(User $user): bool
    {
        return $user->can('reports.view');
    }

    public function exportVendorPerformance(User $user): bool
    {
        return $user->can('reports.export');
    }

    public function viewEquipmentCost(User $user): bool
    {
        return $user->can('reports.view');
    }

    public function exportEquipmentCost(User $user): bool
    {
        return $user->can('reports.export');
    }
}
