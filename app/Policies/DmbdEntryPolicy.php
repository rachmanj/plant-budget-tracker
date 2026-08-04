<?php

namespace App\Policies;

use App\Models\DmbdEntry;
use App\Models\User;

class DmbdEntryPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('planner');
    }

    public function view(User $user): bool
    {
        return true;
    }
}
