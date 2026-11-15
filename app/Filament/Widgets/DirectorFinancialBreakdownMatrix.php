<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Services\Context\Contracts\ContextManagerInterface;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class DirectorFinancialBreakdownMatrix extends BaseWidget
{
    use HasWidgetShield;
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 1;

    protected static ?string $heading = 'Finansial Breakdown Matrix (By Account Type)';

    public function table(Table $table): Table
    {
        $currentFiscalYear = '2026/2027';

        return $table
            ->query(
                Account::query()
                    ->whereHas('fundRequestItems.fundRequest', function ($q) use ($currentFiscalYear) {
                        $q->where('fiscal_year', $currentFiscalYear)
                            ->where('status', 'approved');
                    })
                    ->withSum(['fundRequestItems as total_realized' => function ($q) use ($currentFiscalYear) {
                        $q->whereHas('fundRequest', function ($sub) use ($currentFiscalYear) {
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
