<?php

namespace App\Filament\Resources\FundRequests\Tables;

use App\Filament\Resources\FundRequests\FundRequestResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class FundRequestsTable
{
    protected static string $resource = FundRequestResource::class;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('No. Surat / Nota')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->icon('heroicon-o-document-text'),

                TextColumn::make('request_date')
                    ->label('Tgl Permohonan')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('department.name')
                    ->label('Unit / Departemen')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-building-office-2'),

                TextColumn::make('activity_name')
                    ->label('Nama Kegiatan')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->activity_name),

                TextColumn::make('total_requested')
                    ->label('Total Dimohonkan')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->alignEnd()
                    ->weight('semibold')
                    ->summarize(
                        Sum::make()
                            ->label('Total Permohonan')
                            ->money('IDR', locale: 'id')
                    ),

                TextColumn::make('total_approved')
                    ->label('Total Disetujui')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('Rp 0')
                    ->summarize(
                        Sum::make()
                            ->label('Total Disetujui')
                            ->money('IDR', locale: 'id')
                    ),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'draft' => 'gray',
                        'submitted' => 'info',
                        'pending', 'verified_kabakku', 'verified_wadir_bakku', 'revision', 'in_review' => 'warning',
                        'approved', 'paid', 'completed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match (strtolower($state)) {
                        'draft' => 'Draft',
                        'pending' => 'Menunggu',
                        'submitted' => 'Diajukan',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => ucfirst($state),
                    }),

                TextColumn::make('approvals_count')
                    ->label('Log Persetujuan')
                    ->counts('approvals')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state} Riwayat" : 'Belum Ada')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->alignCenter()
                    ->action(
                        Action::make('viewApprovalLogsFromColumn')
                            ->modalHeading(fn ($record) => "Riwayat Persetujuan - {$record->document_number}")
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Tutup')
                            ->modalContent(fn ($record): View => view('filament.resources.fund-requests.modals.approval-logs', [
                                'record' => $record->load('approvals.user'),
                            ]))
                    ),

                TextColumn::make('fiscal_year')
                    ->label('Tahun Anggaran')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->alignment('center')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('execution_date')
                    ->label('Waktu Pelaksanaan')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('responsible_person')
                    ->label('Penanggung Jawab')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('disbursement_type')
                    ->label('Jenis Pencairan')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('recipient_name')
                    ->label('Penerima')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('bank_account_info')
                    ->label('Info Rekening')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('createdBy.name')
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status Pengajuan')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Diajukan',
                        'pending' => 'Menunggu',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),

                SelectFilter::make('department_id')
                    ->label('Unit / Departemen')
                    ->relationship('department', 'name')
                    ->visible(fn () => ! auth()->user()->hasRole('pemohon'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('fiscal_year')
                    ->label('Tahun Anggaran')
                    ->options(fn () => array_combine(
                        range(date('Y') + 1, date('Y') - 5),
                        range(date('Y') + 1, date('Y') - 5)
                    )),

                Filter::make('request_date')
                    ->label('Tanggal Permohonan')
                    ->form([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('request_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('request_date', '<=', $date),
                            );
                    }),
            ], layout: FiltersLayout::BeforeContent)
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => FundRequestResource::getUrl('view', ['record' => $record]))
                    ->label('Lihat Detail')
                    ->color('info'),

                Action::make('verify')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['keuangan', 'wadir_keuangan', 'direktur']) ?? false)
                    ->url(fn ($record) => FundRequestResource::getUrl('verify', ['record' => $record])),

                Action::make('previewPdf')
                    ->label('Cetak PDF')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->openUrlInNewTab()
                    ->url(fn ($record) => route('fund-requests.pdf-preview', ['record' => $record])),

                EditAction::make()
                    ->visible(fn ($record) => strtolower($record->status) === 'draft' || strtolower($record->status) === 'rejected')
                    ->label('Ubah'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
