<?php

namespace App\Filament\Resources\RkaHeaders\Pages;

use App\Filament\Actions\Rka\RkaDirekturActions;
use App\Filament\Actions\Rka\RkaReviewActions;
use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use App\Models\Department;
use App\Models\RkaHeader;
use App\Models\RkaLog;
use App\Services\RkaReviewService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

/**
 * Halaman Review RKA.
 *
 * Halaman ini digunakan untuk menampilkan, meninjau, menyesuaikan,
 * dan memproses item RKA berdasarkan department dan indikator.
 *
 * Tanggung jawab utama:
 * - Mengelola record RKA yang sedang direview.
 * - Menyediakan navigasi tab dan filter department.
 * - Menampilkan ringkasan serta hierarki anggaran.
 * - Mengelola pemilihan item untuk proses massal.
 * - Menyediakan action review dan adjustment.
 * - Menyediakan action persetujuan Direktur dan Wadir Keuangan.
 *
 * Implementasi action dipisahkan ke class RkaReviewActions dan
 * RkaDirekturActions agar konfigurasi action dapat digunakan kembali.
 *
 * @property RkaHeader $record Record RKA yang sedang direview.
 */
class ReviewRkaPage extends Page
{
    use InteractsWithRecord;
    use WithPagination;

    /**
     * Resource Filament yang menaungi halaman Review RKA.
     */
    protected static string $resource = RkaHeaderResource::class;

    /**
     * View Blade untuk menampilkan antarmuka Review RKA.
     */
    protected string $view =
        'filament.resources.rka-headers.pages.review-rka-page';

    /**
     * Tab utama yang sedang aktif.
     *
     * Pilihan:
     * - ringkasan
     * - sheet_rka
     * - penyesuaian
     * - rekap
     */
    public string $activeTab = 'ringkasan';

    /**
     * ID indikator RKA yang sedang aktif.
     *
     * Digunakan untuk memfilter item dan penyesuaian
     * berdasarkan indikator yang dipilih.
     */
    public ?string $activeIndicatorTab = null;

    /**
     * Filter jenis aktivitas pada tab Riwayat Aktivitas.
     */
    public ?string $logActionFilter = null;

    /**
     * Filter pelaku aktivitas pada tab Riwayat Aktivitas.
     */
    public ?string $logUserFilter = null;

    /**
     * Daftar ID item yang dipilih untuk proses massal.
     *
     * Digunakan oleh bulk approve dan bulk move.
     */
    public array $selectedItems = [];

    /**
     * Mengatur apakah seluruh item ditampilkan.
     *
     * Nilai false dapat digunakan untuk membatasi tampilan
     * sesuai aturan visibilitas item pada role pengguna.
     */
    public bool $showAllItems = false;

    /**
     * ID department yang sedang dipilih pada filter.
     */
    public ?int $selectedDepartmentId = null;

    /**
     * Inisialisasi halaman Review RKA.
     *
     * Mengambil record berdasarkan parameter route, menetapkan
     * department awal, memuat data RKA, dan menentukan tampilan
     * awal berdasarkan role pengguna.
     */
    public function mount(int|string $record): void
    {
        $user = auth()->user();

        $this->record = $this->resolveRecord($record);

        abort_unless($user && $user->can('view', $this->record), 403);

        // Gunakan department milik RKA sebagai filter awal.
        $this->selectedDepartmentId = $this->record->department_id;

        // Muat record dan data terkait melalui service.
        $this->loadRkaRecord();

        // Pemohon secara default hanya melihat item final.
        // Role lainnya secara default melihat seluruh item.
        $this->showAllItems = ! $user?->hasRole('pemohon');
    }

    /**
     * Mengubah mode tampilan seluruh item.
     *
     * @param  bool  $showAll  true untuk menampilkan seluruh item.
     */
    public function toggleShowAllItems(bool $showAll): void
    {
        $this->showAllItems = $showAll;
    }

    /**
     * Memuat record RKA beserta data yang diperlukan untuk review.
     *
     * Service bertanggung jawab menyiapkan record berdasarkan
     * department yang dipilih.
     *
     * Metode ini juga menentukan indikator aktif pertama dan
     * mengubah status submitted menjadi IN_REVIEW jika diperlukan.
     */
    public function loadRkaRecord(): void
    {
        $service = app(RkaReviewService::class);

        $this->record = $service->loadRkaRecord(
            $this->record,
            $this->selectedDepartmentId,
        );

        // Pilih indikator pertama sebagai indikator aktif.
        $firstIndicator = $this->record->indicators->first();

        $this->activeIndicatorTab = $firstIndicator
            ? (string) $firstIndicator->id
            : null;

        // Transisi awal status ketika RKA mulai direview oleh reviewer.
        if (
            strtolower((string) $this->record->status) === 'submitted'
            && auth()->user()?->hasAnyRole(['keuangan', 'wadir_keuangan', 'direktur'])
        ) {
            $this->record->update([
                'status' => 'IN_REVIEW',
            ]);
        }
    }

