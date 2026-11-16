<?php

namespace App\Filament\Resources\RkaHeaders\Pages;

use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use App\Services\Context\Contracts\ContextManagerInterface;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListRkaHeaders extends ListRecords
{
    protected static string $resource = RkaHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        $query = parent::getTableQuery();

        /** @var ContextManagerInterface $context */
        $context = app(ContextManagerInterface::class);

        // 1. Super Admin & Direktur (berdasarkan Role Aktif di ContextManager)
        // Dapat melihat SELURUH pengajuan dana tanpa dibatasi departemen
        if ($context->hasActiveRole(['super_admin', 'direktur'])) {
            return $query;
        }

        // 2. Tim Keuangan / Ka BAKKU & Wadir BAKKU (berdasarkan Role Aktif di ContextManager)
        // Hanya melihat pengajuan yang sudah di-SUBMIT (bukan status draft)
        if ($context->hasActiveRole(['keuangan', 'wadir_keuangan'])) {
            return $query->where('status', '!=', 'draft');
        }

        // 3. Pemohon / User Biasa: Filter ketat berdasarkan Department Aktif yang dipilih
        $activeDept = $context->getActiveDepartment();
        $user = auth()->user();

        return $query->where(function (Builder $q) use ($activeDept, $user) {
            if ($activeDept) {
                // Menyaring data khusus untuk departemen yang sedang aktif di context switcher
                $q->where('department_id', $activeDept->id);
            } elseif ($user) {
                // Fallback jika user tidak memiliki departemen aktif
                $q->where('created_by', $user->id);
            }
        });
    }
}
