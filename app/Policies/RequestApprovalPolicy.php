<?php

namespace App\Policies;

use App\Models\RequestApproval;
use App\Models\User;
use App\Services\Approval\ApprovalEngine;

class RequestApprovalPolicy
{
    public function decide(User $user, RequestApproval $approval): bool
    {
        if ($approval->decision !== 'pending') {
            return false;
        }

        $engine = app(ApprovalEngine::class);
        $current = $engine->currentStep($approval->approvable);

        if (! $current || $current->id !== $approval->id) {
            return false;
        }

        return $user->hasRole($approval->required_role);
    }
}
