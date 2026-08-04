<?php

namespace App\Policies;

use App\Models\InterchangeMap;
use App\Models\User;

class InterchangeMapPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('buyer')
            || $user->hasRole('procurement_manager')
            || $user->hasRole('procurement_admin');
    }

    public function signoff(User $user, InterchangeMap $map): bool
    {
        return ($user->hasRole('plant_manager') || $user->hasRole('aml_manager'))
            && $map->created_by !== $user->id;
    }
}
