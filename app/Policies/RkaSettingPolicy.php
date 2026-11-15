<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RkaSetting;
use Illuminate\Auth\Access\HandlesAuthorization;

class RkaSettingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RkaSetting');
    }

    public function view(AuthUser $authUser, RkaSetting $rkaSetting): bool
    {
        return $authUser->can('View:RkaSetting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RkaSetting');
    }

    public function update(AuthUser $authUser, RkaSetting $rkaSetting): bool
    {
        return $authUser->can('Update:RkaSetting');
    }

    public function delete(AuthUser $authUser, RkaSetting $rkaSetting): bool
    {
        return $authUser->can('Delete:RkaSetting');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RkaSetting');
    }

    public function restore(AuthUser $authUser, RkaSetting $rkaSetting): bool
    {
        return $authUser->can('Restore:RkaSetting');
    }

    public function forceDelete(AuthUser $authUser, RkaSetting $rkaSetting): bool
    {
        return $authUser->can('ForceDelete:RkaSetting');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RkaSetting');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RkaSetting');
    }

    public function replicate(AuthUser $authUser, RkaSetting $rkaSetting): bool
    {
        return $authUser->can('Replicate:RkaSetting');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RkaSetting');
    }

}