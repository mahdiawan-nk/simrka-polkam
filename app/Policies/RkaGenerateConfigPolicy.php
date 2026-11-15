<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RkaGenerateConfig;
use Illuminate\Auth\Access\HandlesAuthorization;

class RkaGenerateConfigPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RkaGenerateConfig');
    }

    public function view(AuthUser $authUser, RkaGenerateConfig $rkaGenerateConfig): bool
    {
        return $authUser->can('View:RkaGenerateConfig');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RkaGenerateConfig');
    }

    public function update(AuthUser $authUser, RkaGenerateConfig $rkaGenerateConfig): bool
    {
        return $authUser->can('Update:RkaGenerateConfig');
    }

    public function delete(AuthUser $authUser, RkaGenerateConfig $rkaGenerateConfig): bool
    {
        return $authUser->can('Delete:RkaGenerateConfig');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RkaGenerateConfig');
    }

    public function restore(AuthUser $authUser, RkaGenerateConfig $rkaGenerateConfig): bool
    {
        return $authUser->can('Restore:RkaGenerateConfig');
    }

    public function forceDelete(AuthUser $authUser, RkaGenerateConfig $rkaGenerateConfig): bool
    {
        return $authUser->can('ForceDelete:RkaGenerateConfig');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RkaGenerateConfig');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RkaGenerateConfig');
    }

    public function replicate(AuthUser $authUser, RkaGenerateConfig $rkaGenerateConfig): bool
    {
        return $authUser->can('Replicate:RkaGenerateConfig');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RkaGenerateConfig');
    }

}