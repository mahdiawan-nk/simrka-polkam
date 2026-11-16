<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FundRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FundRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FundRequest');
    }

    public function view(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('View:FundRequest') && $this->canAccessRecord($authUser, $fundRequest);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FundRequest');
    }

    public function update(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('Update:FundRequest') && $this->canAccessRecord($authUser, $fundRequest);
    }

    public function delete(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('Delete:FundRequest') && $this->canAccessRecord($authUser, $fundRequest);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FundRequest');
    }

    public function restore(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('Restore:FundRequest') && $this->canAccessRecord($authUser, $fundRequest);
    }

    public function forceDelete(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('ForceDelete:FundRequest') && $this->canAccessRecord($authUser, $fundRequest);
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
        return $authUser->can('Replicate:FundRequest') && $this->canAccessRecord($authUser, $fundRequest);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FundRequest');
    }

    protected function canAccessRecord(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        if ($authUser->hasAnyRole(['super_admin', 'direktur', 'keuangan', 'wadir_keuangan'])
            || $authUser->can('ViewAllUnits:FundRequest')) {
            return true;
        }

        if ($fundRequest->department_id === null) {
            return $fundRequest->created_by === $authUser->id;
        }

        return $authUser->departments()
            ->where('departments.id', $fundRequest->department_id)
            ->exists();
    }
}
