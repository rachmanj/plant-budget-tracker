<?php

namespace App\Policies;

use App\Models\RequestApproval;
use App\Models\User;
use App\Policies\Concerns\ChecksModuleAccess;
use App\Services\Approval\ApprovalEngine;
use App\Support\ApprovalChains;
use Illuminate\Auth\Access\Response;

class RequestApprovalPolicy
{
    use ChecksModuleAccess;

    public function viewAny(User $user): Response|bool
    {
        if ($this->canAccessModule($user, ApprovalChains::approverRoles())) {
            return true;
        }

        return Response::deny('You do not have permission to access the Approvals page.');
    }

    public function decide(User $user, RequestApproval $approval): Response|bool
    {
        if ($approval->decision !== 'pending') {
            return Response::deny('This approval has already been decided.');
        }

        $engine = app(ApprovalEngine::class);
        $current = $engine->currentStep($approval->approvable);

        if (! $current || $current->id !== $approval->id) {
            return Response::deny('It is not your turn for this approval step.');
        }

        if ($user->hasRole($approval->required_role)) {
            return true;
        }

        return Response::deny('You do not have permission to decide this approval.');
    }
}
