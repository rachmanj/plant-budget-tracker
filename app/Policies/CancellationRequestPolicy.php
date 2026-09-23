<?php

namespace App\Policies;

use App\Models\CancellationRequest;
use App\Models\User;
use App\Policies\Concerns\ChecksModuleAccess;
use Illuminate\Auth\Access\Response;

class CancellationRequestPolicy
{
    use ChecksModuleAccess;

    /** @var list<string> */
    private const VIEW_ANY_ROLES = [
        'planner',
        'mechanic',
        'project_manager',
        'plant_manager',
        'buyer',
        'procurement_manager',
        'procurement_admin',
    ];

    public function viewAny(User $user): Response|bool
    {
        if ($this->canAccessModule($user, self::VIEW_ANY_ROLES)) {
            return true;
        }

        return Response::deny('You do not have permission to access the Cancellation page.');
    }

    public function agree(User $user, CancellationRequest $request): Response|bool
    {
        if ($request->status !== 'pending') {
            return Response::deny('This cancellation request can no longer be approved.');
        }

        if ($request->initiated_by === 'plant') {
            if ($user->can('cancellation.procurement')) {
                return true;
            }

            return Response::deny('You do not have permission to approve this cancellation on behalf of procurement.');
        }

        if ($user->can('cancellation.plant')) {
            return true;
        }

        return Response::deny('You do not have permission to approve this cancellation on behalf of plant.');
    }
}
