<?php

namespace App\Services\Budget;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VarianceCalculator
{
    public function forAllocation(BudgetAllocation $allocation): array
    {
        $allocation->refresh();

        return [
            'allocated' => (string) $allocation->allocated_amount,
            'carry_forward_in' => (string) $allocation->carry_forward_in,
            'committed' => (string) $allocation->committed_amount,
            'actual' => (string) $allocation->actual_amount,
            'variance' => $allocation->variance,
            'utilization_pct' => $allocation->utilization_pct,
            'tolerance_cap' => $allocation->tolerance_cap,
        ];
    }

    public function forProject(string $projectCode, Carbon $month): Collection
    {
        $period = BudgetPeriod::query()
            ->where('project_code', $projectCode)
            ->whereDate('period_month', $month->copy()->startOfMonth())
            ->with('allocations')
            ->first();

        if (! $period) {
            return collect();
        }

        return $period->allocations->map(fn (BudgetAllocation $allocation) => array_merge(
            [
                'allocation_id' => $allocation->id,
                'equipment_id' => $allocation->equipment_id,
                'plant_type_cache' => $allocation->plant_type_cache,
            ],
            $this->forAllocation($allocation)
        ));
    }

    public function forPlantType(string $projectCode, string $plantType, Carbon $month): array
    {
        $items = $this->forProject($projectCode, $month)
            ->filter(fn (array $row) => ($row['plant_type_cache'] ?? null) === $plantType);

        $totals = [
            'allocated' => '0.00',
            'carry_forward_in' => '0.00',
            'committed' => '0.00',
            'actual' => '0.00',
        ];

        foreach ($items as $item) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] = bcadd($totals[$key], $item[$key] ?? '0.00', 2);
            }
        }

        $totals['variance'] = bcsub(
            bcadd($totals['allocated'], $totals['carry_forward_in'], 2),
            bcadd($totals['committed'], $totals['actual'], 2),
            2
        );

        $base = bcadd($totals['allocated'], $totals['carry_forward_in'], 2);
        $totals['utilization_pct'] = bccomp($base, '0', 2) === 0
            ? '0.00'
            : bcmul(
                bcdiv(bcadd($totals['committed'], $totals['actual'], 2), $base, 4),
                '100',
                2
            );

        return $totals;
    }
}
