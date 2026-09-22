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

        return Response::deny('Anda tidak memiliki izin untuk mengakses halaman Persetujuan.');
    }

    public function decide(User $user, RequestApproval $approval): Response|bool
    {
        if ($approval->decision !== 'pending') {
            return Response::deny('Persetujuan ini sudah diputuskan.');
        }

        $engine = app(ApprovalEngine::class);
        $current = $engine->currentStep($approval->approvable);

        if (! $current || $current->id !== $approval->id) {
            return Response::deny('Langkah persetujuan ini bukan giliran Anda.');
        }

        if ($user->hasRole($approval->required_role)) {
            return true;
        }

        return Response::deny('Anda tidak memiliki izin untuk memutuskan persetujuan ini.');
    }
}
