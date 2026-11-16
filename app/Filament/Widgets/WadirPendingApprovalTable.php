<?php

namespace App\Filament\Widgets;

use App\Models\FundRequest;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class WadirPendingApprovalTable extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Antrean Persetujuan Wadir BAKKU';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FundRequest::query()
                    ->where('status', 'verified_kabakku')
                    ->with(['department', 'approvals' => fn ($q) => $q->where('stage', 'kabakku')->latest()])
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
                    ->label('Nama Kegiatan')
                    ->limit(25),

                Tables\Columns\TextColumn::make('total_requested')
                    ->label('Dimohonkan')
                    ->money('IDR', locale: 'id')
                    ->alignment('right')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('total_approved')
                    ->label('Hasil Verifikasi Kabakku')
                    ->money('IDR', locale: 'id')
                    ->alignment('right')
                    ->fontFamily('mono')
                    ->color('success'),

                Tables\Columns\TextColumn::make('approvals')
                    ->label('Catatan Kabakku')
                    ->formatStateUsing(fn ($record) => $record->approvals->first()?->note ?? '-')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->approvals->first()?->note),
            ])
            ->actions([
                Action::make('review')
                    ->label('Proses Approval')
                    ->icon('heroicon-m-pencil-square')
                    ->color('primary')
                    ->button()
                    ->url(fn (FundRequest $record): string => route('filament.admin.resources.fund-requests.verify', $record)),
            ]);
    }
}
