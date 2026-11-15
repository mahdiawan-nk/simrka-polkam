<?php

namespace App\Filament\Widgets;

use App\Models\FundRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Actions\Action;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
class DirectorExecutiveApprovalQueue extends BaseWidget
{
    use HasWidgetShield;
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Executive Approval Queue (Antrean Persetujuan Direktur)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FundRequest::query()
                    ->where('status', 'verified_wadir_bakku')
                    ->latest('request_date')
            )
            ->paginated(5)
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('No. Dokumen')
                    ->fontFamily('mono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departemen')
                    ->searchable(),

                Tables\Columns\TextColumn::make('activity_name')
                    ->label('Nama Kegiatan / Keperluan')
                    ->limit(35),

                Tables\Columns\TextColumn::make('responsible_person')
                    ->label('Penanggung Jawab'),

                Tables\Columns\TextColumn::make('total_approved')
                    ->label('Nominal Disetujui Keuangan')
                    ->money('IDR', locale: 'id')
                    ->alignment('right')
                    ->fontFamily('mono')
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('request_date')
                    ->label('Tgl Pengajuan')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Tinjau & Setujui')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->button()
                    ->url(fn(FundRequest $record): string => route('filament.admin.resources.fund-requests.edit', $record)),
            ]);
    }
}
