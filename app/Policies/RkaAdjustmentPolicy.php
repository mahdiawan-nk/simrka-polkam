<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RkaAdjustment;
use App\Models\RkaHeader;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RkaAdjustmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'direktur', 'keuangan', 'wadir_keuangan', 'pemohon'])
            || $user->can('ViewAny:RkaAdjustment');
    }

    public function view(User $user, RkaAdjustment $adjustment): bool
    {
        if ($user->hasAnyRole(['super_admin', 'direktur', 'keuangan', 'wadir_keuangan'])) {
            return true;
        }

        $userDeptIds = $user->departments()->pluck('departments.id')->all();
        if ($userDeptIds === []) {
            return false;
        }

        $adjustment->loadMissing('rkaHeader');
        $header = $adjustment->rkaHeader;

        if ($header && in_array((int) $header->department_id, $userDeptIds, true)) {
            return true;
        }

        $isRelatedCrossUnit = ($adjustment->destination_unit_id !== null && in_array((int) $adjustment->destination_unit_id, $userDeptIds, true))
            || ($adjustment->target_unit_id !== null && in_array((int) $adjustment->target_unit_id, $userDeptIds, true));

        return $isRelatedCrossUnit
            && $header !== null
            && strtoupper((string) $header->status) === RkaHeader::STATUS_APPROVED;
    }
}
