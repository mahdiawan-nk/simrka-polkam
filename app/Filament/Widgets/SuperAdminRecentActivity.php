<?php

namespace App\Filament\Widgets;

use App\Models\FundRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class SuperAdminRecentActivity extends BaseWidget
{
    use HasWidgetShield;
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Real-time Activity Log (10 Transaksi Terbaru)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FundRequest::query()->latest('updated_at')->limit(10)
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('No. Dokumen')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departemen')
                    ->searchable(),

                Tables\Columns\TextColumn::make('activity_name')
                    ->label('Kegiatan')
                    ->limit(30),

                Tables\Columns\TextColumn::make('total_requested')
                    ->label('Dimohonkan')
                    ->money('IDR', locale: 'id')
                    ->alignment('right')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('total_approved')
                    ->label('Disetujui')
                    ->money('IDR', locale: 'id')
                    ->alignment('right')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'info',
                        'verified_kabakku', 'verified_wadir_bakku' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'verified_kabakku' => 'Verifikasi KABAKKU',
                        'verified_wadir_bakku' => 'Verifikasi WADIR',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ]);
    }
}
