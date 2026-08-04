<?php

namespace App\Services\Budget;

use App\Models\BudgetAllocation;
use App\Models\BudgetLedger;
use App\Models\BudgetPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BudgetEngine
{
    public function allocate(BudgetPeriod $period, array $allocationsData, User $actor): BudgetPeriod
    {
        return DB::transaction(function () use ($period, $allocationsData, $actor) {
            foreach ($allocationsData as $data) {
                $this->createAllocation($period, $data, $actor);
            }

            $this->syncPeriodEditability($period);

            return $period->fresh(['allocations']);
        });
    }

    public function createAllocation(BudgetPeriod $period, array $data, User $actor): BudgetAllocation
    {
        return DB::transaction(function () use ($period, $data, $actor) {
            $amount = $this->normalizeAmount($data['allocated_amount'] ?? '0');

            $allocation = BudgetAllocation::create([
                'budget_period_id' => $period->id,
                'equipment_id' => $data['equipment_id'] ?? null,
                'unit_code_cache' => $data['unit_code_cache'] ?? null,
                'plant_type_cache' => $data['plant_type_cache'] ?? null,
                'allocated_amount' => $amount,
                'tolerance_pct' => $data['tolerance_pct'] ?? '10.00',
                'is_editable' => $this->computeIsEditable($period),
            ]);

            $this->postLedgerEntry($allocation, 'allocation', $amount, null, null, $actor, $data['memo'] ?? 'Initial allocation');

            $this->recomputeCachedBalances($allocation);
            $this->syncPeriodEditability($period);

            return $allocation->fresh();
        });
    }

    public function revise(BudgetAllocation $allocation, string $newAmount, User $actor, ?string $newTolerancePct = null, ?string $memo = null): BudgetAllocation
    {
        return $this->reviseAllocation($allocation, $newAmount, $actor, $memo ?? 'Allocation revision', $newTolerancePct);
    }

    public function reviseAllocation(BudgetAllocation $allocation, string $newAmount, User $actor, string $memo, ?string $newTolerancePct = null): BudgetAllocation
    {
        $allocation->load('period');

        if (! $allocation->period->isEditableBy($actor)) {
            throw new \InvalidArgumentException('This allocation period is not editable.');
        }

        return DB::transaction(function () use ($allocation, $newAmount, $actor, $memo, $newTolerancePct) {
            $oldAmount = $this->normalizeAmount((string) $allocation->allocated_amount);
            $normalizedNew = $this->normalizeAmount($newAmount);

            if (bccomp($oldAmount, $normalizedNew, 2) !== 0) {
                $this->postLedgerEntry(
                    $allocation,
                    'reversal',
                    bcsub('0', $oldAmount, 2),
                    'allocation',
                    $allocation->id,
                    $actor,
                    $memo.' (reversal)'
                );

                $this->postLedgerEntry(
                    $allocation,
                    'allocation',
                    $normalizedNew,
                    null,
                    null,
                    $actor,
                    $memo
                );

                $allocation->allocated_amount = $normalizedNew;
            }

            if ($newTolerancePct !== null) {
                $allocation->tolerance_pct = $newTolerancePct;
            }

            $allocation->save();

            $this->recomputeCachedBalances($allocation);

            return $allocation->fresh();
        });
    }

    public function postCommitment(BudgetAllocation $allocation, string $amount, string $refType, int $refId, User $actor, ?string $memo = null): BudgetLedger
    {
        return DB::transaction(function () use ($allocation, $amount, $refType, $refId, $actor, $memo) {
            $normalized = $this->normalizeAmount($amount);
            $signed = bccomp($normalized, '0', 2) > 0 ? bcsub('0', $normalized, 2) : $normalized;

            $ledger = $this->postLedgerEntry($allocation, 'commitment', $signed, $refType, $refId, $actor, $memo ?? 'Budget commitment');
            $this->recomputeCachedBalances($allocation);

            return $ledger;
        });
    }

    public function reverseCommitment(BudgetAllocation $allocation, string $refType, int $refId, User $actor, string $reason): BudgetLedger
    {
        return DB::transaction(function () use ($allocation, $refType, $refId, $actor, $reason) {
            $original = BudgetLedger::query()
                ->where('budget_allocation_id', $allocation->id)
                ->where('entry_type', 'commitment')
                ->where('ref_type', $refType)
                ->where('ref_id', $refId)
                ->first();

            if (! $original) {
                throw new \InvalidArgumentException('No commitment found to reverse.');
            }

            $reversalAmount = bcmul((string) $original->amount, '-1', 2);

            $ledger = $this->postLedgerEntry($allocation, 'reversal', $reversalAmount, $refType, $refId, $actor, $reason);
            $this->recomputeCachedBalances($allocation);

            return $ledger;
        });
    }

    public function postActual(BudgetAllocation $allocation, string $amount, int $sapGrpoRef, User $actor, ?string $memo = null): BudgetLedger
    {
        return DB::transaction(function () use ($allocation, $amount, $sapGrpoRef, $actor, $memo) {
            $normalized = $this->normalizeAmount($amount);
            $signed = bccomp($normalized, '0', 2) > 0 ? bcsub('0', $normalized, 2) : $normalized;

            $ledger = $this->postLedgerEntry($allocation, 'actual', $signed, 'grpo', $sapGrpoRef, $actor, $memo ?? 'GRPO actual');

            $commitmentTotal = $this->sumLedgerByType($allocation, 'commitment');
            if (bccomp($commitmentTotal, '0', 2) !== 0) {
                $this->postLedgerEntry(
                    $allocation,
                    'reversal',
                    bcmul($commitmentTotal, '-1', 2),
                    'grpo',
                    $sapGrpoRef,
                    $actor,
                    'Commitment offset for GRPO'
                );
            }

            $this->recomputeCachedBalances($allocation);

            return $ledger;
        });
    }

    public function postOverbudget(BudgetAllocation $allocation, string $amount, int $overbudgetRequestId, User $actor, ?string $memo = null): BudgetLedger
    {
        return DB::transaction(function () use ($allocation, $amount, $overbudgetRequestId, $actor, $memo) {
            $normalized = $this->normalizeAmount($amount);

            $ledger = $this->postLedgerEntry(
                $allocation,
                'overbudget',
                $normalized,
                'overbudget_request',
                $overbudgetRequestId,
                $actor,
                $memo ?? 'Overbudget approval'
            );

            $this->recomputeCachedBalances($allocation);

            return $ledger;
        });
    }

    public function carryForward(?Carbon $asOf = null): int
    {
        $asOf = ($asOf ?? now())->copy()->timezone('Asia/Makassar');
        $endedMonth = $asOf->copy()->subMonthNoOverflow()->startOfMonth();
        $processed = 0;

        $periods = BudgetPeriod::query()
            ->where('status', 'open')
            ->whereDate('period_month', $endedMonth)
            ->with('allocations')
            ->get();

        foreach ($periods as $period) {
            $periodProcessed = $this->carryForwardPeriod($period, $asOf);
            if ($periodProcessed) {
                $processed++;
            }
        }

        return $processed;
    }

    public function carryForwardPeriod(BudgetPeriod $period, ?Carbon $asOf = null): bool
    {
        $asOf = ($asOf ?? now())->copy()->timezone('Asia/Makassar');
        $nextMonth = $period->period_month->copy()->addMonthNoOverflow()->startOfMonth();
        $systemActor = User::query()->first();

        if (! $systemActor) {
            return false;
        }

        $anyProcessed = false;

        foreach ($period->allocations as $allocation) {
            try {
                DB::transaction(function () use ($allocation, $period, $nextMonth, $systemActor, &$anyProcessed) {
                    $this->recomputeCachedBalances($allocation);
                    $allocation->refresh();

                    $variance = bcsub(
                        bcadd((string) $allocation->allocated_amount, (string) $allocation->carry_forward_in, 2),
                        bcadd((string) $allocation->committed_amount, (string) $allocation->actual_amount, 2),
                        2
                    );

                    $carryAmount = bccomp($variance, '0', 2) > 0 ? $variance : '0.00';

                    $nextPeriod = BudgetPeriod::query()->firstOrCreate(
                        [
                            'project_code' => $period->project_code,
                            'period_month' => $nextMonth->toDateString(),
                        ],
                        [
                            'project_name_cache' => $period->project_name_cache,
                            'status' => 'open',
                            'created_by' => $period->created_by,
                        ]
                    );

                    $nextAllocation = BudgetAllocation::query()->firstOrCreate(
                        [
                            'budget_period_id' => $nextPeriod->id,
                            'equipment_id' => $allocation->equipment_id,
                        ],
                        [
                            'unit_code_cache' => $allocation->unit_code_cache,
                            'plant_type_cache' => $allocation->plant_type_cache,
                            'allocated_amount' => '0.00',
                            'tolerance_pct' => $allocation->tolerance_pct,
                            'is_editable' => $this->computeIsEditable($nextPeriod),
                        ]
                    );

                    $alreadyCarried = BudgetLedger::query()
                        ->where('budget_allocation_id', $nextAllocation->id)
                        ->where('entry_type', 'carry_forward')
                        ->where('ref_type', 'budget_period')
                        ->where('ref_id', $period->id)
                        ->exists();

                    if (! $alreadyCarried && bccomp($carryAmount, '0', 2) > 0) {
                        $this->postLedgerEntry(
                            $nextAllocation,
                            'carry_forward',
                            $carryAmount,
                            'budget_period',
                            $period->id,
                            $systemActor,
                            "Carry forward from {$period->period_month->format('Y-m')}"
                        );

                        $this->recomputeCachedBalances($nextAllocation);
                        $anyProcessed = true;
                    }

                    $this->syncPeriodEditability($nextPeriod);
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($anyProcessed || $period->status === 'open') {
            $period->update(['status' => 'locked']);
            $this->syncPeriodEditability($period);
        }

        return $anyProcessed;
    }

    public function recomputeCachedBalances(BudgetAllocation $allocation): void
    {
        $allocation->refresh();

        $committed = $this->sumLedgerByType($allocation, 'commitment');
        $actual = $this->sumLedgerByType($allocation, 'actual');
        $carryForward = $this->sumLedgerByType($allocation, 'carry_forward');

        $committedAmount = bccomp($committed, '0', 2) < 0 ? bcmul($committed, '-1', 2) : '0.00';
        $actualAmount = bccomp($actual, '0', 2) < 0 ? bcmul($actual, '-1', 2) : '0.00';

        $allocation->update([
            'committed_amount' => $committedAmount,
            'actual_amount' => $actualAmount,
            'carry_forward_in' => bccomp($carryForward, '0', 2) > 0 ? $carryForward : '0.00',
        ]);
    }

    public function validateAgainstTolerance(BudgetAllocation $allocation, string $additionalAmount): array
    {
        $this->recomputeCachedBalances($allocation);
        $allocation->refresh();

        $base = bcadd((string) $allocation->allocated_amount, (string) $allocation->carry_forward_in, 2);
        $cap = $allocation->tolerance_cap;

        $currentSpend = bcadd((string) $allocation->committed_amount, (string) $allocation->actual_amount, 2);
        $projected = bcadd($currentSpend, $this->normalizeAmount($additionalAmount), 2);

        $withinTolerance = bccomp($projected, $cap, 2) <= 0;

        $projectedPct = '0.00';
        if (bccomp($base, '0', 2) > 0) {
            $projectedPct = bcmul(bcdiv($projected, $base, 4), '100', 2);
        }

        return [
            'within_tolerance' => $withinTolerance,
            'projected_pct' => $projectedPct,
            'cap' => $cap,
            'projected' => $projected,
            'base' => $base,
        ];
    }

    private function postLedgerEntry(
        BudgetAllocation $allocation,
        string $entryType,
        string $amount,
        ?string $refType,
        ?int $refId,
        User $actor,
        ?string $memo
    ): BudgetLedger {
        return BudgetLedger::create([
            'budget_allocation_id' => $allocation->id,
            'entry_type' => $entryType,
            'amount' => $this->normalizeAmount($amount),
            'ref_type' => $refType,
            'ref_id' => $refId,
            'memo' => $memo,
            'posted_by' => $actor->id,
            'posted_at' => now(),
        ]);
    }

    private function sumLedgerByType(BudgetAllocation $allocation, string $entryType): string
    {
        $sum = BudgetLedger::query()
            ->where('budget_allocation_id', $allocation->id)
            ->where('entry_type', $entryType)
            ->sum('amount');

        return $this->normalizeAmount((string) $sum);
    }

    private function normalizeAmount(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function computeIsEditable(BudgetPeriod $period): bool
    {
        if (in_array($period->status, ['locked', 'closed'], true)) {
            return false;
        }

        return $period->period_month->greaterThanOrEqualTo(now()->startOfMonth());
    }

    private function syncPeriodEditability(BudgetPeriod $period): void
    {
        $isEditable = $this->computeIsEditable($period);

        $period->allocations()->update(['is_editable' => $isEditable]);
    }
}
