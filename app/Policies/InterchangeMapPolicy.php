<?php

namespace App\Policies;

use App\Models\InterchangeMap;
use App\Models\User;
use App\Policies\Concerns\ChecksModuleAccess;
use Illuminate\Auth\Access\Response;

class InterchangeMapPolicy
{
    use ChecksModuleAccess;

    /** @var list<string> */
    private const VIEW_ANY_ROLES = [
        'buyer',
        'procurement_manager',
        'procurement_admin',
        'project_manager',
        'plant_manager',
        'aml_manager',
    ];

    public function viewAny(User $user): Response|bool
    {
        if ($this->canAccessModule($user, self::VIEW_ANY_ROLES)) {
            return true;
        }

        return Response::deny('Anda tidak memiliki izin untuk mengakses halaman Interchange.');
    }

    public function create(User $user): Response|bool
    {
        if ($user->hasRole('buyer')
            || $user->hasRole('procurement_manager')
            || $user->hasRole('procurement_admin')) {
            return true;
        }

        return Response::deny('Anda tidak memiliki izin untuk membuat mapping interchange.');
    }

    public function signoff(User $user, InterchangeMap $map): Response|bool
    {
        if (($user->hasRole('plant_manager') || $user->hasRole('aml_manager'))
            && $map->created_by !== $user->id) {
            return true;
        }

        return Response::deny('Anda tidak memiliki izin untuk melakukan sign-off teknis interchange ini.');
    }
}
