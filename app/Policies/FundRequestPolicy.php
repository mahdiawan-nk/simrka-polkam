<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\FundRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class FundRequestPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FundRequest');
    }

    public function view(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('View:FundRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FundRequest');
    }

    public function update(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('Update:FundRequest');
    }

    public function delete(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('Delete:FundRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FundRequest');
    }

    public function restore(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('Restore:FundRequest');
    }

    public function forceDelete(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('ForceDelete:FundRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FundRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FundRequest');
    }

    public function replicate(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('Replicate:FundRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FundRequest');
    }

}