<?php

namespace App\Filament\Actions\Rka;

use App\Models\RkaHeader;
use App\Services\RkaApprovalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class RkaDirekturActions
{
    /**
     * Mengembalikan seluruh action workflow RKA.
     */
    public static function make(
        RkaHeader $record,
        string $redirectUrl,
    ): array {
        return [
            self::forwardToDirektur($record, $redirectUrl),
            self::approveDirektur($record, $redirectUrl),
            self::requireRevisionDirektur($record, $redirectUrl),
        ];
    }

    /**
     * Wadir Keuangan meneruskan RKA ke Direktur.
     */
    public static function forwardToDirektur(
        RkaHeader $record,
        string $redirectUrl,
    ): Action {
        return Action::make('forwardToDirektur')
            ->label('Teruskan ke Direktur')
            ->icon('heroicon-m-paper-airplane')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Teruskan RKA ke Direktur')
            ->modalDescription(
                'Apakah Anda yakin RKA unit ini sudah sesuai dan siap diteruskan ke Direktur untuk pengesahan?'
            )
            ->modalSubmitActionLabel('Ya, Teruskan')
            ->visible(function () use ($record): bool {
                $user = auth()->user();

                $status = strtoupper(
                    $record->rka_status ?? $record->status ?? ''
                );

                return $user !== null
                    && $user->hasRole('wadir_keuangan')
                    && in_array($status, [
                        RkaHeader::STATUS_IN_REVIEW,
                        RkaHeader::STATUS_REVISION_REQUIRED,
                    ], true);
            })
            ->form([
                Textarea::make('comments')
                    ->label('Catatan Rekomendasi (Opsional)')
                    ->placeholder(
                        'Masukkan catatan rekomendasi untuk Direktur...'
                    )
                    ->rows(3),
            ])
            ->action(function (
                array $data,
                RkaApprovalService $service,
            ) use ($record, $redirectUrl): void {
                $service->forwardToDirektur(
                    $record,
                    auth()->user(),
                    $data['comments'] ?? null,
                );

                Notification::make()
                    ->title('RKA Diteruskan ke Direktur')
                    ->body(
                        'Status RKA berhasil diubah menjadi IN_APPROVED.'
                    )
                    ->success()
                    ->send();

                redirect()->to($redirectUrl);
            });
    }

    /**
     * Direktur melakukan pengesahan akhir RKA.
     */
    public static function approveDirektur(
        RkaHeader $record,
        string $redirectUrl,
    ): Action {
        return Action::make('approveDirektur')
            ->label('Sahkan / Setujui RKA')
            ->icon('heroicon-m-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Pengesahan Akhir RKA')
            ->modalDescription(
                'Apakah Anda yakin ingin menyetujui dan mengesahkan RKA unit ini secara resmi?'
            )
            ->modalSubmitActionLabel('Ya, Sahkan')
            ->visible(function () use ($record): bool {
                $user = auth()->user();

                $status = strtoupper(
                    $record->rka_status ?? $record->status ?? ''
                );

                return $user !== null
                    && $user->hasRole('direktur')
                    && $status === RkaHeader::STATUS_IN_APPROVED;
            })
            ->form([
                Textarea::make('comments')
                    ->label('Catatan Pengesahan (Opsional)')
                    ->placeholder(
                        'Masukkan catatan pengesahan jika ada...'
                    )
                    ->rows(3),
            ])
            ->action(function (
                array $data,
                RkaApprovalService $service,
            ) use ($record, $redirectUrl): void {
                $service->approveByDirektur(
                    $record,
                    auth()->user(),
                    $data['comments'] ?? null,
                );

                Notification::make()
                    ->title('RKA Berhasil Disahkan')
                    ->body(
                        'Status RKA telah diperbarui menjadi APPROVED.'
                    )
                    ->success()
                    ->send();

                redirect()->to($redirectUrl);
            });
    }

    /**
     * Direktur meminta revisi kepada Wadir Keuangan.
     */
    public static function requireRevisionDirektur(
        RkaHeader $record,
        string $redirectUrl,
    ): Action {
        return Action::make('requireRevisionDirektur')
            ->label('Minta Revisi')
            ->icon('heroicon-m-arrow-path')
            ->color('warning')
            ->visible(function () use ($record): bool {
                $user = auth()->user();

                $status = strtoupper(
                    $record->rka_status ?? $record->status ?? ''
                );

                return $user !== null
                    && $user->hasRole('direktur')
                    && $status === RkaHeader::STATUS_IN_APPROVED;
            })
            ->form([
                Textarea::make('comments')
                    ->label('Catatan Revisi untuk Wadir Keuangan')
                    ->placeholder(
                        'Masukkan poin-poin revisi yang wajib diperbaiki...'
                    )
                    ->rows(4)
                    ->required(),
            ])
            ->modalHeading('Minta Revisi RKA')
            ->modalDescription(
                'RKA akan dikembalikan ke Wadir Keuangan untuk proses penyesuaian ulang.'
            )
            ->modalSubmitActionLabel('Kirim Catatan Revisi')
            ->action(function (
                array $data,
                RkaApprovalService $service,
            ) use ($record, $redirectUrl): void {
                $service->requireRevisionByDirektur(
                    $record,
                    auth()->user(),
                    $data['comments'],
                );

                Notification::make()
                    ->title('Catatan Revisi Terkirim')
                    ->body(
                        'Status RKA dikembalikan menjadi REVISION_REQUIRED untuk ditindaklanjuti Wadir Keuangan.'
                    )
                    ->warning()
                    ->send();

                redirect()->to($redirectUrl);
            });
    }
}
