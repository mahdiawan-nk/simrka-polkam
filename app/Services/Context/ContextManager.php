<?php

namespace App\Services\Context;

use App\Models\Department;
use App\Models\User;
use App\Services\Context\Contracts\ContextManagerInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Collection;

class ContextManager implements ContextManagerInterface
{
    protected const SESSION_KEY = 'active_department_id';
    protected const SESSION_ROLE_KEY = 'active_role';

    /**
     * User yang sedang diautentikasi.
     */
    protected ?User $user;

    public function __construct()
    {
        /** @var User|null $user */
        $user = Auth::user();
        $this->user = $user;
    }

    public function getActiveDepartment(): ?Department
    {
        if (!$this->user) {
            return null;
        }

        $sessionDeptId = Session::get(self::SESSION_KEY);

        if ($sessionDeptId) {
            $department = $this->user->departments()
                ->where('departments.id', $sessionDeptId)
                ->first();

            if ($department) {
                return $department;
            }
        }

        // Fallback: Ambil Primary Department jika session belum ada / invalid
        $primaryDept = $this->user->primaryDepartment()->first();

        if ($primaryDept) {
            $this->setActiveDepartment($primaryDept->id);
            return $primaryDept;
        }

        // Fallback opsional jika tidak ada primary: ambil department pertama dari relasi
        $firstDept = $this->user->departments()->first();
        if ($firstDept) {
            $this->setActiveDepartment($firstDept->id);
            return $firstDept;
        }

        return null;
    }

    public function setActiveDepartment(int $departmentId): bool
    {
        if (!$this->user) {
            return false;
        }

        // Pastikan department memang terhubung dengan user ini
        $exists = $this->user->departments()->where('departments.id', $departmentId)->exists();

        if ($exists) {
            Session::put(self::SESSION_KEY, $departmentId);
            return true;
        }

        return false;
    }

    public function getActiveAssignmentType(): ?string
    {
        $activeDept = $this->getActiveDepartment();

        if (!$activeDept) {
            return null;
        }

        return $activeDept->pivot->assignment_type ?? null;
    }

    public function getAvailableDepartments()
    {
        if (!$this->user) {
            return collect();
        }

        return $this->user->departments;
    }

    public function isPltContext(): bool
    {
        return $this->getActiveAssignmentType() === 'plt';
    }

    public function clearContext(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    // ==========================================
    // ROLE CONTEXT MANAGEMENT
    // ==========================================

    public function getActiveRole(): ?string
    {
        if (!$this->user) {
            return null;
        }

        $sessionRole = Session::get(self::SESSION_ROLE_KEY);

        // Pastikan role di session valid & memang dimiliki oleh user saat ini
        if ($sessionRole && $this->user->hasRole($sessionRole)) {
            return $sessionRole;
        }

        // Fallback: Ambil role pertama milik user
        $firstRole = $this->user->roles->first()?->name;

        if ($firstRole) {
            $this->setActiveRole($firstRole);
            return $firstRole;
        }

        return null;
    }

    public function getActiveRoleModel(): ?Role
    {
        $activeRoleName = $this->getActiveRole();

        if (!$activeRoleName || !$this->user) {
            return null;
        }

        return $this->user->roles->firstWhere('name', $activeRoleName);
    }

    public function setActiveRole(string $roleName): bool
    {
        if (!$this->user) {
            return false;
        }

        if ($this->user->hasRole($roleName)) {
            Session::put(self::SESSION_ROLE_KEY, $roleName);
            return true;
        }

        return false;
    }

    public function getAvailableRoles(): Collection
    {
        if (!$this->user) {
            return collect();
        }

        return $this->user->roles;
    }

    public function hasActiveRole(string|array $roles): bool
    {
        $activeRole = $this->getActiveRole();

        if (!$activeRole) {
            return false;
        }

        if (is_array($roles)) {
            return in_array($activeRole, $roles, true);
        }

        return $activeRole === $roles;
    }

    public function clearRoleContext(): void
    {
        Session::forget(self::SESSION_ROLE_KEY);
    }

    public function clearAllContext(): void
    {
        $this->clearContext();
        $this->clearRoleContext();
    }
}
