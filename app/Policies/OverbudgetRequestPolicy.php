<?php

namespace App\Policies;

use App\Models\OverbudgetRequest;
use App\Models\User;
use App\Policies\Concerns\ChecksModuleAccess;
use Illuminate\Auth\Access\Response;

class OverbudgetRequestPolicy
{
    use ChecksModuleAccess;

    /** @var list<string> */
    private const VIEW_ANY_ROLES = [
        'planner',
        'mechanic',
        'project_manager',
        'plant_manager',
        'finance_director',
        'operation_director',
    ];

    public function viewAny(User $user): Response|bool
    {
        if ($this->canAccessModule($user, self::VIEW_ANY_ROLES)) {
            return true;
        }

        return Response::deny('You do not have permission to access the Overbudget page.');
    }

    public function create(User $user): Response|bool
    {
        if ($user->can('plant_request.create')) {
            return true;
        }

        return Response::deny('You do not have permission to create overbudget requests.');
    }

    public function view(User $user, OverbudgetRequest $request): bool
    {
        return true;
    }
}
