<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RkaUnlockRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RkaUnlockRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->isFinanceApprover($user)
            || $user->hasRole('pemohon')
            || $user->can('ViewAny:RkaUnlockRequest');
    }

    public function view(User $user, RkaUnlockRequest $request): bool
    {
        if ($this->isFinanceApprover($user) || $user->can('View:RkaUnlockRequest')) {
            return true;
        }

        if ($user->hasRole('pemohon')) {
            return $user->departments()
                ->where('departments.id', $request->department_id)
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('pemohon') || $user->can('Create:RkaUnlockRequest');
    }

    public function approve(User $user, RkaUnlockRequest $request): bool
    {
        return $this->isFinanceApprover($user) || $user->can('Approve:RkaUnlockRequest');
    }

    public function reject(User $user, RkaUnlockRequest $request): bool
    {
        return $this->isFinanceApprover($user) || $user->can('Reject:RkaUnlockRequest');
    }

    public function isFinanceApprover(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'keuangan', 'wadir_keuangan']);
    }
}
