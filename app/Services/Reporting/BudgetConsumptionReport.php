<?php

namespace App\Services\Reporting;

use App\Models\BudgetAllocation;
use App\Models\BudgetLedger;
use App\Models\BudgetPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BudgetConsumptionReport
{
    public function byProject(string $projectCode, Carbon $month): array
    {
        $period = BudgetPeriod::query()
            ->where('project_code', $projectCode)
            ->whereDate('period_month', $month->copy()->startOfMonth())
            ->first();

        if (! $period) {
            return [];
        }

        return $period->allocations->map(fn (BudgetAllocation $a) => $this->allocationSummary($a))->all();
    }

    public function byEquipment(int $equipmentId, Carbon $month): array
    {
        $allocation = BudgetAllocation::query()
            ->where('equipment_id', $equipmentId)
            ->whereHas('period', fn ($q) => $q->whereDate('period_month', $month->copy()->startOfMonth()))
            ->first();

        return $allocation ? $this->allocationSummary($allocation) : [];
    }

    public function byPlantType(string $projectCode, string $plantType, Carbon $month): array
    {
        $period = BudgetPeriod::query()
            ->where('project_code', $projectCode)
            ->whereDate('period_month', $month->copy()->startOfMonth())
            ->first();

        if (! $period) {
            return [];
        }

        return $period->allocations()
            ->where('plant_type_cache', $plantType)
            ->get()
            ->map(fn (BudgetAllocation $a) => $this->allocationSummary($a))
            ->all();
    }

    public function rollingSixMonth(string $projectCode): Collection
    {
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $months->push([
                'month' => $month->format('Y-m'),
                'data' => $this->byProject($projectCode, $month),
            ]);
        }

        return $months;
    }

    private function allocationSummary(BudgetAllocation $allocation): array
    {
        $ledgers = BudgetLedger::query()
            ->where('budget_allocation_id', $allocation->id)
            ->get()
            ->groupBy('entry_type');

        $sum = fn (string $type) => number_format(
            (float) ($ledgers->get($type)?->sum('amount') ?? 0),
            2,
            '.',
            ''
        );

        return [
            'allocation_id' => $allocation->id,
            'equipment_id' => $allocation->equipment_id,
            'unit_code' => $allocation->unit_code_cache,
            'allocated' => $sum('allocation'),
            'committed' => $sum('commitment'),
            'actual' => $sum('actual'),
            'carry_forward' => $sum('carry_forward'),
            'variance' => $allocation->variance,
        ];
    }
}
