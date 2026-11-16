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
        return $authUser->can('View:FundRequest')
            && $this->canAccessDepartmentRecord($authUser, $fundRequest->department_id, $fundRequest->created_by);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FundRequest');
    }

    public function update(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('Update:FundRequest')
            && $this->canAccessDepartmentRecord($authUser, $fundRequest->department_id, $fundRequest->created_by);
    }

    public function delete(AuthUser $authUser, FundRequest $fundRequest): bool
    {
        return $authUser->can('Delete:FundRequest')
            && $this->canAccessDepartmentRecord($authUser, $fundRequest->department_id, $fundRequest->created_by);
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

    private function canAccessDepartmentRecord(AuthUser $authUser, ?int $departmentId, ?int $createdBy = null): bool
    {
        if (method_exists($authUser, 'hasAnyRole') && $authUser->hasAnyRole(['super_admin', 'direktur', 'keuangan', 'wadir_keuangan', 'auditor'])) {
            return true;
        }

        if ($departmentId !== null && method_exists($authUser, 'userDepartments')) {
            return $authUser->userDepartments()
                ->where('department_id', $departmentId)
                ->exists();
        }

        return $createdBy !== null && (int) $authUser->getAuthIdentifier() === $createdBy;
    }
}
