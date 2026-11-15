<?php

namespace App\Filament\Actions\Rka;

use App\Models\RkaItem;
use App\Services\RkaReviewService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Textarea;

// Sesuaikan namespace kedua form ini dengan project kamu.
use App\Filament\Resources\RkaHeaders\Schemas\ReviewItemForm;
use App\Filament\Resources\RkaHeaders\Schemas\BulkMoveForm;
use App\Filament\Resources\RkaHeaders\Schemas\AddAdjustmentForm;

class RkaReviewActions
{
    /**
     * Review / Pemangkasan & Adjustment Item Belanja.
     */
    public static function reviewItem(object $page): Action
    {
        return Action::make('reviewItem')
            ->label('Review')
            ->icon('heroicon-o-check-badge')
            ->color('warning')
            ->iconButton()
            ->modalHeading(
                'Review / Pemangkasan & Adjustment Item Belanja'
            )
            ->modalWidth('2xl')
            ->fillForm(function (array $arguments): array {
                $item = RkaItem::find(
                    $arguments['item_id'] ?? null
                );

                if (! $item) {
                    return [];
                }

                return [
                    'item_id' => $item->id,
                    'unit_price' => $item->unit_price,
                    'proposed_volume' => $item->proposed_volume,
                    'proposed_total' => $item->proposed_total,
                    'action_type' => $item->status
                        ?? ($item->is_trimmed ? 'MOVED' : 'APPROVED'),
                    'approved_volume' => $item->approved_volume
                        ?? $item->proposed_volume,
                    'unit_symbol' => $item->unit_symbol
                        ?? $item->unit
                        ?? 'Satuan',
                    'approved_price' => $item->approved_price
                        ?? $item->unit_price,
                    'approved_total' => $item->approved_total
                        ?? $item->proposed_total,
                    'review_notes' => $item->review_notes,
                ];
            })
            ->form(
                fn(Schema $schema): Schema =>
                ReviewItemForm::configure($schema)
            )
            ->action(function (
                array $data,
                array $arguments,
                RkaReviewService $reviewService,
            ) use ($page): void {
                $reviewService->reviewItem(
                    $page->record,
                    $data,
                    $arguments,
                );

                $page->record->refresh();

                Notification::make()
                    ->title('Hasil Review & Adjustment Item Disimpan')
                    ->success()
                    ->send();
            });
    }

    /**
     * Impor adjustment ke Item Belanja RKA.
     */
    public static function importAdjustmentToItem(
        object $page,
    ): Action {
        return Action::make('importAdjustmentToItem')
            ->label('Masukkan ke RKA')
            ->icon('heroicon-o-arrow-down-on-square')
            ->color('success')
            ->iconButton()
            ->tooltip(
                'Masukkan data adjustment ini ke Item Belanja Unit'
            )
            ->requiresConfirmation()
            ->modalHeading(
                'Masukkan Adjustment ke Item Belanja'
            )
            ->modalDescription(
                'Apakah Anda yakin ingin memasukkan data adjustment ini ke dalam daftar Item Belanja (RKA) unit ini?'
            )
            ->action(function (
                array $arguments,
                RkaReviewService $reviewService,
            ) use ($page): void {
                $adjustmentId = $arguments['adjustment_id'] ?? null;

                $success = $reviewService->importAdjustmentToItem(
                    $page->record,
                    (int) $adjustmentId,
                );

                if (! $success) {
                    Notification::make()
                        ->title(
                            'Data adjustment tidak dapat diproses atau sudah diimpor'
                        )
                        ->warning()
                        ->send();

                    return;
                }

                $page->record->refresh();

                Notification::make()
                    ->title(
                        'Berhasil Dimasukkan ke Item Belanja Unit'
                    )
                    ->success()
                    ->send();
            });
    }

    /**
     * Auto approve item yang dipilih.
     */
    public static function bulkApprove(object $page): Action
    {
        return Action::make('bulkApprove')
            ->label(
                'Approve Terpilih (' .
                    count($page->selectedItems) .
                    ')'
            )
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Konfirmasi Auto Approve Massal')
            ->modalDescription(
                'Apakah Anda yakin ingin menyetujui (Auto Approve) semua item yang dipilih? Nilai disetujui akan disesuaikan dengan nilai usulan.'
            )
            ->action(function (
                RkaReviewService $reviewService,
            ) use ($page): void {
                if (empty($page->selectedItems)) {
                    Notification::make()
                        ->title('Tidak ada item yang dipilih')
                        ->warning()
                        ->send();

                    return;
                }

                $reviewService->bulkApproveItems(
                    $page->record,
                    $page->selectedItems,
                );

                $page->selectedItems = [];

                $page->record->refresh();

                Notification::make()
                    ->title(
                        'Item berhasil disetujui secara massal'
                    )
                    ->success()
                    ->send();
            });
    }

    /**
     * Pindahkan item terpilih ke unit tujuan.
     */
    public static function bulkMove(object $page): Action
    {
        return Action::make('bulkMove')
            ->label(
                'Pindahkan Terpilih (' .
                    count($page->selectedItems) .
                    ')'
            )
            ->icon('heroicon-o-arrow-right-circle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(
                'Konfirmasi Pemindahan Item Massal (Pos Terpusat)'
            )
            ->modalDescription(
                'Semua item terpilih akan dipindahkan statusnya menjadi MOVED ke Unit Pengelola/Tujuan yang ditentukan.'
            )
            ->form(
                fn(Schema $schema): Schema =>
                BulkMoveForm::configure($schema)
            )
            ->action(function (
                array $data,
                RkaReviewService $reviewService,
            ) use ($page): void {
                if (empty($page->selectedItems)) {
                    Notification::make()
                        ->title('Tidak ada item yang dipilih')
                        ->warning()
                        ->send();

                    return;
                }

                $reviewService->bulkMoveItems(
                    $page->record,
                    $page->selectedItems,
                    (int) $data['destination_unit_id'],
                    $data['review_notes']
                        ?? 'Dipindahkan secara massal',
                );

                $page->selectedItems = [];

                $page->record->refresh();

                Notification::make()
                    ->title(
                        'Item berhasil dipindahkan (MOVED) secara massal'
                    )
                    ->success()
                    ->send();
            });
    }

    /**
     * Tambah penyesuaian usulan Section A & B.
     */
    public static function addAdjustment(object $page): Action
    {
        return Action::make('addAdjustment')
            ->label('Tambah Penyesuaian')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->modalHeading('Tambah Daftar Penyesuaian RKA')
            ->modalWidth('3xl')
            ->form(
                fn(Schema $schema): Schema =>
                AddAdjustmentForm::configure($schema)
            )
            ->action(function (
                array $data,
                RkaReviewService $reviewService,
            ) use ($page): void {
                $reviewService->addAdjustment(
                    $page->record,
                    $page->activeIndicatorTab,
                    $data,
                );

                $page->record->refresh();

                Notification::make()
                    ->title('Penyesuaian Berhasil Ditambahkan')
                    ->success()
                    ->send();
            });
    }

    /**
     * Hapus penyesuaian.
     */
    public static function deleteAdjustment(
        object $page,
    ): Action {
        return Action::make('deleteAdjustment')
            ->label('Hapus')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->iconButton()
            ->requiresConfirmation()
            ->action(function (
                array $arguments,
                RkaReviewService $reviewService,
            ) use ($page): void {
                $reviewService->deleteAdjustment(
                    (int) ($arguments['adjustment_id'] ?? 0)
                );

                $page->record->refresh();

                Notification::make()
                    ->title('Penyesuaian Dihapus')
                    ->success()
                    ->send();
            });
    }
}
