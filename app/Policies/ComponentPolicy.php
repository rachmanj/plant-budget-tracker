<?php

namespace App\Policies;

use App\Models\Component;
use App\Models\User;

class ComponentPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('aml_manager') || $user->hasRole('aml_dept_head');
    }

    public function update(User $user, Component $component): bool
    {
        return $this->create($user);
    }

    public function view(User $user): bool
    {
        return true;
    }
}
