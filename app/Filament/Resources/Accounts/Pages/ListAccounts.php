<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Exports\AccountTemplateExport;
use App\Exports\AccountExport;
use App\Filament\Imports\AccountImporter;
use App\Filament\Resources\Accounts\AccountResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AccountImport;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->label('Tambah Akun')
                ->icon('heroicon-o-plus')
                ->color('primary'),

            Action::make('downloadTemplate')
                ->label('Download Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->action(fn() => (new AccountTemplateExport)->download(time() . '_ Template_Import_COA_RKA.xlsx')),

            Action::make('importAkun')
                ->label('Import Akun')
                ->icon('heroicon-o-document-arrow-up')
                ->color('info')
                ->form([
                    FileUpload::make('attachment')
                        ->label('File Excel')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        // Jalankan import menggunakan class AccountImport
                        Excel::import(new AccountImport, $data['attachment']);

                        Notification::make()
                            ->title('Import Berhasil')
                            ->body('Data akun berhasil diimport.')
                            ->success()
                            ->send();
                    } catch (\Throwable $th) {
                        Notification::make()
                            ->title('Import Gagal')
                            ->body($th->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('exportAccounts')
                ->label('Export Akun')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->action(fn() => Excel::download(new AccountExport, time() . '_ Export_COA_RKA.xlsx')),
        ];
    }
}
