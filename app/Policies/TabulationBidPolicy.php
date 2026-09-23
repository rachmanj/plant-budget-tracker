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

        return Response::deny('You do not have permission to access the Tabulation Bid page.');
    }

    public function create(User $user): Response|bool
    {
        if ($user->hasRole('buyer')) {
            return true;
        }

        return Response::deny('Only Buyers can create Tabulation Bids.');
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

        return Response::deny('You do not have permission to review this Tabulation Bid.');
    }

    public function award(User $user, TabulationBid $bid): Response|bool
    {
        if (($user->hasRole('procurement_manager') || $user->hasRole('procurement_admin'))
            && $bid->status === 'forwarded_admin') {
            return true;
        }

        return Response::deny('You do not have permission to award this Tabulation Bid.');
    }

    public function createPo(User $user, TabulationBid $bid): Response|bool
    {
        if ($user->hasRole('procurement_admin')
            && $user->id !== $bid->created_by
            && $bid->award()->exists()
            && $bid->status === 'forwarded_admin') {
            return true;
        }

        return Response::deny('You do not have permission to create a PO from this Tabulation Bid.');
    }
}
