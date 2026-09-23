<?php

namespace App\Services\Dashboard;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\CancellationRequest;
use App\Models\DmbdEntry;
use App\Models\InterchangeMap;
use App\Models\OverbudgetRequest;
use App\Models\PlantRequest;
use App\Models\TabulationBid;
use App\Models\TabulationBidAward;
use App\Models\User;
use App\Services\Approval\ApprovalEngine;
use App\Services\Arkfleet\EquipmentCache;
use App\Support\ApprovalChains;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class DashboardMetrics
{
    public function __construct(
        private readonly EquipmentCache $equipmentCache,
    ) {}

    public function for(User $user, ?string $projectCode): array
    {
        $now = Carbon::now();

        return [
            'projectCode' => $projectCode,
            'period' => $this->periodInfo($projectCode, $now),
            'budget' => $this->budgetMetrics($projectCode, $now),
            'requests' => $this->requestMetrics($user, $projectCode, $now),
            'procurement' => $this->procurementMetrics($now),
            'pending' => $this->pendingMetrics($user, $projectCode),
            'dmbd' => $this->dmbdMetrics($user, $projectCode),
        ];
    }

    /**
     * @return array{month: string, label: string, status: string, exists: bool}
     */
    private function periodInfo(?string $projectCode, Carbon $now): array
    {
        $month = $now->copy()->startOfMonth();

        $default = [
            'month' => $month->format('Y-m'),
            'label' => $this->monthLabel($month),
            'status' => 'none',
            'exists' => false,
        ];

        if ($projectCode === null) {
            return $default;
        }

        try {
            $period = BudgetPeriod::query()
                ->where('project_code', $projectCode)
                ->whereDate('period_month', $month)
                ->first();

            if (! $period) {
                return $default;
            }

            return [
                'month' => $month->format('Y-m'),
                'label' => $this->monthLabel($month),
                'status' => $period->status,
                'exists' => true,
            ];
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * @return array{allocated: string, carryForward: string, used: string, remaining: string, usedPct: float, allocationCount: int, available: bool}
     */
    private function budgetMetrics(?string $projectCode, Carbon $now): array
    {
        $default = [
            'allocated' => '0.00',
            'carryForward' => '0.00',
            'used' => '0.00',
            'remaining' => '0.00',
            'usedPct' => 0.0,
            'allocationCount' => 0,
            'available' => false,
        ];

        try {
            if ($projectCode === null) {
                return array_merge($default, ['available' => true]);
            }

            $period = BudgetPeriod::query()
                ->where('project_code', $projectCode)
                ->whereDate('period_month', $now->copy()->startOfMonth())
                ->first();

            if (! $period) {
                return array_merge($default, ['available' => true]);
            }

            // Source of truth: BudgetAllocation::variance / utilization_pct accessors.
            // "used" mirrors committed_amount + actual_amount, the cached balances
            // kept in sync by BudgetEngine::recomputeCachedBalances(). Do not read
            // budget_ledgers here and never fold "overbudget" into "used" — it
            // raises the ceiling (carry_forward-like), it is not consumption.
            $totals = BudgetAllocation::query()
                ->where('budget_period_id', $period->id)
                ->selectRaw(
                    'COUNT(*) as allocation_count,
                    SUM(allocated_amount) as allocated_sum,
                    SUM(carry_forward_in) as carry_forward_sum,
                    SUM(committed_amount + actual_amount) as used_sum'
                )
                ->first();

            $allocated = $this->money($totals?->allocated_sum ?? 0);
            $carryForward = $this->money($totals?->carry_forward_sum ?? 0);
            $used = $this->money($totals?->used_sum ?? 0);
            $base = bcadd($allocated, $carryForward, 2);
            $remaining = bcsub($base, $used, 2);

            $usedPct = 0.0;
            if (bccomp($base, '0', 2) > 0) {
                $usedPct = round(((float) $used / (float) $base) * 100, 2);
            }

            return [
                'allocated' => $allocated,
                'carryForward' => $carryForward,
                'used' => $used,
                'remaining' => $remaining,
                'usedPct' => $usedPct,
                'allocationCount' => (int) ($totals?->allocation_count ?? 0),
                'available' => true,
            ];
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * @return array{draft: int, waitingApproval: int, approvedThisMonth: int, rejected: int, awaitingMyDecision: int}
     */
    private function requestMetrics(User $user, ?string $projectCode, Carbon $now): array
    {
        $default = [
            'draft' => 0,
            'waitingApproval' => 0,
            'approvedThisMonth' => 0,
            'rejected' => 0,
            'awaitingMyDecision' => 0,
        ];

        if ($projectCode === null) {
            return $default;
        }

        try {
            $monthStart = $now->copy()->startOfMonth();
            $monthEnd = $now->copy()->endOfMonth();

            $counts = $this->plantRequestsQuery($projectCode)
                ->selectRaw(
                    "SUM(status = 'draft') as draft,
                    SUM(status IN ('pending_pm', 'pending_plant_mgr')) as waiting_approval,
                    SUM(status = 'approved' AND updated_at BETWEEN ? AND ?) as approved_this_month,
                    SUM(status = 'rejected') as rejected",
                    [$monthStart, $monthEnd]
                )
                ->first();

            $myStatuses = array_values($this->roleStatusMap('PlantRequest', $user));

            $awaitingMyDecision = 0;
            if ($myStatuses !== []) {
                $awaitingMyDecision = (int) $this->plantRequestsQuery($projectCode)
                    ->whereIn('status', $myStatuses)
                    ->count();
            }

            return [
                'draft' => (int) ($counts?->draft ?? 0),
                'waitingApproval' => (int) ($counts?->waiting_approval ?? 0),
                'approvedThisMonth' => (int) ($counts?->approved_this_month ?? 0),
                'rejected' => (int) ($counts?->rejected ?? 0),
                'awaitingMyDecision' => $awaitingMyDecision,
            ];
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * @return array{bidsAwaitingReview: int, bidsAwaitingPo: int, poCreatedThisMonth: int, poValueThisMonth: string}
     */
    private function procurementMetrics(Carbon $now): array
    {
        $default = [
            'bidsAwaitingReview' => 0,
            'bidsAwaitingPo' => 0,
            'poCreatedThisMonth' => 0,
            'poValueThisMonth' => '0.00',
        ];

        try {
            $monthStart = $now->copy()->startOfMonth();
            $monthEnd = $now->copy()->endOfMonth();

            $bidsAwaitingReview = (int) TabulationBid::query()->where('status', 'pending_proc_mgr')->count();
            $bidsAwaitingPo = (int) TabulationBid::query()->where('status', 'forwarded_admin')->count();

            $poCreatedThisMonth = (int) TabulationBid::query()
                ->whereNotNull('sap_po_id')
                ->whereBetween('updated_at', [$monthStart, $monthEnd])
                ->count();

            $poValue = TabulationBidAward::query()
                ->join('tabulation_bid_vendors', 'tabulation_bid_vendors.id', '=', 'tabulation_bid_awards.tabulation_bid_vendor_id')
                ->whereHas('bid', function (Builder $query) use ($monthStart, $monthEnd) {
                    $query->whereNotNull('sap_po_id')->whereBetween('updated_at', [$monthStart, $monthEnd]);
                })
                ->sum('tabulation_bid_vendors.price');

            return [
                'bidsAwaitingReview' => $bidsAwaitingReview,
                'bidsAwaitingPo' => $bidsAwaitingPo,
                'poCreatedThisMonth' => $poCreatedThisMonth,
                'poValueThisMonth' => $this->money($poValue),
            ];
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * @return array{overbudget: int, cancellation: int, interchange: int}
     */
    private function pendingMetrics(User $user, ?string $projectCode): array
    {
        $default = ['overbudget' => 0, 'cancellation' => 0, 'interchange' => 0];

        try {
            return [
                'overbudget' => $this->pendingOverbudget($user, $projectCode),
                'cancellation' => $this->pendingCancellation($user, $projectCode),
                'interchange' => $this->pendingInterchange($user),
            ];
        } catch (Throwable) {
            return $default;
        }
    }

    private function pendingOverbudget(User $user, ?string $projectCode): int
    {
        if ($projectCode === null) {
            return 0;
        }

        $query = OverbudgetRequest::query()
            ->whereHas('allocation.period', fn (Builder $q) => $q->where('project_code', $projectCode));

        $roleStatusMap = $this->roleStatusMap('OverbudgetRequest', $user);

        if ($roleStatusMap !== []) {
            return (int) (clone $query)->whereIn('status', array_values($roleStatusMap))->count();
        }

        $allStatuses = array_values($this->roleStatusMap('OverbudgetRequest'));

        return (int) (clone $query)->whereIn('status', $allStatuses)->count();
    }

    private function pendingCancellation(User $user, ?string $projectCode): int
    {
        if ($projectCode === null) {
            return 0;
        }

        $query = CancellationRequest::query()
            ->whereHas('plantRequest.allocation.period', fn (Builder $q) => $q->where('project_code', $projectCode))
            ->where('status', 'pending');

        $canProcurement = $user->can('cancellation.procurement');
        $canPlant = $user->can('cancellation.plant');

        if ($canProcurement xor $canPlant) {
            $awaitingFrom = $canProcurement ? 'plant' : 'procurement';

            return (int) (clone $query)->where('initiated_by', $awaitingFrom)->count();
        }

        return (int) (clone $query)->count();
    }

    private function pendingInterchange(User $user): int
    {
        $query = InterchangeMap::query()->whereNull('technical_signoff_at');

        if ($user->hasRole('plant_manager') || $user->hasRole('aml_manager')) {
            return (int) (clone $query)->where('created_by', '!=', $user->id)->count();
        }

        return (int) (clone $query)->count();
    }

    /**
     * @return array{rfu: int, standby: int, breakdown: int, recorded: int, unitsActive: int|null, available: bool}
     */
    private function dmbdMetrics(User $user, ?string $projectCode): array
    {
        $default = [
            'rfu' => 0,
            'standby' => 0,
            'breakdown' => 0,
            'recorded' => 0,
            'unitsActive' => null,
            'available' => false,
        ];

        if (! $user->can('dmbd.view') || $projectCode === null) {
            return $default;
        }

        try {
            $result = $this->equipmentCache->list(['project_code' => $projectCode]);

            $equipmentIds = collect($result['data'] ?? [])
                ->map(fn (array $item) => (int) ($item['id'] ?? 0))
                ->filter()
                ->unique()
                ->values();

            $counts = ['rfu' => 0, 'standby' => 0, 'breakdown' => 0];

            if ($equipmentIds->isNotEmpty()) {
                $rows = DmbdEntry::query()
                    ->whereIn('equipment_id', $equipmentIds)
                    ->where('report_date', now()->toDateString())
                    ->selectRaw('operational_status, COUNT(*) as cnt')
                    ->groupBy('operational_status')
                    ->pluck('cnt', 'operational_status');

                foreach ($rows as $status => $cnt) {
                    if (array_key_exists($status, $counts)) {
                        $counts[$status] = (int) $cnt;
                    }
                }
            }

            return [
                'rfu' => $counts['rfu'],
                'standby' => $counts['standby'],
                'breakdown' => $counts['breakdown'],
                'recorded' => array_sum($counts),
                'unitsActive' => $equipmentIds->count(),
                'available' => true,
            ];
        } catch (Throwable) {
            return $default;
        }
    }

    private function plantRequestsQuery(string $projectCode): Builder
    {
        return PlantRequest::query()
            ->whereHas('allocation.period', fn (Builder $q) => $q->where('project_code', $projectCode));
    }

    /**
     * Maps the approval chain roles for a given approvable type to their
     * corresponding pending status. When a $user is provided, only roles
     * the user actually holds are returned.
     *
     * @return array<string, string>
     */
    private function roleStatusMap(string $type, ?User $user = null): array
    {
        $statusMap = ApprovalEngine::STATUS_MAP[$type] ?? [];
        $chain = ApprovalChains::for($type);

        $map = [];
        foreach ($chain as $step) {
            $status = $statusMap[$step['step_order']] ?? null;
            $role = $step['required_role'] ?? null;

            if ($status === null || $role === null) {
                continue;
            }

            if ($user !== null && ! $user->hasRole($role)) {
                continue;
            }

            $map[$role] = $status;
        }

        return $map;
    }

    private function monthLabel(Carbon $month): string
    {
        return $month->locale('en')->translatedFormat('M Y');
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
