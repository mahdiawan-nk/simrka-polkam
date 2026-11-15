<?php

namespace App\Filament\Actions\Rka;

use App\Filament\Resources\RkaHeaders\Schemas\AddItemForm;
use App\Filament\Resources\RkaHeaders\Schemas\AddProgramForm;
use App\Filament\Resources\RkaHeaders\Schemas\EditItemForm;
use App\Filament\Resources\RkaHeaders\Schemas\ImportExcelForm;
use App\Imports\RkaItemsImport;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Services\RkaApprovalService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class RkaActions
{
    /**
     * Mengajukan RKA dan mengunci data.
     */
    public static function submitRka(object $page): Action
    {
        return Action::make('submitRka')
            ->label('Submit RKA')
            ->icon('heroicon-m-paper-airplane')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Submit Rencana Kerja & Anggaran')
            ->modalDescription(
                'Apakah Anda yakin ingin mengajukan RKA ini? Setelah diajukan, data akan dikunci dan tidak dapat diubah kembali.'
            )
            ->modalSubmitActionLabel('Ya, Submit')
            ->hidden(
                fn (): bool => $page->record->is_locked ||
                    strtolower((string) $page->record->status) === 'submitted'
            )
            ->action(function (RkaApprovalService $service) use ($page): void {
                $service->submitByPemohon(
                    $page->record,
                    auth()->user()
                );

                $page->record->refresh();

                Notification::make()
                    ->title('RKA Berhasil Diajukan')
                    ->body(
                        'Status RKA telah diperbarui menjadi Submitted dan data telah dikunci.'
                    )
                    ->success()
                    ->send();
            });
    }

    /**
     * Menambahkan program dan indikator kinerja.
     */
    public static function addProgram(object $page): Action
    {
        return Action::make('addProgram')
            ->label('Tambah Program & Indikator')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->modalHeading('Tambah Program & Indikator Kinerja RKA')
            ->slideOver()
            ->modalWidth('4xl')
            ->form(
                fn (Schema $schema): Schema => AddProgramForm::configure($schema)
            )
            ->action(function (array $data) use ($page): void {
                $indicator = RkaIndicator::create([
                    'rka_header_id' => $page->record->id,
                    'account_id' => $data['account_id'],
                    'program_name' => $data['program_name'],
                    'activity_name' => $data['activity_name'],
                    'location' => $data['location'],
                    'indicator_matrix' => $data['indicator_matrix'] ?? [],
                ]);

                $page->activeTabGroup = (string) $indicator->id;
                $page->record->refresh();

                Notification::make()
                    ->title('Program & Indikator Berhasil Ditambahkan')
                    ->success()
                    ->send();
            });
    }

    /**
     * Menambahkan item belanja pada indikator aktif.
     */
    public static function addItem(object $page): Action
    {
        return Action::make('addItem')
            ->label('Tambah Item Belanja')
            ->icon('heroicon-o-plus')
            ->color('success')
            ->modalHeading('Tambah Item Belanja RKA')
            ->modalWidth('2xl')
            ->form(
                fn (Schema $schema): Schema => AddItemForm::configure(
                    $schema,
                    $page->activeTabGroup,
                )
            )
            ->action(function (array $data) use ($page): void {
                $indicator = RkaIndicator::query()
                    ->where('rka_header_id', $page->record->id)
                    ->findOrFail($page->activeTabGroup);

                RkaItem::create([
                    'rka_header_id' => $page->record->id,
                    'rka_indicator_id' => $indicator->id,
                    'account_id' => $data['account_id'],
                    'form_type' => $data['form_type'],
                    'description' => $data['description'],
                    'proposed_volume' => $data['proposed_volume'],
                    'unit_symbol' => $data['unit_symbol'],
                    'unit_price' => $data['unit_price'],
                    'status' => RkaItem::STATUS_PENDING,
                ]);

                $indicator->recalculateMasukanTargetValue();
                $page->record->calculatePaguRka();
                $page->record->refresh();

                Notification::make()
                    ->title('Item Belanja Berhasil Ditambahkan')
                    ->success()
                    ->send();
            });
    }

    /**
     * Mengubah item belanja.
     */
    public static function editItem(object $page): Action
    {
        return Action::make('editItem')
            ->label('Edit')
            ->iconButton()
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->modalHeading('Edit Item Belanja RKA')
            ->modalWidth('2xl')
            ->fillForm(
                fn (array $arguments): array => RkaItem::query()
                    ->where('rka_header_id', $page->record->id)
                    ->find($arguments['item_id'] ?? null)
                    ?->toArray() ?? []
            )
            ->form(
                fn (Schema $schema): Schema => EditItemForm::configure($schema)
            )
            ->action(function (array $data, array $arguments) use ($page): void {
                $item = RkaItem::query()
                    ->where('rka_header_id', $page->record->id)
                    ->findOrFail($arguments['item_id']);

                $item->update([
                    'account_id' => $data['account_id'],
                    'description' => $data['description'],
                    'proposed_volume' => $data['proposed_volume'],
                    'unit_symbol' => $data['unit_symbol'],
                    'unit_price' => $data['unit_price'],
                ]);

                $item->indicator?->recalculateMasukanTargetValue();

                $page->record->calculatePaguRka();
                $page->record->refresh();

                Notification::make()
                    ->title('Item Belanja Disimpan')
                    ->success()
                    ->send();
            });
    }

    /**
     * Mengimpor item belanja dari Excel.
     */
    public static function importExcel(object $page): Action
    {
        return Action::make('importExcel')
            ->label('Import Item dari Excel')
            ->icon('heroicon-o-document-arrow-up')
            ->color('success')
            ->form(
                fn (Schema $schema): Schema => ImportExcelForm::configure($schema)
            )
            ->action(function (array $data) use ($page): void {
                $indicatorId = $data['import_scope'] === 'active_tab'
                    ? (int) $page->activeTabGroup
                    : null;

                Excel::import(
                    new RkaItemsImport(
                        $page->record->id,
                        $indicatorId,
                    ),
                    $data['attachment']->getRealPath(),
                );

                $page->record->calculatePaguRka();
                $page->record->refresh();

                Notification::make()
                    ->title('Data Item Belanja Berhasil Diimport')
                    ->body(
                        $data['import_scope'] === 'active_tab'
                            ? 'Data diimport khusus untuk indikator aktif saat ini.'
                            : 'Data diimport untuk seluruh indikator pada RKA ini.'
                    )
                    ->success()
                    ->send();
            });
    }

    /**
     * Menghapus satu item belanja.
     */
    public static function deleteItem(object $page): Action
    {
        return Action::make('deleteItem')
            ->label('Hapus')
            ->hiddenLabel()
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->iconButton()
            ->requiresConfirmation()
            ->modalHeading('Hapus Item Belanja')
            ->modalDescription(
                'Apakah Anda yakin ingin menghapus item belanja ini? Tindakan ini tidak dapat dibatalkan.'
            )
            ->modalSubmitActionLabel('Ya, Hapus')
            ->modalCancelActionLabel('Batal')
            ->action(function (array $arguments) use ($page): void {
                $item = RkaItem::query()
                    ->where('rka_header_id', $page->record->id)
                    ->findOrFail($arguments['item_id']);

                $indicatorId = $item->rka_indicator_id;

                $item->delete();

                if ($indicator = RkaIndicator::find($indicatorId)) {
                    $indicator->recalculateMasukanTargetValue();
                }

                $page->record->calculatePaguRka();
                $page->record->refresh();

                Notification::make()
                    ->title('Item Belanja Dihapus')
                    ->success()
                    ->send();
            });
    }

    /**
     * Menghapus seluruh item belanja pada RKA.
     */
    public static function clearAllItems(object $page): Action
    {
        return Action::make('clearAllItems')
            ->label('Hapus Semua Item')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus Semua Item Belanja')
            ->modalDescription(
                'Apakah Anda yakin ingin menghapus SEMUA item belanja pada RKA ini? Tindakan ini tidak dapat dibatalkan.'
            )
            ->modalSubmitActionLabel('Ya, Hapus Semua')
            ->modalCancelActionLabel('Batal')
            ->action(function () use ($page): void {
                DB::transaction(function () use ($page): void {
                    $record = RkaHeader::query()
                        ->lockForUpdate()
                        ->findOrFail($page->record->id);

                    $indicatorIds = RkaItem::query()
                        ->where('rka_header_id', $record->id)
                        ->whereNotNull('rka_indicator_id')
                        ->distinct()
                        ->pluck('rka_indicator_id');

                    RkaItem::query()
                        ->where('rka_header_id', $record->id)
                        ->delete();

                    foreach ($indicatorIds as $indicatorId) {
                        RkaIndicator::find($indicatorId)
                            ?->recalculateMasukanTargetValue();
                    }

                    $record->calculatePaguRka();
                });

                $page->record->refresh();

                Notification::make()
                    ->title('Semua Item Belanja Berhasil Dihapus')
                    ->success()
                    ->send();
            });
    }

    /**
     * Menghitung ulang pagu RKA.
     */
    public static function calculatePagu(object $page): Action
    {
        return Action::make('calculatePagu')
            ->label('Hitung Pagu')
            ->icon('heroicon-o-calculator')
            ->color('primary')
            ->action(function () use ($page): void {
                $page->record->calculatePaguRka();
                $page->record->refresh();

                Notification::make()
                    ->title('Pagu RKA Dihitung Ulang')
                    ->success()
                    ->send();
            });
    }
}
