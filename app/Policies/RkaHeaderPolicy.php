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
        return $authUser->can('View:RkaHeader')
            && $this->canAccessDepartmentRecord($authUser, $rkaHeader->department_id);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RkaHeader');
    }

    public function update(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        return $authUser->can('Update:RkaHeader')
            && $this->canAccessDepartmentRecord($authUser, $rkaHeader->department_id);
    }

    public function delete(AuthUser $authUser, RkaHeader $rkaHeader): bool
    {
        return $authUser->can('Delete:RkaHeader')
            && $this->canAccessDepartmentRecord($authUser, $rkaHeader->department_id);
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

    private function canAccessDepartmentRecord(AuthUser $authUser, ?int $departmentId): bool
    {
        if (method_exists($authUser, 'hasAnyRole') && $authUser->hasAnyRole(['super_admin', 'direktur', 'keuangan', 'wadir_keuangan', 'auditor'])) {
            return true;
        }

        if ($departmentId !== null && method_exists($authUser, 'userDepartments')) {
            return $authUser->userDepartments()
                ->where('department_id', $departmentId)
                ->exists();
        }

        return false;
    }
}
