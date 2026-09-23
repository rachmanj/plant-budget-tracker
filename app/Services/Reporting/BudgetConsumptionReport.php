<?php

namespace App\Services\Reporting;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\PlantRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BudgetConsumptionReport
{
    /**
     * @return array{summary: array<string, mixed>|null, units: list<array<string, mixed>>}
     */
    public function byProject(string $projectCode, Carbon $month): array
    {
        $period = BudgetPeriod::query()
            ->where('project_code', $projectCode)
            ->whereDate('period_month', $month->copy()->startOfMonth())
            ->with('allocations')
            ->first();

        if (! $period) {
            return ['summary' => null, 'units' => []];
        }

        $allocation = $period->allocations->first();

        if (! $allocation) {
            return ['summary' => null, 'units' => []];
        }

        return [
            'summary' => $this->projectSummary($allocation, $projectCode),
            'units' => $this->unitBreakdownFromRequests($allocation),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>|null, units: list<array<string, mixed>>}
     */
    public function byEquipment(int $equipmentId, Carbon $month): array
    {
        $allocation = BudgetAllocation::query()
            ->whereHas('period', fn ($q) => $q->whereDate('period_month', $month->copy()->startOfMonth()))
            ->whereNull('equipment_id')
            ->first();

        if (! $allocation) {
            return ['summary' => null, 'units' => []];
        }

        $units = collect($this->unitBreakdownFromRequests($allocation))
            ->filter(fn (array $row) => (int) ($row['equipment_id'] ?? 0) === $equipmentId)
            ->values()
            ->all();

        return [
            'summary' => $this->projectSummary($allocation, $allocation->period->project_code),
            'units' => $units,
        ];
    }

    /**
     * @return array{summary: array<string, mixed>|null, units: list<array<string, mixed>>}
     */
    public function byPlantType(string $projectCode, string $plantType, Carbon $month): array
    {
        $report = $this->byProject($projectCode, $month);

        if ($report['summary'] === null) {
            return $report;
        }

        $report['units'] = collect($report['units'])
            ->filter(fn (array $row) => ($row['plant_type'] ?? '') === $plantType)
            ->values()
            ->all();

        return $report;
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

    /**
     * @return list<array<string, mixed>>
     */
    private function unitBreakdownFromRequests(BudgetAllocation $allocation): array
    {
        $requests = PlantRequest::query()
            ->where('budget_allocation_id', $allocation->id)
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->orderByDesc('updated_at')
            ->get();

        return $requests
            ->groupBy('equipment_id')
            ->map(function (Collection $group, $equipmentId) {
                $latest = $group->sortByDesc('id')->first();

                return [
                    'equipment_id' => (int) $equipmentId,
                    'unit_code' => $latest->unit_code_cache,
                    'plant_type' => null,
                    'request_count' => $group->count(),
                    'total_estimated' => number_format(
                        (float) $group->sum('estimated_total'),
                        2,
                        '.',
                        ''
                    ),
                    'last_status' => $latest->status,
                ];
            })
            ->sortBy('unit_code')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function projectSummary(BudgetAllocation $allocation, string $projectCode): array
    {
        $allocation->refresh();

        $base = bcadd((string) $allocation->allocated_amount, (string) $allocation->carry_forward_in, 2);
        $spent = bcadd((string) $allocation->committed_amount, (string) $allocation->actual_amount, 2);
        $remaining = bcsub($base, $spent, 2);

        return [
            'allocation_id' => $allocation->id,
            'project_code' => $projectCode,
            'allocated' => number_format((float) $allocation->allocated_amount, 2, '.', ''),
            'carry_forward' => number_format((float) $allocation->carry_forward_in, 2, '.', ''),
            'pagu' => number_format((float) $base, 2, '.', ''),
            'committed' => number_format((float) $allocation->committed_amount, 2, '.', ''),
            'actual' => number_format((float) $allocation->actual_amount, 2, '.', ''),
            'remaining' => number_format((float) $remaining, 2, '.', ''),
            'utilization_pct' => $allocation->utilization_pct,
            'variance' => $allocation->variance,
        ];
    }
}
