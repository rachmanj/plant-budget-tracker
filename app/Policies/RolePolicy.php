<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function manage(User $user, ?Role $role = null): bool
    {
        return $user->can('user.manage');
    }
}
