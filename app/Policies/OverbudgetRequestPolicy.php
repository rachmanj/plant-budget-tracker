<?php

namespace App\Policies;

use App\Models\OverbudgetRequest;
use App\Models\User;

class OverbudgetRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->can('plant_request.create');
    }

    public function view(User $user, OverbudgetRequest $request): bool
    {
        return true;
    }
}
