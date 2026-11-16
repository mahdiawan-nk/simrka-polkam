<?php

namespace App\Filament\Widgets;

use App\Models\FundRequest;
use App\Services\Context\Contracts\ContextManagerInterface;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DepartmentTrackingTable extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Tracking Pengajuan Dana Terakhir';

    public function table(Table $table): Table
    {
        /** @var ContextManagerInterface $contextManager */
        $contextManager = app(ContextManagerInterface::class);
        $activeDept = $contextManager->getActiveDepartment();

        $deptId = $activeDept?->id ?? 0;

        return $table
            ->query(
                FundRequest::query()
                    ->where('department_id', $deptId)
                    ->latest('request_date')
            )
            ->paginated(5)
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('No. Dokumen')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('activity_name')
                    ->label('Nama Kegiatan')
                    ->limit(35)
                    ->searchable(),

                Tables\Columns\TextColumn::make('request_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

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
                    ->label('Status Timeline')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'info',
                        'verified_kabakku' => 'warning',
                        'verified_wadir_bakku' => 'amber',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'verified_kabakku' => 'Verifikasi KABAKKU',
                        'verified_wadir_bakku' => 'Verifikasi WADIR',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => $state,
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->label('Detail')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->url(fn (FundRequest $record): string => route('filament.admin.resources.fund-requests.view', $record)),
            ]);
    }
}
