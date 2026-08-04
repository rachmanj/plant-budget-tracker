<?php

namespace App\Policies;

use App\Models\CancellationRequest;
use App\Models\User;

class CancellationRequestPolicy
{
    public function agree(User $user, CancellationRequest $request): bool
    {
        if ($request->status !== 'pending') {
            return false;
        }

        if ($request->initiated_by === 'plant') {
            return $user->can('cancellation.procurement');
        }

        return $user->can('cancellation.plant');
    }
}
