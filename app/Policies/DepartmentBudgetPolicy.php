<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DepartmentBudget;
use Illuminate\Auth\Access\HandlesAuthorization;

class DepartmentBudgetPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DepartmentBudget');
    }

    public function view(AuthUser $authUser, DepartmentBudget $departmentBudget): bool
    {
        return $authUser->can('View:DepartmentBudget');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DepartmentBudget');
    }

    public function update(AuthUser $authUser, DepartmentBudget $departmentBudget): bool
    {
        return $authUser->can('Update:DepartmentBudget');
    }

    public function delete(AuthUser $authUser, DepartmentBudget $departmentBudget): bool
    {
        return $authUser->can('Delete:DepartmentBudget');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DepartmentBudget');
    }

    public function restore(AuthUser $authUser, DepartmentBudget $departmentBudget): bool
    {
        return $authUser->can('Restore:DepartmentBudget');
    }

    public function forceDelete(AuthUser $authUser, DepartmentBudget $departmentBudget): bool
    {
        return $authUser->can('ForceDelete:DepartmentBudget');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DepartmentBudget');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DepartmentBudget');
    }

    public function replicate(AuthUser $authUser, DepartmentBudget $departmentBudget): bool
    {
        return $authUser->can('Replicate:DepartmentBudget');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DepartmentBudget');
    }

}