    /**
     * Hook Livewire ketika filter department berubah.
     *
     * Jika department memiliki RKA pada jadwal yang sama,
     * pengguna diarahkan ke halaman review record tersebut.
     *
     * Jika tidak tersedia, tampilkan notifikasi dan kembalikan
     * pilihan ke department pada record saat ini.
     */
    public function updatedSelectedDepartmentId(?int $value): void
    {
        if (! $value) {
            return;
        }

        $targetRkaHeader = RkaHeader::query()
            ->where('schedule_id', $this->record->schedule_id)
            ->where('department_id', $value)
            ->first();

        if ($targetRkaHeader) {
            $this->redirect(
                static::getResource()::getUrl('review', [
                    'record' => $targetRkaHeader->id,
                ]),
            );

            return;
        }

        Notification::make()
            ->warning()
            ->title('RKA Belum Dibuat')
            ->body(
                'Department terpilih belum memiliki dokumen RKA pada jadwal ini.'
            )
            ->send();

        // Pulihkan pilihan ke department record saat ini.
        $this->selectedDepartmentId = $this->record->department_id;
    }

    /**
     * Mengambil daftar department untuk filter RKA.
     *
     * Data mencakup informasi department dan status RKA
     * pada jadwal yang sama dengan record aktif.
     *
     * Department bertipe direktorat dan bidang dikecualikan.
     */
    #[Computed]
    public function departments()
    {
        return Department::query()
            ->select([
                'departments.id',
                'departments.name',
                'departments.type',
                'rka_headers.status as rka_status',
                'rka_headers.id as rka_id',
            ])
            ->leftJoin('rka_headers', function ($join) {
                $join->on(
                    'departments.id',
                    '=',
                    'rka_headers.department_id',
                )->where(
                    'rka_headers.schedule_id',
                    '=',
                    $this->record->schedule_id,
                );
            })
            ->whereNotIn('departments.type', [
                'direktorat',
                'bidang',
            ])
            ->get();
    }

    /**
     * Menentukan judul halaman berdasarkan nama department.
     */
    public function getTitle(): string|Htmlable
    {
        return 'Review RKA - '
            .($this->record->department->name ?? 'Unit Work');
    }

    /**
     * Mengubah tab utama yang aktif.
     *
     * @param  string  $tab  Nama tab yang dipilih.
     */
    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * Mengubah indikator RKA yang aktif.
     *
     * @param  string  $indicatorId  ID indikator yang dipilih.
     */
    public function setActiveIndicatorTab(string $indicatorId): void
    {
        $this->activeIndicatorTab = $indicatorId;
    }

    /*
    |--------------------------------------------------------------------------
    | COMPUTED PROPERTIES
    |--------------------------------------------------------------------------
    |
    | Properti berikut dihitung melalui service dan dapat diakses
    | sebagai computed property Livewire.
    */

    /**
     * Menghitung ringkasan data RKA.
     *
     * @return array Data ringkasan anggaran.
     */
    #[Computed]
    public function summaryData(): array
    {
        return app(RkaReviewService::class)
            ->calculateSummaryData($this->record);
    }

    /**
     * Menghitung hierarki akun anggaran.
     *
     * @return array Hierarki akun berdasarkan record RKA.
     */
    #[Computed]
    public function summaryAccountHierarchy(): array
    {
        return app(RkaReviewService::class)
            ->calculateAccountHierarchy($this->record);
    }

    /**
     * Menghitung data kontrol pagu berdasarkan hierarki akun.
     *
     * @return array Data kontrol pagu.
     */
    #[Computed]
    public function kontrolPaguData(): array
    {
        return app(RkaReviewService::class)->calculateKontrolPagu(
            $this->record,
            $this->summaryAccountHierarchy,
        );
    }

    /**
     * Menghitung rekapitulasi anggaran berdasarkan kelompok.
     *
     * @return array Data rekapitulasi kelompok.
     */
    #[Computed]
    public function rekapByKelompok(): array
    {
        return app(RkaReviewService::class)
            ->calculateRekapByKelompok($this->record);
    }

