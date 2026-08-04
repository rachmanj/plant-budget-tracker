<?php

namespace App\Services\Reporting;

use App\Models\BudgetAllocation;
use App\Models\BudgetLedger;
use App\Services\Arkfleet\ArkfleetClient;
use Carbon\Carbon;

class EquipmentCostReport
{
    public function __construct(
        private readonly ArkfleetClient $arkfleetClient,
    ) {}

    public function costPerHour(int $equipmentId, Carbon $month): array
    {
        $spend = $this->getActualSpend($equipmentId, $month);
        $deltaHm = $this->getDeltaHm($equipmentId, $month);

        if (bccomp($deltaHm, '0', 2) <= 0) {
            return [
                'equipment_id' => $equipmentId,
                'cost_per_hour' => '0.00',
                'total_spend' => $spend,
                'delta_hm' => $deltaHm,
                'stale' => false,
            ];
        }

        return [
            'equipment_id' => $equipmentId,
            'cost_per_hour' => bcdiv($spend, $deltaHm, 2),
            'total_spend' => $spend,
            'delta_hm' => $deltaHm,
            'stale' => false,
        ];
    }

    public function costPerKm(int $equipmentId, Carbon $month): array
    {
        $spend = $this->getActualSpend($equipmentId, $month);

        try {
            $readings = $this->arkfleetClient->getHmKmReadings($equipmentId, [
                'from' => $month->copy()->startOfMonth()->toDateString(),
                'to' => $month->copy()->endOfMonth()->toDateString(),
            ]);
            $deltaKm = $this->calculateDelta($readings, 'km');
        } catch (\Throwable) {
            return [
                'equipment_id' => $equipmentId,
                'cost_per_km' => '0.00',
                'stale' => true,
            ];
        }

        return [
            'equipment_id' => $equipmentId,
            'cost_per_km' => bccomp($deltaKm, '0', 2) > 0 ? bcdiv($spend, $deltaKm, 2) : '0.00',
            'stale' => false,
        ];
    }

    public function fleetSummary(string $projectCode, Carbon $month): array
    {
        $allocations = BudgetAllocation::query()
            ->whereHas('period', fn ($q) => $q
                ->where('project_code', $projectCode)
                ->whereDate('period_month', $month->copy()->startOfMonth()))
            ->get();

        return $allocations->map(fn ($a) => $this->costPerHour((int) $a->equipment_id, $month))->all();
    }

    private function getActualSpend(int $equipmentId, Carbon $month): string
    {
        $allocation = BudgetAllocation::query()
            ->where('equipment_id', $equipmentId)
            ->whereHas('period', fn ($q) => $q->whereDate('period_month', $month->copy()->startOfMonth()))
            ->first();

        if (! $allocation) {
            return '0.00';
        }

        $sum = BudgetLedger::query()
            ->where('budget_allocation_id', $allocation->id)
            ->where('entry_type', 'actual')
            ->sum('amount');

        return number_format(abs((float) $sum), 2, '.', '');
    }

    private function getDeltaHm(int $equipmentId, Carbon $month): string
    {
        try {
            $readings = $this->arkfleetClient->getHmKmReadings($equipmentId, [
                'from' => $month->copy()->startOfMonth()->toDateString(),
                'to' => $month->copy()->endOfMonth()->toDateString(),
            ]);

            return $this->calculateDelta($readings, 'hm');
        } catch (\Throwable) {
            return '0.00';
        }
    }

    private function calculateDelta(array $readings, string $field): string
    {
        $items = $readings['data'] ?? $readings;
        if (count($items) < 2) {
            return '0.00';
        }

        $first = (float) ($items[0][$field] ?? $items[0]['hm'] ?? 0);
        $last = (float) ($items[count($items) - 1][$field] ?? $items[count($items) - 1]['hm'] ?? 0);

        return number_format(max(0, $last - $first), 2, '.', '');
    }
}
