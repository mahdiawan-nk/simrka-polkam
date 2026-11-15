<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class WadirDepartmentUtilizationTable extends BaseWidget
{
    use HasWidgetShield;
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Department Budget Utilization Table (Laporan Penyerapan Anggaran Unit)';

    public function table(Table $table): Table
    {
        $currentFiscalYear = '2026/2027';

        return $table
            ->query(
                Department::query()
                    ->whereHas('budgets', fn($q) => $q->where('fiscal_year', $currentFiscalYear))
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Dept')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('id')
                    ->label('Pagu Anggaran')
                    ->formatStateUsing(function ($state) use ($currentFiscalYear) {
                        $pagu = DepartmentBudget::where('department_id', $state)
                            ->where('fiscal_year', $currentFiscalYear)
                            ->sum('allocated_amount');
                        return 'Rp ' . number_format($pagu, 2, ',', '.');
                    })
                    ->alignment('right')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Disetujui (Approved)')
                    ->formatStateUsing(function ($record) use ($currentFiscalYear) {
                        $approved = FundRequest::where('department_id', $record->id)
                            ->where('fiscal_year', $currentFiscalYear)
                            ->where('status', 'approved')
                            ->sum('total_approved');
                        return 'Rp ' . number_format($approved, 2, ',', '.');
                    })
                    ->alignment('right')
                    ->fontFamily('mono')
                    ->color('success'),

                Tables\Columns\TextColumn::make('level_code')
                    ->label('Diproses (Pending)')
                    ->formatStateUsing(function ($record) use ($currentFiscalYear) {
                        $pending = FundRequest::where('department_id', $record->id)
                            ->where('fiscal_year', $currentFiscalYear)
                            ->whereIn('status', ['submitted', 'verified_kabakku', 'verified_wadir_bakku'])
                            ->sum('total_requested');
                        return 'Rp ' . number_format($pending, 2, ',', '.');
                    })
                    ->alignment('right')
                    ->fontFamily('mono')
                    ->color('warning'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sisa Pagu')
                    ->formatStateUsing(function ($record) use ($currentFiscalYear) {
                        $pagu = DepartmentBudget::where('department_id', $record->id)
                            ->where('fiscal_year', $currentFiscalYear)
                            ->sum('allocated_amount');
                        $approved = FundRequest::where('department_id', $record->id)
                            ->where('fiscal_year', $currentFiscalYear)
                            ->where('status', 'approved')
                            ->sum('total_approved');
                        $pending = FundRequest::where('department_id', $record->id)
                            ->where('fiscal_year', $currentFiscalYear)
                            ->whereIn('status', ['submitted', 'verified_kabakku', 'verified_wadir_bakku'])
                            ->sum('total_requested');

                        $remaining = $pagu - ($approved + $pending);
                        return 'Rp ' . number_format($remaining, 2, ',', '.');
                    })
                    ->alignment('right')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('% Penyerapan')
                    ->formatStateUsing(function ($record) use ($currentFiscalYear) {
                        $pagu = DepartmentBudget::where('department_id', $record->id)
                            ->where('fiscal_year', $currentFiscalYear)
                            ->sum('allocated_amount');
                        $approved = FundRequest::where('department_id', $record->id)
                            ->where('fiscal_year', $currentFiscalYear)
                            ->where('status', 'approved')
                            ->sum('total_approved');

                        $percentage = $pagu > 0 ? round(($approved / $pagu) * 100, 1) : 0;
                        return $percentage . '%';
                    })
                    ->badge()
                    ->color(function ($record) use ($currentFiscalYear) {
                        $pagu = DepartmentBudget::where('department_id', $record->id)
                            ->where('fiscal_year', $currentFiscalYear)
                            ->sum('allocated_amount');
                        $approved = FundRequest::where('department_id', $record->id)
                            ->where('fiscal_year', $currentFiscalYear)
                            ->where('status', 'approved')
                            ->sum('total_approved');
                        $pct = $pagu > 0 ? ($approved / $pagu) * 100 : 0;

                        return $pct > 90 ? 'danger' : ($pct > 75 ? 'warning' : 'success');
                    })
                    ->alignment('center'),
            ]);
    }
}