    /**
     * Menghitung hierarki anggaran untuk indikator aktif.
     *
     * Perhitungan dipengaruhi oleh indikator yang dipilih
     * dan mode tampilan seluruh item.
     *
     * @return array Hierarki anggaran.
     */
    #[Computed]
    public function budgetHierarchy(): array
    {
        return app(RkaReviewService::class)->calculateBudgetHierarchy(
            $this->activeIndicatorTab,
            $this->showAllItems,
        );
    }

    public function updatedLogActionFilter(): void
    {
        $this->resetPage('activityPage');
    }

    public function updatedLogUserFilter(): void
    {
        $this->resetPage('activityPage');
    }

    /**
     * Mengambil riwayat aktivitas RKA Header terkait (paginated, terbaru lebih dulu).
     */
    #[Computed]
    public function activityLogs(): LengthAwarePaginator
    {
        return RkaLog::query()
            ->where('rka_header_id', $this->record->id)
            ->when(
                filled($this->logActionFilter),
                fn ($query) => $query->where('action', $this->logActionFilter),
            )
            ->when(
                filled($this->logUserFilter),
                fn ($query) => $query->where('user_id', (int) $this->logUserFilter),
            )
            ->with('user')
            ->latest('created_at')
            ->latest('id')
            ->paginate(10, ['*'], 'activityPage');
    }

    /**
     * Opsi filter jenis aksi dari log yang tercatat pada RKA ini.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function activityActionOptions(): array
    {
        return RkaLog::query()
            ->where('rka_header_id', $this->record->id)
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->get()
            ->mapWithKeys(fn (RkaLog $log): array => [$log->action => $log->action_label])
            ->all();
    }

    /**
     * Opsi filter pengguna dari log yang tercatat pada RKA ini.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function activityUserOptions(): array
    {
        return RkaLog::query()
            ->where('rka_header_id', $this->record->id)
            ->with('user:id,name')
            ->select('user_id')
            ->distinct()
            ->get()
            ->filter(fn (RkaLog $log): bool => $log->user !== null)
            ->mapWithKeys(fn (RkaLog $log): array => [$log->user_id => $log->user->name])
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | FILAMENT ACTIONS: PERSETUJUAN RKA
    |--------------------------------------------------------------------------
    |
    | Konfigurasi action dipisahkan ke RkaDirekturActions.
    | Method di halaman ini hanya meneruskan record dan URL tujuan.
    */

    /**
     * Action Wadir Keuangan untuk meneruskan RKA ke Direktur.
     */
    public function forwardToDirekturAction(): Action
    {
        return RkaDirekturActions::forwardToDirektur(
            record: $this->record,
            redirectUrl: static::getResource()::getUrl('index'),
        );
    }

    /**
     * Action Direktur untuk melakukan pengesahan akhir RKA.
     */
    public function approveDirekturAction(): Action
    {
        return RkaDirekturActions::approveDirektur(
            record: $this->record,
            redirectUrl: static::getResource()::getUrl('index'),
        );
    }

    /**
     * Action Direktur untuk meminta revisi kepada Wadir Keuangan.
     */
    public function requireRevisionDirekturAction(): Action
    {
        return RkaDirekturActions::requireRevisionDirektur(
            record: $this->record,
            redirectUrl: static::getResource()::getUrl('index'),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FILAMENT ACTIONS: REVIEW & ADJUSTMENT
    |--------------------------------------------------------------------------
    |
    | Seluruh konfigurasi action berikut dikelola oleh
    | RkaReviewActions agar dapat digunakan kembali.
    |
    | Parameter $this meneruskan konteks halaman, termasuk record,
    | item terpilih, dan indikator aktif.
    */

    /**
     * Action untuk review, pemangkasan, dan adjustment item belanja.
     */
    public function reviewItemAction(): Action
    {
        return RkaReviewActions::reviewItem($this);
    }

    /**
     * Action untuk mengimpor adjustment ke item belanja RKA.
     */
    public function importAdjustmentToItemAction(): Action
    {
        return RkaReviewActions::importAdjustmentToItem($this);
    }

    /**
     * Action untuk menyetujui item terpilih secara massal.
     */
    public function bulkApproveAction(): Action
    {
        return RkaReviewActions::bulkApprove($this);
    }

    /**
     * Action untuk memindahkan item terpilih secara massal.
     */
    public function bulkMoveAction(): Action
    {
        return RkaReviewActions::bulkMove($this);
    }

    /**
     * Action untuk menambahkan penyesuaian usulan RKA.
     */
    public function addAdjustmentAction(): Action
    {
        return RkaReviewActions::addAdjustment($this);
    }

    /**
     * Action untuk menghapus data penyesuaian RKA.
     */
    public function deleteAdjustmentAction(): Action
    {
        return RkaReviewActions::deleteAdjustment($this);
    }
}
