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

    public function viewPurchaseRequestStatus(User $user): bool
    {
        return $user->can('reports.view') && $user->can('procurement.view');
    }

    public function exportPurchaseRequestStatus(User $user): bool
    {
        return $user->can('reports.export');
    }

    public function viewPurchaseOrderTrend(User $user): bool
    {
        return $user->can('reports.view') && $user->can('procurement.view');
    }

    public function exportPurchaseOrderTrend(User $user): bool
    {
        return $user->can('reports.export');
    }

    public function viewTopSupplier(User $user): bool
    {
        return $user->can('reports.view') && $user->can('procurement.view');
    }

    public function exportTopSupplier(User $user): bool
    {
        return $user->can('reports.export');
    }

    public function viewApprovalTurnaround(User $user): bool
    {
        return $user->can('reports.view') && $user->can('procurement.view');
    }

    public function exportApprovalTurnaround(User $user): bool
    {
        return $user->can('reports.export');
    }

    public function viewPurchaseRequestByDepartment(User $user): bool
    {
        return $user->can('reports.view') && $user->can('procurement.view');
    }

    public function exportPurchaseRequestByDepartment(User $user): bool
    {
        return $user->can('reports.export');
    }
}
