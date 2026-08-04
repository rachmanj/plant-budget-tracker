<?php

namespace App\Policies;

use App\Models\PlantRequest;
use App\Models\User;

class PlantRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('planner') || $user->hasRole('mechanic');
    }

    public function view(User $user, PlantRequest $request): bool
    {
        return true;
    }

    public function update(User $user, PlantRequest $request): bool
    {
        return $request->requested_by === $user->id && $request->status === 'draft';
    }

    public function submit(User $user, PlantRequest $request): bool
    {
        return $this->update($user, $request)
            && $request->lines()->count() > 0
            && $request->sap_mr_id > 0;
    }

    public function cancel(User $user, PlantRequest $request): bool
    {
        return $user->can('cancellation.plant') || $user->can('cancellation.procurement');
    }
}
