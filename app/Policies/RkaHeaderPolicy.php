<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RkaHeader;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RkaHeaderPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RkaHeader');
    }

    public function view(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        return $authUser->can('View:RkaHeader') && $this->canAccessRecord($authUser, $rkaHeader);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RkaHeader');
    }

    public function update(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        if ($rkaHeader->is_locked && ! $authUser->hasRole('super_admin')) {
            return false;
        }

        return $authUser->can('Update:RkaHeader') && $this->canAccessRecord($authUser, $rkaHeader);
    }

    public function delete(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        if ($rkaHeader->is_locked && ! $authUser->hasRole('super_admin')) {
            return false;
        }

        return $authUser->can('Delete:RkaHeader') && $this->canAccessRecord($authUser, $rkaHeader);
    }

    public function importApproved(AuthUser $authUser, ?RkaHeader $rkaHeader = null): bool
    {
        if (! $authUser->hasAnyRole(['pemohon', 'super_admin'])) {
            return false;
        }

        if ($rkaHeader !== null) {
            return $this->canAccessRecord($authUser, $rkaHeader);
        }

        return true;
    }

    public function requestUnlock(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        if (! $authUser->hasRole('pemohon')) {
            return false;
        }

        return $this->canAccessRecord($authUser, $rkaHeader)
            && strtoupper((string) $rkaHeader->status) === RkaHeader::STATUS_APPROVED
            && (bool) $rkaHeader->is_locked;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RkaHeader');
    }

    public function restore(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        return $authUser->can('Restore:RkaHeader') && $this->canAccessRecord($authUser, $rkaHeader);
    }

    public function forceDelete(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        return $authUser->can('ForceDelete:RkaHeader') && $this->canAccessRecord($authUser, $rkaHeader);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RkaHeader');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RkaHeader');
    }

    public function replicate(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        return $authUser->can('Replicate:RkaHeader') && $this->canAccessRecord($authUser, $rkaHeader);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RkaHeader');
    }

    protected function canAccessRecord(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        if ($authUser->hasAnyRole(['super_admin', 'direktur', 'keuangan', 'wadir_keuangan'])) {
            return true;
        }

        if ($rkaHeader->department_id === null) {
            return $rkaHeader->created_by === $authUser->id;
        }

        return $authUser->departments()
            ->where('departments.id', $rkaHeader->department_id)
            ->exists();
    }
}
