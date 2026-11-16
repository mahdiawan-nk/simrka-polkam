<?php

namespace App\Filament\Pages\Development;

use App\Services\Development\RkaDataCleanupService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;
use UnitEnum;

/**
 * Halaman Filament khusus Development untuk membersihkan data operasional RKA oleh Super Admin.
 */
class CleanDataRkaPage extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Alat Pengembang';

    protected static ?string $navigationLabel = 'Pembersihan Data RKA';

    protected static ?string $title = 'Pembersihan Data RKA (Pengembangan)';

    protected static ?string $slug = 'development/clean-data-rka';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.development.clean-data-rka-page';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $previewResult = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $executionResult = null;

    public static function canAccess(): bool
    {
        return app(RkaDataCleanupService::class)->isAvailable(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->previewResult = app(RkaDataCleanupService::class)->preview(auth()->user());
    }

    public function previewCleanup(): void
    {
        try {
            $this->previewResult = app(RkaDataCleanupService::class)->preview(auth()->user());

            Notification::make()
                ->title('Preview Cleanup Diperbarui')
                ->body('Total '.$this->previewResult['total_to_delete'].' record masuk cleanup scope.')
                ->info()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Preview Cleanup Ditolak')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewCleanupAction')
                ->label('Pratinjau Pembersihan')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->visible(fn (): bool => static::canAccess())
                ->action(fn () => $this->previewCleanup()),

            Action::make('executeCleanupAction')
                ->label('Bersihkan Data RKA')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => static::canAccess())
                ->modalHeading('Konfirmasi Pembersihan Data Operasional RKA')
                ->modalDescription(function (): string {
                    $preview = app(RkaDataCleanupService::class)->preview(auth()->user());
                    $db = $preview['database'];

                    return sprintf(
                        'PERINGATAN: Operasi ini akan menghapus %d record operasional RKA pada environment [%s] dan database [%s]. Master data (users, roles, departments, accounts, settings, schedules, fund_requests) tetap dipertahankan.',
                        $preview['total_to_delete'],
                        $db['environment'],
                        $db['database']
                    );
                })
                ->form([
                    TextInput::make('confirmation_text')
                        ->label('Ketik tepat "'.RkaDataCleanupService::CONFIRMATION_PHRASE.'" untuk melanjutkan')
                        ->placeholder(RkaDataCleanupService::CONFIRMATION_PHRASE)
                        ->required()
                        ->rule(static function (): Closure {
                            return static function (string $attribute, mixed $value, Closure $fail): void {
                                if ($value !== RkaDataCleanupService::CONFIRMATION_PHRASE) {
                                    $fail('Teks konfirmasi harus persis: '.RkaDataCleanupService::CONFIRMATION_PHRASE);
                                }
                            };
                        }),
                ])
                ->modalSubmitActionLabel('Eksekusi Cleanup')
                ->action(function (array $data): void {
                    try {
                        $result = app(RkaDataCleanupService::class)->execute(
                            (string) ($data['confirmation_text'] ?? ''),
                            auth()->user()
                        );

                        $this->executionResult = $result;
                        $this->previewResult = app(RkaDataCleanupService::class)->preview(auth()->user());

                        if ($result['status'] === 'SUCCESS') {
                            Notification::make()
                                ->title('Cleanup RKA Berhasil')
                                ->body("Berhasil menghapus {$result['total_deleted']} record operasional RKA.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Cleanup RKA '.$result['status'])
                                ->body($result['message'] ?? 'Periksa ringkasan eksekusi.')
                                ->warning()
                                ->send();
                        }
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Cleanup RKA Gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
