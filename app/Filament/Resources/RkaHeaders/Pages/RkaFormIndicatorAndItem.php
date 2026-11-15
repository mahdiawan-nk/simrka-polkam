<?php

namespace App\Filament\Resources\RkaHeaders\Pages;

use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use App\Filament\Actions\Rka\RkaActions;
use App\Services\RkaItemService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Livewire\Attributes\Computed;

/**
 * Halaman penyusunan formulir Rencana Kerja dan Anggaran (RKA).
 *
 * Halaman ini digunakan untuk mengelola program, indikator kinerja,
 * serta rincian item belanja berdasarkan RKA yang sedang dibuka.
 *
 * Fitur utama:
 * - Memuat data RKA beserta relasi pendukungnya.
 * - Menentukan indikator aktif untuk navigasi tab.
 * - Menampilkan hierarki akun dan item belanja berdasarkan indikator aktif.
 * - Menyediakan action untuk menambah program dan item belanja.
 * - Menyediakan action untuk mengedit, menghapus, dan mengimpor item.
 * - Mengajukan RKA serta menghitung ulang pagu anggaran.
 *
 * Implementasi action dipisahkan ke dalam RkaActions, sedangkan
 * pengolahan hierarki anggaran ditangani oleh RkaItemService.
 *
 * @package App\Filament\Resources\RkaHeaders\Pages
 */
class RkaFormIndicatorAndItem extends Page
{
    use InteractsWithRecord;

    /**
     * Resource Filament yang menaungi halaman penyusunan RKA.
     *
     * @var class-string<RkaHeaderResource>
     */
    protected static string $resource = RkaHeaderResource::class;

    /**
     * Lokasi template Blade untuk menampilkan halaman penyusunan RKA.
     *
     * @var string
     */
    protected string $view =
    'filament.resources.rka-headers.pages.rka-form-indicator-and-item';

    /**
     * ID indikator program yang sedang aktif pada tab.
     *
     * Nilai ini digunakan untuk menentukan indikator yang menjadi
     * konteks pengelolaan item belanja dan hierarki akun.
     *
     * Nilai null menunjukkan belum ada indikator yang dipilih.
     *
     * @var string|null
     */
    public ?string $activeTabGroup = null;

    /**
     * Inisialisasi halaman berdasarkan ID record RKA.
     *
     * Memuat record RKA beserta relasi jadwal, departemen, pengaturan,
     * indikator beserta akun, serta item beserta akun.
     *
     * Setelah data dimuat, indikator pertama dipilih sebagai tab aktif
     * apabila RKA memiliki setidaknya satu indikator.
     *
     * @param int|string $record ID record RKA yang akan ditampilkan.
     *
     * @return void
     */
    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record)->load([
            'schedule',
            'department',
            'setting',
            'indicators.account',
            'items.account',
        ]);

        // Menetapkan indikator pertama sebagai tab aktif jika tersedia.
        $firstIndicator = $this->record->indicators->first();

        if ($firstIndicator) {
            $this->activeTabGroup = (string) $firstIndicator->id;
        }
    }

    /**
     * Menyediakan data hierarki akun dan item belanja untuk tampilan.
     *
     * Hasil perhitungan diserahkan kepada RkaItemService berdasarkan
     * indikator yang sedang aktif.
     *
     * Properti computed ini dapat diakses dari Blade menggunakan
     * $this->budgetHierarchy.
     *
     * @return array Data hierarki akun, item belanja, dan total anggaran.
     */
    #[Computed]
    public function budgetHierarchy(): array
    {
        return app(RkaItemService::class)
            ->calculateBudgetHierarchy($this->activeTabGroup);
    }

    /**
     * Menentukan judul halaman penyusunan RKA.
     *
     * Judul mencantumkan nama departemen pemilik RKA. Jika nama
     * departemen tidak tersedia, digunakan teks pengganti.
     *
     * @return string|Htmlable Judul halaman.
     */
    public function getTitle(): string|Htmlable
    {
        return 'Penyusunan Formulir RKA - '
            . ($this->record->department->name ?? 'Unit Work');
    }

    /**
     * Membuat action untuk mengajukan RKA.
     *
     * Konfigurasi action dan proses pengajuan ditangani oleh
     * RkaActions agar implementasi action terpisah dari halaman.
     *
     * @return Action Action pengajuan RKA.
     */
    public function submitRkaAction(): Action
    {
        return RkaActions::submitRka(
            page: $this,
        );
    }

    /**
     * Membuat action untuk menambahkan program dan indikator kinerja.
     *
     * Setelah indikator berhasil dibuat, indikator tersebut dapat
     * ditetapkan sebagai tab aktif oleh implementasi action.
     *
     * @return Action Action penambahan program dan indikator.
     */
    public function addProgramAction(): Action
    {
        return RkaActions::addProgram(
            page: $this,
        );
    }

    /**
     * Membuat action untuk menambahkan rincian item belanja.
     *
     * Item belanja dikaitkan dengan indikator yang sedang aktif.
     *
     * @return Action Action penambahan item belanja.
     */
    public function addItemAction(): Action
    {
        return RkaActions::addItem(
            page: $this,
        );
    }

    /**
     * Membuat action untuk mengedit rincian item belanja.
     *
     * Konfigurasi form, pengisian data awal, dan penyimpanan perubahan
     * ditangani oleh RkaActions.
     *
     * @return Action Action pengeditan item belanja.
     */
    public function editItemAction(): Action
    {
        return RkaActions::editItem(
            page: $this,
        );
    }

    /**
     * Membuat action untuk mengimpor rincian item belanja dari Excel.
     *
     * Cakupan impor ditentukan berdasarkan pilihan pengguna, baik
     * untuk indikator aktif maupun seluruh indikator pada RKA.
     *
     * @return Action Action impor Excel.
     */
    public function importExcelAction(): Action
    {
        return RkaActions::importExcel(
            page: $this,
        );
    }

    /**
     * Membuat action untuk menghapus satu item belanja.
     *
     * Proses penghapusan dan perhitungan ulang anggaran ditangani
     * oleh RkaActions.
     *
     * @return Action Action penghapusan item belanja.
     */
    public function deleteItemAction(): Action
    {
        return RkaActions::deleteItem(
            page: $this,
        );
    }

    /**
     * Membuat action untuk menghapus seluruh item belanja pada RKA.
     *
     * Action ini digunakan untuk mengosongkan rincian item belanja
     * dalam satu dokumen RKA.
     *
     * @return Action Action penghapusan seluruh item belanja.
     */
    public function clearAllItemsAction(): Action
    {
        return RkaActions::clearAllItems(
            page: $this,
        );
    }

    /**
     * Membuat action untuk menghitung ulang pagu anggaran RKA.
     *
     * Perhitungan dilakukan melalui method calculatePaguRka()
     * pada model record RKA.
     *
     * @return Action Action perhitungan ulang pagu.
     */
    public function calculatePaguAction(): Action
    {
        return RkaActions::calculatePagu(
            page: $this,
        );
    }

    /**
     * Mengubah indikator yang sedang aktif pada tab.
     *
     * Method ini dapat dipanggil dari Blade ketika pengguna memilih
     * tab indikator yang berbeda.
     *
     * @param string $indicatorId ID indikator yang akan diaktifkan.
     *
     * @return void
     */
    public function setActiveTab(string $indicatorId): void
    {
        $this->activeTabGroup = $indicatorId;
    }
}
