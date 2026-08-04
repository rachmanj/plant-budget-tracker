<?php

namespace App\Policies;

use App\Models\RequestApproval;
use App\Models\TabulationBid;
use App\Models\User;
use App\Services\Approval\ApprovalEngine;

class TabulationBidPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('buyer');
    }

    public function view(User $user, TabulationBid $bid): bool
    {
        return true;
    }

    public function review(User $user, TabulationBid $bid): bool
    {
        return $user->hasRole('procurement_manager') && $bid->status === 'pending_proc_mgr';
    }

    public function award(User $user, TabulationBid $bid): bool
    {
        return ($user->hasRole('procurement_manager') || $user->hasRole('procurement_admin'))
            && $bid->status === 'forwarded_admin';
    }

    public function createPo(User $user, TabulationBid $bid): bool
    {
        return $user->hasRole('procurement_admin')
            && $user->id !== $bid->created_by
            && $bid->award()->exists()
            && $bid->status === 'forwarded_admin';
    }
}
