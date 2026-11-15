<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RkaSchedule;
use Illuminate\Auth\Access\HandlesAuthorization;

class RkaSchedulePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RkaSchedule');
    }

    public function view(AuthUser $authUser, RkaSchedule $rkaSchedule): bool
    {
        return $authUser->can('View:RkaSchedule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RkaSchedule');
    }

    public function update(AuthUser $authUser, RkaSchedule $rkaSchedule): bool
    {
        return $authUser->can('Update:RkaSchedule');
    }

    public function delete(AuthUser $authUser, RkaSchedule $rkaSchedule): bool
    {
        return $authUser->can('Delete:RkaSchedule');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RkaSchedule');
    }

    public function restore(AuthUser $authUser, RkaSchedule $rkaSchedule): bool
    {
        return $authUser->can('Restore:RkaSchedule');
    }

    public function forceDelete(AuthUser $authUser, RkaSchedule $rkaSchedule): bool
    {
        return $authUser->can('ForceDelete:RkaSchedule');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RkaSchedule');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RkaSchedule');
    }

    public function replicate(AuthUser $authUser, RkaSchedule $rkaSchedule): bool
    {
        return $authUser->can('Replicate:RkaSchedule');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RkaSchedule');
    }

}