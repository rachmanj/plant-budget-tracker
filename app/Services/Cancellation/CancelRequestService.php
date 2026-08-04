<?php

namespace App\Services\Cancellation;

use App\Models\CancellationRequest;
use App\Models\PlantRequest;
use App\Models\User;
use App\Services\Budget\BudgetEngine;
use InvalidArgumentException;

class CancelRequestService
{
    public function __construct(
        private readonly BudgetEngine $budgetEngine,
    ) {}

    public function canPlantCancel(PlantRequest $request, ?string $poStage): bool
    {
        return $poStage !== 'sent';
    }

    public function createCancellation(
        PlantRequest $plantRequest,
        User $actor,
        string $initiatedBy,
        ?string $poStage,
        string $reason
    ): CancellationRequest {
        if ($initiatedBy === 'plant' && ! $this->canPlantCancel($plantRequest, $poStage)) {
            throw new InvalidArgumentException('Cannot cancel: PO has been sent.');
        }

        return CancellationRequest::create([
            'plant_request_id' => $plantRequest->id,
            'sap_po_id' => null,
            'po_stage' => $poStage,
            'initiated_by' => $initiatedBy,
            'status' => 'pending',
            'budget_reversal_amount' => $plantRequest->estimated_total,
            'reason' => $reason,
        ]);
    }

    public function agree(CancellationRequest $cancellation, User $actor): void
    {
        $cancellation->update([
            'agreed_by' => $actor->id,
            'agreed_at' => now(),
            'status' => 'approved',
        ]);

        $plantRequest = $cancellation->plantRequest;
        $allocation = $plantRequest->allocation;

        if ($allocation) {
            try {
                $this->budgetEngine->reverseCommitment(
                    $allocation,
                    'plant_request',
                    $plantRequest->id,
                    $actor,
                    'Cancellation approved'
                );
            } catch (InvalidArgumentException) {
                // No commitment to reverse
            }
        }

        $plantRequest->update(['status' => 'cancelled']);
    }
}
