<?php

namespace App\Jobs;

use App\Models\BudgetAllocation;
use App\Models\SapSyncLog;
use App\Models\User;
use App\Services\Budget\BudgetEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcileGrpoToLedger implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $sapGrpoDocEntry,
        public ?int $budgetAllocationId = null,
        public string $amount = '0.00',
    ) {
        $this->onQueue('sap-writes');
    }

    public function handle(BudgetEngine $budgetEngine): void
    {
        $correlationKey = "reconcile_grpo:{$this->sapGrpoDocEntry}";

        $log = SapSyncLog::firstOrCreate(
            ['correlation_key' => $correlationKey],
            [
                'operation' => 'reconcile_grpo',
                'ref_type' => 'grpo',
                'ref_id' => $this->sapGrpoDocEntry,
                'status' => 'pending',
            ]
        );

        if ($log->status === 'success' || ! $this->budgetAllocationId) {
            return;
        }

        $allocation = BudgetAllocation::find($this->budgetAllocationId);
        $actor = User::query()->first();

        if (! $allocation || ! $actor) {
            return;
        }

        $budgetEngine->postActual($allocation, $this->amount, $this->sapGrpoDocEntry, $actor);

        $log->update(['status' => 'success', 'completed_at' => now()]);
    }
}
