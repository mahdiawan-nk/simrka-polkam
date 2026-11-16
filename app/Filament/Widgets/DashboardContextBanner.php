<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

class DashboardContextBanner extends Widget
{
    use HasWidgetShield;

    protected string $view = 'filament.widgets.dashboard-context-banner';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $context = app(DashboardMetricsService::class)->getActiveContext();
        $user = $context['user'];
        $roleName = (string) ($context['active_role'] ?? ($user?->roles->first()?->name ?? 'pengguna'));

        $roleLabel = match ($roleName) {
            'super_admin' => 'Super Administrator',
            'keuangan' => 'Bagian Keuangan (Kabag KKU)',
            'staf_keuangan' => 'Staf Keuangan (Monitoring Read-Only)',
            'wadir_keuangan' => 'Wakil Direktur II',
            'direktur' => 'Direktur Politeknik Kampar',
            'pemohon' => 'Pemohon / Kepala Unit',
            default => ucwords(str_replace('_', ' ', $roleName)),
        };

        return [
            'userName' => $user?->name ?? 'Pengguna',
            'roleLabel' => $roleLabel,
            'departmentName' => $context['active_department']?->name ?? 'Lintas Unit / Institusi',
            'fiscalYear' => $context['fiscal_year'],
            'phaseName' => $context['active_schedule']?->phase_name ?? 'Periode Aktif',
            'updatedAt' => $context['updated_at']->format('d/m/Y H:i'),
        ];
    }
}
