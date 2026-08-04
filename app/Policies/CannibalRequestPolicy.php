<?php

namespace App\Policies;

use App\Models\CannibalRequest;
use App\Models\User;

class CannibalRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('planner') || $user->hasRole('mechanic');
    }

    public function view(User $user, CannibalRequest $request): bool
    {
        return true;
    }
}
