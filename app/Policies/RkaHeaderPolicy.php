<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RkaHeader;
use Illuminate\Auth\Access\HandlesAuthorization;

class RkaHeaderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RkaHeader');
    }

    public function view(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        return $authUser->can('View:RkaHeader');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RkaHeader');
    }

    public function update(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        return $authUser->can('Update:RkaHeader');
    }

    public function delete(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        return $authUser->can('Delete:RkaHeader');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RkaHeader');
    }

    public function restore(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        return $authUser->can('Restore:RkaHeader');
    }

    public function forceDelete(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        return $authUser->can('ForceDelete:RkaHeader');
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
        return $authUser->can('Replicate:RkaHeader');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RkaHeader');
    }

}