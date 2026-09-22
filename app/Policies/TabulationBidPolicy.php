<?php

namespace App\Policies;

use App\Models\TabulationBid;
use App\Models\User;
use App\Policies\Concerns\ChecksModuleAccess;
use Illuminate\Auth\Access\Response;

class TabulationBidPolicy
{
    use ChecksModuleAccess;

    /** @var list<string> */
    private const VIEW_ANY_ROLES = [
        'buyer',
        'procurement_manager',
        'procurement_admin',
        'president_director',
    ];

    public function viewAny(User $user): Response|bool
    {
        if ($this->canAccessModule($user, self::VIEW_ANY_ROLES)) {
            return true;
        }

        return Response::deny('Anda tidak memiliki izin untuk mengakses halaman Tabulation Bid.');
    }

    public function create(User $user): Response|bool
    {
        if ($user->hasRole('buyer')) {
            return true;
        }

        return Response::deny('Hanya Buyer yang dapat membuat Tabulation Bid.');
    }

    public function view(User $user, TabulationBid $bid): bool
    {
        return true;
    }

    public function review(User $user, TabulationBid $bid): Response|bool
    {
        if ($user->hasRole('procurement_manager') && $bid->status === 'pending_proc_mgr') {
            return true;
        }

        return Response::deny('Anda tidak memiliki izin untuk meninjau Tabulation Bid ini.');
    }

    public function award(User $user, TabulationBid $bid): Response|bool
    {
        if (($user->hasRole('procurement_manager') || $user->hasRole('procurement_admin'))
            && $bid->status === 'forwarded_admin') {
            return true;
        }

        return Response::deny('Anda tidak memiliki izin untuk memberikan award pada Tabulation Bid ini.');
    }

    public function createPo(User $user, TabulationBid $bid): Response|bool
    {
        if ($user->hasRole('procurement_admin')
            && $user->id !== $bid->created_by
            && $bid->award()->exists()
            && $bid->status === 'forwarded_admin') {
            return true;
        }

        return Response::deny('Anda tidak memiliki izin untuk membuat PO dari Tabulation Bid ini.');
    }
}
