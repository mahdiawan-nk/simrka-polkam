<?php

namespace App\Services\Context\Contracts;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
interface ContextManagerInterface
{
    /**
     * Dapatkan department yang sedang aktif.
     */
    public function getActiveDepartment(): ?Department;

    /**
     * Set department aktif untuk user berdasarkan department ID.
     */
    public function setActiveDepartment(int $departmentId): bool;

    /**
     * Dapatkan tipe penugasan department yang sedang aktif (primary / plt).
     */
    public function getActiveAssignmentType(): ?string;

    /**
     * Ambil semua daftar department yang berhak diakses user saat ini.
     */
    public function getAvailableDepartments();

    /**
     * Cek apakah user sedang dalam context PLT.
     */
    public function isPltContext(): bool;

    /**
     * Clear context yang tersimpan di session.
     */
    public function clearContext(): void;

    // --- MANAJEMEN ROLE CONTEXT ---

    /**
     * Dapatkan nama role yang sedang aktif.
     */
    public function getActiveRole(): ?string;

    /**
     * Dapatkan object Role yang sedang aktif.
     */
    public function getActiveRoleModel(): ?Role;

    /**
     * Set role aktif untuk user berdasarkan nama role.
     */
    public function setActiveRole(string $roleName): bool;

    /**
     * Ambil semua daftar role yang dimiliki user saat ini.
     */
    public function getAvailableRoles(): Collection;

    /**
     * Cek apakah role aktif saat ini sesuai dengan nama role tertentu.
     */
    public function hasActiveRole(string|array $roles): bool;

    /**
     * Clear context role yang tersimpan di session.
     */
    public function clearRoleContext(): void;

    /**
     * Clear seluruh context (Department & Role).
     */
    public function clearAllContext(): void;
}
