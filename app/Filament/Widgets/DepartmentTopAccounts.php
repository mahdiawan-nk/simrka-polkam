<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DepartmentTopAccounts extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Rincian Penyerapan per Kode Akun (5 Teratas)';

    public function table(Table $table): Table
    {
        $service = app(DashboardMetricsService::class);
        $context = $service->getActiveContext();
        $activeDept = $context['active_department'];
        $fiscalYear = $context['fiscal_year'];

        $deptId = $activeDept?->id ?? 0;

        return $table
            ->query(
                Account::query()
                    ->whereHas('fundRequestItems', function ($query) use ($deptId, $fiscalYear) {
                        $query->whereIn('verification_status', ['approved', 'partial'])
                            ->whereHas('fundRequest', function ($q) use ($deptId, $fiscalYear) {
                                $q->where('department_id', $deptId)
                                    ->where('fiscal_year', $fiscalYear)
                                    ->where('status', 'approved');
                            });
                    })
                    ->withCount(['fundRequestItems as item_count' => function ($query) use ($deptId, $fiscalYear) {
                        $query->whereIn('verification_status', ['approved', 'partial'])
                            ->whereHas('fundRequest', function ($q) use ($deptId, $fiscalYear) {
                                $q->where('department_id', $deptId)
                                    ->where('fiscal_year', $fiscalYear)
                                    ->where('status', 'approved');
                            });
                    }])
                    ->withSum(['fundRequestItems as total_used' => function ($query) use ($deptId, $fiscalYear) {
                        $query->whereIn('verification_status', ['approved', 'partial'])
                            ->whereHas('fundRequest', function ($q) use ($deptId, $fiscalYear) {
                                $q->where('department_id', $deptId)
                                    ->where('fiscal_year', $fiscalYear)
                                    ->where('status', 'approved');
                            });
                    }], 'approved_price')
                    ->orderByDesc('total_used')
                    ->limit(5)
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Akun')
                    ->fontFamily('mono')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Akun COA')
                    ->wrap(),

                Tables\Columns\TextColumn::make('item_count')
                    ->label('Jumlah Item')
                    ->alignment('center')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('total_used')
                    ->label('Total Terpakai')
                    ->money('IDR', locale: 'id')
                    ->alignment('right')
                    ->fontFamily('mono'),
            ]);
    }
}
