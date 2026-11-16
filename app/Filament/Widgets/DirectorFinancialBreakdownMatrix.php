<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\RkaSchedule;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DirectorFinancialBreakdownMatrix extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Matriks Rincian Keuangan (Berdasarkan Jenis Akun)';

    public function table(Table $table): Table
    {
        $currentFiscalYear = RkaSchedule::currentFiscalYear();

        return $table
            ->query(
                Account::query()
                    ->whereHas('fundRequestItems', function ($q) use ($currentFiscalYear) {
                        $q->whereIn('verification_status', ['approved', 'partial'])
                            ->whereHas('fundRequest', function ($sub) use ($currentFiscalYear) {
                                $sub->where('fiscal_year', $currentFiscalYear)
                                    ->where('status', 'approved');
                            });
                    })
                    ->withSum(['fundRequestItems as total_realized' => function ($q) use ($currentFiscalYear) {
                        $q->whereIn('verification_status', ['approved', 'partial'])
                            ->whereHas('fundRequest', function ($sub) use ($currentFiscalYear) {
                                $sub->where('fiscal_year', $currentFiscalYear)
                                    ->where('status', 'approved');
                            });
                    }], 'approved_price')
                    ->orderByDesc('total_realized')
                    ->limit(6)
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->fontFamily('mono')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Kelompok Akun / Deskripsi')
                    ->wrap(),

                Tables\Columns\TextColumn::make('total_realized')
                    ->label('Realisasi (Rp)')
                    ->money('IDR', locale: 'id')
                    ->alignment('right')
                    ->fontFamily('mono')
                    ->color('success'),
            ]);
    }
}
