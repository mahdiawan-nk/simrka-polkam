<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Imports\UsersImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('importUsers')
                ->label('Import Excel')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->form([
                    FileUpload::make('attachment')
                        ->label('File Excel / CSV')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'text/plain',
                        ])
                        ->required()
                        ->disk('local')
                        ->directory('imports/temp'),
                ])
                ->action(function (array $data) {
                    // Gunakan Storage::disk('local')->path(...) agar mendapatkan absolut path yang akurat
                    $filePath = Storage::disk('local')->path($data['attachment']);

                    try {
                        Excel::import(new UsersImport, $filePath);

                        // (Opsional) Hapus file temporary setelah berhasil diimpor
                        Storage::disk('local')->delete($data['attachment']);

                        Notification::make()
                            ->title('Import Berhasil')
                            ->body('Data user berhasil diimpor.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Import Gagal')
                            ->body('Terjadi kesalahan: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
