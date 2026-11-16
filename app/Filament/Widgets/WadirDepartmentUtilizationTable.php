<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\RkaSchedule;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class WadirDepartmentUtilizationTable extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Laporan Penyerapan Anggaran Unit';

    public function table(Table $table): Table
    {
        $currentFiscalYear = RkaSchedule::currentFiscalYear();

        return $table
            ->query(
                Department::query()
                    ->whereHas('budgets', fn ($q) => $q->where('fiscal_year', $currentFiscalYear))
                    ->withSum(['budgets as total_pagu' => fn ($q) => $q->where('fiscal_year', $currentFiscalYear)], 'allocated_amount')
                    ->withSum(['fundRequests as total_approved_sum' => fn ($q) => $q->where('fiscal_year', $currentFiscalYear)->where('status', 'approved')], 'total_approved')
                    ->withSum(['fundRequests as total_pending_sum' => fn ($q) => $q->where('fiscal_year', $currentFiscalYear)->whereIn('status', ['submitted', 'verified_kabakku', 'verified_wadir_bakku'])], 'total_requested')
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Dept')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_pagu')
                    ->label('Pagu Anggaran')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->alignment('right')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('total_approved_sum')
                    ->label('Disetujui (Approved)')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->alignment('right')
                    ->fontFamily('mono')
                    ->color('success'),

                Tables\Columns\TextColumn::make('total_pending_sum')
                    ->label('Diproses (Pending)')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->alignment('right')
                    ->fontFamily('mono')
                    ->color('warning'),

                Tables\Columns\TextColumn::make('sisa_pagu')
                    ->label('Sisa Pagu')
                    ->state(function ($record): float {
                        $pagu = (float) ($record->total_pagu ?? 0);
                        $approved = (float) ($record->total_approved_sum ?? 0);
                        $pending = (float) ($record->total_pending_sum ?? 0);

                        return $pagu - ($approved + $pending);
                    })
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 2, ',', '.'))
                    ->alignment('right')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('absorption_percentage')
                    ->label('% Penyerapan')
                    ->state(function ($record): float {
                        $pagu = (float) ($record->total_pagu ?? 0);
                        $approved = (float) ($record->total_approved_sum ?? 0);

                        return $pagu > 0 ? round(($approved / $pagu) * 100, 1) : 0.0;
                    })
                    ->formatStateUsing(fn ($state): string => $state.'%')
                    ->badge()
                    ->color(fn ($state): string => $state > 90 ? 'danger' : ($state > 75 ? 'warning' : 'success'))
                    ->alignment('center'),
            ]);
    }
}
