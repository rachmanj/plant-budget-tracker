<?php

namespace App\Services\Approval;

use App\Models\RequestApproval;
use App\Models\User;
use App\Services\Budget\BudgetEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApprovalEngine
{
    public const STATUS_MAP = [
        'PlantRequest' => [
            1 => 'pending_pm',
            2 => 'pending_plant_mgr',
        ],
        'OverbudgetRequest' => [
            1 => 'pending_fin_dir',
            2 => 'pending_ops_dir',
        ],
        'CannibalRequest' => [
            1 => 'pending_plant_mgr',
            2 => 'pending_aml_mgr',
            3 => 'pending_ops_dir',
            4 => 'pending_presdir',
        ],
        'TabulationBid' => [
            1 => 'pending_proc_mgr',
        ],
    ];

    public function __construct(
        private readonly BudgetEngine $budgetEngine,
    ) {}

    public function initiate(Model $approvable, array $chain): void
    {
        DB::transaction(function () use ($approvable, $chain) {
            foreach ($chain as $step) {
                $approvable->approvals()->create([
                    'step_order' => $step['step_order'],
                    'required_role' => $step['required_role'],
                    'decision' => 'pending',
                ]);
            }

            $type = class_basename($approvable);
            $firstStatus = self::STATUS_MAP[$type][1] ?? 'pending';
            $approvable->update(['status' => $firstStatus]);
        });
    }

    public function decide(RequestApproval $approval, User $actor, string $decision, ?string $remarks = null): void
    {
        if ($approval->decision !== 'pending') {
            throw new InvalidArgumentException('Approval step already decided.');
        }

        $current = $this->currentStep($approval->approvable);
        if (! $current || $current->id !== $approval->id) {
            throw new InvalidArgumentException('Not the current approval step.');
        }

        DB::transaction(function () use ($approval, $actor, $decision, $remarks) {
            $approval->update([
                'decision' => $decision,
                'remarks' => $remarks,
                'approver_id' => $actor->id,
                'acted_at' => now(),
            ]);

            $approvable = $approval->approvable;
            $type = class_basename($approvable);

            if (in_array($decision, ['rejected', 'returned'], true)) {
                $this->handleRejection($approvable, $type, $decision);
                return;
            }

            if ($this->isFullyApproved($approvable)) {
                if (method_exists($approvable, 'onFullyApproved')) {
                    $approvable->onFullyApproved();
                }
                return;
            }

            $nextStep = $this->currentStep($approvable);
            if ($nextStep) {
                $status = self::STATUS_MAP[$type][$nextStep->step_order] ?? $approvable->status;
                $approvable->update(['status' => $status]);
            }
        });
    }

    public function currentStep(Model $approvable): ?RequestApproval
    {
        return $approvable->approvals()
            ->where('decision', 'pending')
            ->orderBy('step_order')
            ->first();
    }

    public function isFullyApproved(Model $approvable): bool
    {
        return $approvable->approvals()
            ->where('decision', 'pending')
            ->doesntExist()
            && $approvable->approvals()->where('decision', 'approved')->exists();
    }

    public function getCurrentRequiredRole(Model $approvable): ?string
    {
        return $this->currentStep($approvable)?->required_role;
    }

    private function handleRejection(Model $approvable, string $type, string $decision): void
    {
        if ($type === 'PlantRequest') {
            $allocation = $approvable->allocation;
            if ($allocation) {
                try {
                    $this->budgetEngine->reverseCommitment(
                        $allocation,
                        'plant_request',
                        $approvable->id,
                        $approvable->requester,
                        'Plant request rejected/returned'
                    );
                } catch (InvalidArgumentException) {
                    // No commitment to reverse
                }
            }
            $approvable->update(['status' => $decision === 'rejected' ? 'rejected' : 'draft']);
            return;
        }

        if ($type === 'CannibalRequest') {
            $approvable->update(['status' => 'rejected']);
            return;
        }

        if ($type === 'OverbudgetRequest') {
            $approvable->update(['status' => 'rejected']);
            return;
        }

        $approvable->update(['status' => 'draft']);
    }
}
