<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Services\Context\Contracts\ContextManagerInterface;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class DepartmentTopAccounts extends BaseWidget
{
    use HasWidgetShield;
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Breakdown Penyerapan per Kode Akun (Top 5 Accounts)';

    public function table(Table $table): Table
    {
        /** @var ContextManagerInterface $contextManager */
        $contextManager = app(ContextManagerInterface::class);
        $activeDept = $contextManager->getActiveDepartment();

        $deptId = $activeDept?->id ?? 0;

        return $table
            ->query(
                Account::query()
                    ->whereHas('fundRequestItems.fundRequest', function ($query) use ($deptId) {
                        $query->where('department_id', $deptId)
                            ->where('status', 'approved');
                    })
                    ->withCount(['fundRequestItems as item_count' => function ($query) use ($deptId) {
                        $query->whereHas('fundRequest', function ($q) use ($deptId) {
                            $q->where('department_id', $deptId)
                                ->where('status', 'approved');
                        });
                    }])
                    ->withSum(['fundRequestItems as total_used' => function ($query) use ($deptId) {
                        $query->whereHas('fundRequest', function ($q) use ($deptId) {
                            $q->where('department_id', $deptId)
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
