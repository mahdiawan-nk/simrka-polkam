<?php

namespace App\Filament\Widgets;

use App\Models\FundRequest;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class FinanceVerificationQueue extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Antrean Verifikasi Keuangan';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FundRequest::query()
                    ->with('department:id,name')
                    ->where('status', 'submitted')
                    ->oldest('request_date')
            )
            ->paginated(5)
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('No. Dokumen')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departemen Pemohon')
                    ->searchable(),

                Tables\Columns\TextColumn::make('activity_name')
                    ->label('Kegiatan')
                    ->limit(30),

                Tables\Columns\TextColumn::make('disbursement_type')
                    ->label('Jenis Pencairan')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_requested')
                    ->label('Total Dimohonkan')
                    ->money('IDR', locale: 'id')
                    ->alignment('right')
                    ->fontFamily('mono')
                    ->sortable(),

                Tables\Columns\TextColumn::make('request_date')
                    ->label('Tgl Pengajuan')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->actions([
                Action::make('verify')
                    ->label('Verifikasi Item')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('primary')
                    ->button()
                    ->url(fn (FundRequest $record): string => route('filament.admin.resources.fund-requests.verify', $record)),
            ]);
    }
}
