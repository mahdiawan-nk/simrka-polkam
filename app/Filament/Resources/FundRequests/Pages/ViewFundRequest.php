<?php

namespace App\Filament\Resources\FundRequests\Pages;

use App\Filament\Resources\FundRequests\FundRequestResource;
use App\Services\FundRequest\FundRequestDetailService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewFundRequest extends Page
{
    use InteractsWithRecord;

    protected static string $resource = FundRequestResource::class;

    protected string $view = 'filament.resources.fund-requests.pages.view-fund-request';

    protected static ?string $title = 'Detail Pengajuan Pencairan';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $user = auth()->user();
        abort_unless($user && $user->can('view', $this->record), 403);
    }

    public function getBreadcrumbs(): array
    {
        return [
            FundRequestResource::getUrl('index') => 'Pengajuan Pencairan',
            '#' => 'Detail Pengajuan',
        ];
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        return [
            Action::make('printPdf')
                ->label('Cetak PDF')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn (): string => route('fund-requests.pdf-preview', ['record' => $this->record]))
                ->openUrlInNewTab(),

            Action::make('edit')
                ->label('Ubah Pengajuan')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (): bool => $user !== null
                    && $user->can('update', $this->record)
                    && in_array(strtolower((string) $this->record->status), ['draft', 'rejected'], true))
                ->url(fn (): string => FundRequestResource::getUrl('edit', ['record' => $this->record])),

            Action::make('back')
                ->label('Kembali ke Daftar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => FundRequestResource::getUrl('index')),
        ];
    }

    public function getDetailDataProperty(): array
    {
        return app(FundRequestDetailService::class)->build($this->record);
    }
}
