<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RkaAdjustmentMatch;
use App\Models\RkaHeader;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RkaAdjustmentMatchPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->isFinanceOversight($user)
            || $user->hasRole('pemohon')
            || $user->can('ViewAny:RkaAdjustmentMatch');
    }

    public function monitor(User $user): bool
    {
        return $this->isFinanceOversight($user)
            || $user->can('Monitor:RkaAdjustmentMatch');
    }

    public function view(User $user, RkaAdjustmentMatch $match): bool
    {
        if ($this->isFinanceOversight($user) || $user->can('View:RkaAdjustmentMatch')) {
            return true;
        }

        if ($user->hasRole('pemohon')) {
            $deptIds = $user->departments()->pluck('departments.id')->map(fn ($id): int => (int) $id)->all();

            if (in_array((int) $match->source_department_id, $deptIds, true)) {
                return true;
            }

            if ($match->target_department_id !== null && in_array((int) $match->target_department_id, $deptIds, true)) {
                return $match->sourceAdjustment?->rkaHeader?->status === RkaHeader::STATUS_APPROVED;
            }
        }

        return false;
    }

    public function adjust(User $user, RkaAdjustmentMatch $match): bool
    {
        return $this->isFinanceOversight($user)
            || $user->can('Adjust:RkaAdjustmentMatch');
    }

    public function cancel(User $user, RkaAdjustmentMatch $match): bool
    {
        return $this->isFinanceOversight($user)
            || $user->can('Cancel:RkaAdjustmentMatch');
    }

    public function rerun(User $user): bool
    {
        return $this->isFinanceOversight($user)
            || $user->can('Rerun:RkaAdjustmentMatch');
    }

    public function isFinanceOversight(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'keuangan', 'finance', 'wadir_keuangan']);
    }
}
