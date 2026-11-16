<?php

namespace App\Filament\Widgets;

use App\Models\FundRequest;
use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class FinanceMonitoringTable extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Pemantauan Pengajuan Pencairan Dana (Read-Only)';

    public function table(Table $table): Table
    {
        $service = app(DashboardMetricsService::class);
        $context = $service->getActiveContext();
        $user = Auth::user();
        $canViewAll = $service->canViewAllUnits($user, 'ViewAllUnits:FundRequest');

        $query = FundRequest::query()
            ->with('department:id,name')
            ->where('fiscal_year', $context['fiscal_year'])
            ->latest('request_date');

        if (! $canViewAll) {
            $deptIds = $user?->departments()->pluck('departments.id')->all() ?? [];
            $query->whereIn('department_id', empty($deptIds) ? [0] : $deptIds);
        }

        return $table
            ->query($query)
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('No. Dokumen')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Unit Kerja')
                    ->searchable(),

                Tables\Columns\TextColumn::make('activity_name')
                    ->label('Kegiatan')
                    ->limit(35),

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
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'info',
                        'verified_kabakku', 'verified_wadir_bakku' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'submitted' => 'Menunggu Verifikasi',
                        'verified_kabakku' => 'Terverifikasi Keuangan',
                        'verified_wadir_bakku' => 'Disetujui Wadir II',
                        'approved' => 'Disetujui Final',
                        'rejected' => 'Ditolak',
                        default => $state,
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->label('Lihat')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->visible(fn (FundRequest $record): bool => (bool) Auth::user()?->can('view', $record))
                    ->url(fn (FundRequest $record): string => route('filament.admin.resources.fund-requests.view', $record)),
            ]);
    }
}
