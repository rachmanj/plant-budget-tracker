<?php

namespace App\Services\Budget;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\PlantRequest;
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

        $allocation = $period->allocations->first();

        if (! $allocation) {
            return collect();
        }

        $projectRow = array_merge(
            [
                'scope' => 'project',
                'allocation_id' => $allocation->id,
                'equipment_id' => null,
                'unit_code_cache' => null,
                'plant_type_cache' => null,
            ],
            $this->forAllocation($allocation)
        );

        $equipmentRows = $this->equipmentVarianceFromRequests($allocation);

        return collect([$projectRow])->concat($equipmentRows);
    }

    public function forPlantType(string $projectCode, string $plantType, Carbon $month): array
    {
        $items = $this->forProject($projectCode, $month)
            ->filter(fn (array $row) => ($row['scope'] ?? '') === 'equipment')
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

    /**
     * @return list<array<string, mixed>>
     */
    private function equipmentVarianceFromRequests(BudgetAllocation $allocation): array
    {
        $requests = PlantRequest::query()
            ->where('budget_allocation_id', $allocation->id)
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->get();

        return $requests
            ->groupBy('equipment_id')
            ->map(function (Collection $group, $equipmentId) {
                $latest = $group->sortByDesc('id')->first();
                $requestTotal = number_format((float) $group->sum('estimated_total'), 2, '.', '');
                $receivedTotal = number_format(
                    (float) $group->where('status', 'received')->sum('estimated_total'),
                    2,
                    '.',
                    ''
                );

                return [
                    'scope' => 'equipment',
                    'allocation_id' => null,
                    'equipment_id' => (int) $equipmentId,
                    'unit_code_cache' => $latest->unit_code_cache,
                    'plant_type_cache' => null,
                    'allocated' => '0.00',
                    'carry_forward_in' => '0.00',
                    'committed' => $requestTotal,
                    'actual' => $receivedTotal,
                    'variance' => bcsub('0.00', bcadd($requestTotal, $receivedTotal, 2), 2),
                    'utilization_pct' => '0.00',
                    'tolerance_cap' => '0.00',
                ];
            })
            ->values()
            ->all();
    }
}
