<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksModuleAccess
{
    /**
     * @param  list<string>  $roles
     */
    protected function canAccessModule(User $user, array $roles, bool $grantItManager = true): bool
    {
        if ($grantItManager && $user->hasRole('it_manager')) {
            return true;
        }

        return $user->hasAnyRole($roles);
    }
}
