<?php

namespace App\Policies;

use App\Models\CancellationRequest;
use App\Models\User;
use App\Policies\Concerns\ChecksModuleAccess;
use Illuminate\Auth\Access\Response;

class CancellationRequestPolicy
{
    use ChecksModuleAccess;

    /** @var list<string> */
    private const VIEW_ANY_ROLES = [
        'planner',
        'mechanic',
        'project_manager',
        'plant_manager',
        'buyer',
        'procurement_manager',
        'procurement_admin',
    ];

    public function viewAny(User $user): Response|bool
    {
        if ($this->canAccessModule($user, self::VIEW_ANY_ROLES)) {
            return true;
        }

        return Response::deny('Anda tidak memiliki izin untuk mengakses halaman Pembatalan.');
    }

    public function agree(User $user, CancellationRequest $request): Response|bool
    {
        if ($request->status !== 'pending') {
            return Response::deny('Permintaan pembatalan ini sudah tidak dapat disetujui.');
        }

        if ($request->initiated_by === 'plant') {
            if ($user->can('cancellation.procurement')) {
                return true;
            }

            return Response::deny('Anda tidak memiliki izin untuk menyetujui pembatalan dari sisi procurement.');
        }

        if ($user->can('cancellation.plant')) {
            return true;
        }

        return Response::deny('Anda tidak memiliki izin untuk menyetujui pembatalan dari sisi plant.');
    }
}
