<?php

namespace App\Filament\Resources\RkaHeaders\Pages;

use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use App\Models\Department;
use App\Models\RkaApprovedImportBatch;
use App\Models\RkaHeader;
use App\Models\RkaSchedule;
use App\Models\RkaUnlockRequest;
use App\Services\ApprovedRkaImport\ApprovedRkaImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportApprovedRkaPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = RkaHeaderResource::class;

    protected string $view = 'filament.resources.rka-headers.pages.import-approved-rka-page';

    protected static ?string $title = 'Import Approved RKA (Excel)';

    public ?array $data = [];

    public ?string $previewToken = null;

    public ?array $previewResult = null;

    public string $activePreviewTab = 'belanja';

    public ?int $reimportHeaderId = null;

    public ?int $activeUnlockRequestId = null;

    public ?RkaHeader $targetHeader = null;

    public ?RkaUnlockRequest $activeUnlockRequest = null;

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('importApproved', RkaHeader::class);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $reimportId = request()->integer('reimport');
        if ($reimportId > 0) {
            $header = RkaHeader::with(['department', 'schedule'])->findOrFail($reimportId);
            app(ApprovedRkaImportService::class)->authorizeUserForDepartment(
                auth()->user(),
                $header->department_id,
                $header,
            );

            $unlockRequest = RkaUnlockRequest::query()
                ->where('rka_header_id', $header->id)
                ->where('status', RkaUnlockRequest::STATUS_APPROVED)
                ->whereNull('reimport_used_at')
                ->latest('id')
                ->first();

            if (! $unlockRequest || ! $unlockRequest->isReimportActive()) {
                Notification::make()
                    ->title('Hak Import Ulang Tidak Aktif')
                    ->body('RKA ini belum memiliki persetujuan pembukaan kunci yang aktif atau masa berlakunya sudah habis.')
                    ->danger()
                    ->send();

                $this->redirect(RkaHeaderResource::getUrl('index'));

                return;
            }

            $this->reimportHeaderId = $header->id;
            $this->activeUnlockRequestId = $unlockRequest->id;
            $this->targetHeader = $header;
            $this->activeUnlockRequest = $unlockRequest;
        }

        $defaultDepartmentId = $this->targetHeader?->department_id
            ?? $this->getAccessibleDepartments()->keys()->first();
        $defaultScheduleId = $this->targetHeader?->schedule_id
            ?? RkaSchedule::query()->latest('id')->value('id');

        $this->form->fill([
            'schedule_id' => $defaultScheduleId,
            'department_id' => $defaultDepartmentId,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('schedule_id')
                    ->label('Periode / Jadwal RKA')
                    ->options(fn (): array => RkaSchedule::query()
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn (RkaSchedule $s): array => [$s->id => trim("{$s->fiscal_year} - {$s->phase_name}")])
                        ->all())
                    ->required()
                    ->disabled(fn (): bool => $this->reimportHeaderId !== null)
                    ->dehydrated(),

                Select::make('department_id')
                    ->label('Unit / Departemen')
                    ->options(fn (): array => $this->getAccessibleDepartments()->all())
                    ->required()
                    ->searchable()
                    ->disabled(fn (): bool => $this->reimportHeaderId !== null)
                    ->dehydrated(),

                FileUpload::make('attachment')
                    ->label('File Excel Approved RKA (.xlsx)')
                    ->disk('local')
                    ->directory('approved-rka-uploads')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxSize(10240)
                    ->required()
                    ->helperText('Unggah workbook RKA asli Politeknik Kampar (.xlsx) yang berisi IDENTITAS, RINGKASAN, REKAP, DAFTAR PENYESUAIAN, BERITA ACARA, dan worksheet Belanja.'),
            ])
            ->statePath('data');
    }

    public function runPreview(): void
    {
        $state = $this->form->getState();
        $relativePath = $state['attachment'] ?? null;

        if (! $relativePath || ! Storage::disk('local')->exists($relativePath)) {
            Notification::make()
                ->title('File Tidak Ditemukan')
                ->body('Silakan unggah file Excel .xlsx terlebih dahulu.')
                ->danger()
                ->send();

            return;
        }

        $fullPath = Storage::disk('local')->path($relativePath);
        $originalFilename = basename((string) $relativePath);

        try {
            $mode = $this->reimportHeaderId !== null
                ? RkaApprovedImportBatch::TYPE_REIMPORT
                : RkaApprovedImportBatch::TYPE_INITIAL;

            $result = app(ApprovedRkaImportService::class)->preview(
                filePath: $fullPath,
                originalFilename: $originalFilename,
                user: auth()->user(),
                departmentId: (int) ($this->targetHeader?->department_id ?? $state['department_id']),
                scheduleId: (int) ($this->targetHeader?->schedule_id ?? $state['schedule_id']),
                mode: $mode,
                targetHeader: $this->targetHeader,
                unlockRequestId: $this->activeUnlockRequestId,
            );

            $this->previewToken = $result['token'];
            $this->previewResult = $result;
            $this->activePreviewTab = 'belanja';

            if ($result['has_fatal_errors']) {
                Notification::make()
                    ->title('Validasi Menemukan Kesalahan Fatal')
                    ->body('Periksa detail validasi di bawah sebelum dapat melanjutkan proses import.')
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title('Preview Berhasil Dibuat')
                    ->body('Silakan periksa seluruh ringkasan dan detail item sebelum mengonfirmasi import.')
                    ->success()
                    ->send();
            }
        } catch (Throwable $e) {
            $this->previewToken = null;
            $this->previewResult = null;

            Notification::make()
                ->title('Preview Gagal')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function confirmImport(): void
    {
        if (! $this->previewToken || ! $this->previewResult) {
            Notification::make()
                ->title('Preview Diperlukan')
                ->body('Jalankan Preview & Validasi terlebih dahulu sebelum melakukan import.')
                ->warning()
                ->send();

            return;
        }

        try {
            $service = app(ApprovedRkaImportService::class);

            if ($this->reimportHeaderId !== null && $this->targetHeader && $this->activeUnlockRequest) {
                $batch = $service->executeReimport(
                    previewToken: $this->previewToken,
                    header: $this->targetHeader->fresh(),
                    unlockRequest: $this->activeUnlockRequest->fresh(),
                    user: auth()->user(),
                );

                Notification::make()
                    ->title('Import Ulang Approved RKA Berhasil')
                    ->body("Batch #{$batch->id} selesai. RKA tetap berstatus APPROVED dan otomatis dikunci kembali.")
                    ->success()
                    ->send();
            } else {
                $batch = $service->executeImport(
                    previewToken: $this->previewToken,
                    user: auth()->user(),
                );

                Notification::make()
                    ->title('Import Approved RKA Berhasil')
                    ->body("RKA #{$batch->rka_header_id} berhasil dibuat dengan status APPROVED dan dikunci otomatis.")
                    ->success()
                    ->send();
            }

            $this->previewToken = null;
            $this->previewResult = null;

            $this->redirect(RkaHeaderResource::getUrl('index'));
        } catch (Throwable $e) {
            Notification::make()
                ->title('Import Gagal')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetPreview(): void
    {
        $this->previewToken = null;
        $this->previewResult = null;
    }

    public function assignUnitAction(): Action
    {
        return Action::make('assignUnit')
            ->modalHeading(function (array $arguments): string {
                $row = $this->findPreviewAdjustmentRow((string) ($arguments['adj_ref_id'] ?? ''));

                return strtoupper((string) ($row['section'] ?? $row['section_type'] ?? 'A')) === 'A'
                    ? 'Tentukan Unit Tujuan (Section A)'
                    : 'Tentukan Unit Asal / Sumber (Section B)';
            })
            ->modalDescription('Pilih unit kerja dari master unit untuk melengkapi relasi penyesuaian RKA ini.')
            ->modalSubmitActionLabel('Simpan Unit')
            ->fillForm(function (array $arguments): array {
                $row = $this->findPreviewAdjustmentRow((string) ($arguments['adj_ref_id'] ?? ''));
                if ($row === null) {
                    return [];
                }

                $sectionType = strtoupper((string) ($row['section'] ?? $row['section_type'] ?? 'A'));
                $currentDeptId = $sectionType === 'A'
                    ? ($row['destination_department_id'] ?? null)
                    : ($row['target_department_id'] ?? null);

                return [
                    'department_id' => $currentDeptId,
                ];
            })
            ->form(function (array $arguments): array {
                $row = $this->findPreviewAdjustmentRow((string) ($arguments['adj_ref_id'] ?? ''));
                $sectionType = strtoupper((string) ($row['section'] ?? $row['section_type'] ?? 'A'));
                $actionType = strtoupper((string) ($row['action_type'] ?? '-'));
                $description = (string) ($row['uraian'] ?? $row['description'] ?? '-');
                $amount = (float) ($row['jumlah_disetujui'] ?? $row['jumlah_usulan'] ?? 0);
                if ($amount <= 0) {
                    $amount = (float) ($row['jumlah_usulan'] ?? 0);
                }
                $formattedAmount = 'Rp '.number_format($amount, 0, ',', '.');

                return [
                    Placeholder::make('adjustment_context')
                        ->label('Informasi Item Penyesuaian')
                        ->content("Section {$sectionType} ({$actionType}) — {$description} | Nilai: {$formattedAmount}"),
                    Select::make('department_id')
                        ->label($sectionType === 'A' ? 'Unit Tujuan' : 'Unit Asal / Sumber')
                        ->options($this->getMasterUnitOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->placeholder('Cari nama atau kode unit...'),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                if (! $this->previewToken) {
                    Notification::make()
                        ->title('Sesi Preview Tidak Aktif')
                        ->body('Silakan jalankan preview file Excel terlebih dahulu.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $updatedPreview = app(ApprovedRkaImportService::class)->assignUnitOnPreview(
                        previewToken: $this->previewToken,
                        adjRefId: (string) ($arguments['adj_ref_id'] ?? ''),
                        departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
                        user: auth()->user(),
                    );

                    $this->previewResult = $updatedPreview;
                    $this->activePreviewTab = 'adjustments';

                    Notification::make()
                        ->title('Unit Penyesuaian Disimpan')
                        ->body('Relasi unit pada baris penyesuaian berhasil diperbarui.')
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Gagal Menentukan Unit')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findPreviewAdjustmentRow(string $adjRefId): ?array
    {
        $rows = $this->previewResult['adjustment_rows'] ?? [];
        foreach ($rows as $row) {
            if ((string) ($row['adj_ref_id'] ?? '') === $adjRefId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function getMasterUnitOptions(): array
    {
        $ownerDepartmentId = (int) ($this->previewResult['department_id'] ?? 0);

        return Department::query()
            ->when($ownerDepartmentId > 0, fn ($query) => $query->where('id', '!=', $ownerDepartmentId))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Department $dept): array => [
                $dept->id => "{$dept->name} ({$dept->code})",
            ])
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    protected function getAccessibleDepartments(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        if ($user->hasRole('super_admin')) {
            return Department::query()->orderBy('name')->pluck('name', 'id');
        }

        return $user->departments()->orderBy('name')->pluck('departments.name', 'departments.id');
    }

    /**
     * @return Collection<int, RkaApprovedImportBatch>
     */
    public function getRecentBatches(): Collection
    {
        $user = auth()->user();
        $query = RkaApprovedImportBatch::query()
            ->with(['department', 'schedule', 'importer'])
            ->latest('id')
            ->limit(10);

        if ($this->reimportHeaderId !== null) {
            $query->where('rka_header_id', $this->reimportHeaderId);
        } elseif ($user && ! $user->hasRole('super_admin')) {
            $deptIds = $user->departments()->pluck('departments.id');
            $query->whereIn('department_id', $deptIds);
        }

        return $query->get();
    }
}
