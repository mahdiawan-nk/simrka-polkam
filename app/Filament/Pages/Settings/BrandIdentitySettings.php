<?php

namespace App\Filament\Pages\Settings;

use App\Services\Branding\BrandSettingsService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class BrandIdentitySettings extends Page implements HasSchemas
{
    use HasPageShield;
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan Sistem';

    protected static ?string $navigationLabel = 'Identitas Aplikasi';

    protected static ?string $title = 'Pengaturan Identitas Aplikasi';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.settings.brand-identity-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(BrandSettingsService $branding): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill($branding->getSettings());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Pengaturan Identitas')
                    ->tabs([
                        Tab::make('Identitas Brand')
                            ->icon(Heroicon::OutlinedIdentification)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('brand_name')
                                            ->label('Nama Brand')
                                            ->placeholder('Contoh: e-RKA')
                                            ->required()
                                            ->maxLength(100)
                                            ->live(onBlur: true),

                                        TextInput::make('institution_short_name')
                                            ->label('Singkatan Sistem / Institusi')
                                            ->placeholder('Contoh: SIMRKA')
                                            ->required()
                                            ->maxLength(50)
                                            ->live(onBlur: true),

                                        TextInput::make('app_full_name')
                                            ->label('Nama Lengkap Aplikasi')
                                            ->placeholder('Contoh: Sistem Perencanaan & Anggaran')
                                            ->required()
                                            ->maxLength(150)
                                            ->live(onBlur: true),

                                        TextInput::make('institution_name')
                                            ->label('Nama Institusi')
                                            ->placeholder('Contoh: Politeknik Kampar')
                                            ->required()
                                            ->maxLength(150)
                                            ->live(onBlur: true),
                                    ]),

                                TextInput::make('tagline')
                                    ->label('Tagline')
                                    ->placeholder('Masukkan tagline aplikasi atau institusi')
                                    ->required()
                                    ->maxLength(200)
                                    ->live(onBlur: true),

                                Textarea::make('short_description')
                                    ->label('Deskripsi Singkat')
                                    ->placeholder('Deskripsi singkat yang tampil di halaman login')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->live(onBlur: true),
                            ]),

                        Tab::make('Logo & Favicon')
                            ->icon(Heroicon::OutlinedPhoto)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        $this->makeLogoUpload('logo_path', 'Logo Utama', 'Digunakan sebagai logo utama aplikasi dan fallback seluruh varian.'),
                                        $this->makeLogoUpload('logo_light_path', 'Logo Mode Terang (Opsional)', 'Ditampilkan pada topbar/sidebar mode terang.'),
                                        $this->makeLogoUpload('logo_dark_path', 'Logo Mode Gelap (Opsional)', 'Ditampilkan pada topbar/sidebar mode gelap.'),
                                        $this->makeLogoUpload('logo_compact_path', 'Logo Compact / Ikon (Opsional)', 'Digunakan saat sidebar diciutkan atau tampilan mobile.'),
                                        $this->makeLogoUpload('login_logo_path', 'Logo Halaman Login (Opsional)', 'Khusus panel branding pada halaman masuk.'),

                                        FileUpload::make('favicon_path')
                                            ->label('Favicon Browser')
                                            ->helperText('Format: PNG, ICO, atau SVG aman. Maksimal 512 KB.')
                                            ->disk(BrandSettingsService::STORAGE_DISK)
                                            ->directory(BrandSettingsService::STORAGE_DIR)
                                            ->visibility('public')
                                            ->acceptedFileTypes([
                                                'image/png',
                                                'image/x-icon',
                                                'image/vnd.microsoft.icon',
                                                'image/svg+xml',
                                            ])
                                            ->maxSize(512)
                                            ->imagePreviewHeight('48')
                                            ->live(),
                                    ]),
                            ]),

                        Tab::make('Browser & Dokumen')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                Section::make('Pengaturan Browser')
                                    ->description('Menentukan judul tab browser saat mengakses panel administrasi.')
                                    ->schema([
                                        TextInput::make('browser_title')
                                            ->label('Judul Tab Browser (Browser Title)')
                                            ->placeholder('Contoh: SIMRKA / e-RKA — Politeknik Kampar')
                                            ->required()
                                            ->maxLength(160)
                                            ->live(onBlur: true),
                                    ]),

                                Section::make('Identitas Dokumen Cetak (Opsional)')
                                    ->description('Digunakan pada header/footer dokumen cetak apabila diaktifkan.')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('document_institution_name')
                                                    ->label('Nama Institusi pada Dokumen')
                                                    ->placeholder('Kosongkan untuk mengikuti Nama Institusi utama')
                                                    ->maxLength(150),

                                                TextInput::make('document_tagline')
                                                    ->label('Tagline Dokumen')
                                                    ->placeholder('Kosongkan untuk mengikuti Tagline utama')
                                                    ->maxLength(200),
                                            ]),

                                        TextInput::make('document_footer_text')
                                            ->label('Informasi Footer Dokumen')
                                            ->placeholder('Contoh: Dokumen RKA Politeknik Kampar')
                                            ->maxLength(255),

                                        $this->makeLogoUpload('document_logo_path', 'Logo Dokumen (Opsional)', 'Kosongkan untuk menggunakan logo utama/default dokumen.'),
                                    ]),
                            ]),
                    ])
                    ->persistTabInQueryString(),
            ])
            ->statePath('data');
    }

    public function save(BrandSettingsService $branding): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        $branding->updateSettings($state, Auth::id());

        Notification::make()
            ->success()
            ->title('Identitas aplikasi berhasil disimpan')
            ->body('Perubahan brand, logo, judul browser, dan favicon telah langsung diterapkan ke seluruh aplikasi.')
            ->send();

        $this->js('window.location.reload()');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetDefaults')
                ->label('Kembalikan ke Default')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Kembalikan Identitas Default?')
                ->modalDescription('Tindakan ini akan mengembalikan nama brand, institusi, tagline, dan menghapus logo/favicon kustom ke setelan bawaan aplikasi.')
                ->modalSubmitActionLabel('Ya, Kembalikan')
                ->action(function (BrandSettingsService $branding): void {
                    abort_unless(static::canAccess(), 403);

                    $branding->updateSettings($branding->getDefaults(), Auth::id());
                    $this->form->fill($branding->getSettings());

                    Notification::make()
                        ->success()
                        ->title('Identitas dikembalikan ke default')
                        ->send();

                    $this->js('window.location.reload()');
                }),
        ];
    }

    /**
     * Resolve preview URLs safely for both persisted storage paths and temporary Livewire uploads.
     *
     * @return array<string, mixed>
     */
    public function getPreviewData(): array
    {
        $branding = app(BrandSettingsService::class);
        $active = $branding->getSettings();
        $draft = array_merge($active, $this->data ?? []);

        return [
            'active' => [
                'brand_name' => $active['brand_name'],
                'institution_short_name' => $active['institution_short_name'],
                'app_full_name' => $active['app_full_name'],
                'institution_name' => $active['institution_name'],
                'tagline' => $active['tagline'],
                'browser_title' => $active['browser_title'],
                'logo_url' => $branding->getLogoUrl('primary'),
                'favicon_url' => $branding->getFaviconUrl(),
            ],
            'draft' => [
                'brand_name' => $draft['brand_name'] ?: $active['brand_name'],
                'institution_short_name' => $draft['institution_short_name'] ?: $active['institution_short_name'],
                'app_full_name' => $draft['app_full_name'] ?: $active['app_full_name'],
                'institution_name' => $draft['institution_name'] ?: $active['institution_name'],
                'tagline' => $draft['tagline'] ?: $active['tagline'],
                'short_description' => $draft['short_description'] ?: $active['short_description'],
                'browser_title' => $draft['browser_title'] ?: $active['browser_title'],
                'logo_url' => $this->resolvePreviewUrl($draft['logo_path'] ?? null) ?? $branding->getLogoUrl('primary'),
                'compact_logo_url' => $this->resolvePreviewUrl($draft['logo_compact_path'] ?? null)
                    ?? $this->resolvePreviewUrl($draft['logo_path'] ?? null)
                    ?? $branding->getLogoUrl('compact'),
                'login_logo_url' => $this->resolvePreviewUrl($draft['login_logo_path'] ?? null)
                    ?? $this->resolvePreviewUrl($draft['logo_path'] ?? null)
                    ?? $branding->getLogoUrl('login'),
                'favicon_url' => $this->resolvePreviewUrl($draft['favicon_path'] ?? null) ?? $branding->getFaviconUrl(),
            ],
        ];
    }

    protected function resolvePreviewUrl(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value) ?: null;
        }

        if ($value instanceof TemporaryUploadedFile) {
            return $value->isPreviewable() ? $value->temporaryUrl() : null;
        }

        if (is_string($value) && $value !== '') {
            $disk = Storage::disk(BrandSettingsService::STORAGE_DISK);

            return $disk->exists($value) ? $disk->url($value) : null;
        }

        return null;
    }

    protected function makeLogoUpload(string $field, string $label, string $helperText): FileUpload
    {
        return FileUpload::make($field)
            ->label($label)
            ->helperText($helperText.' Format: PNG, JPG, WebP, atau SVG aman (maks. 2 MB).')
            ->disk(BrandSettingsService::STORAGE_DISK)
            ->directory(BrandSettingsService::STORAGE_DIR)
            ->visibility('public')
            ->image()
            ->acceptedFileTypes([
                'image/png',
                'image/jpeg',
                'image/webp',
                'image/svg+xml',
            ])
            ->maxSize(2048)
            ->imagePreviewHeight('72')
            ->live();
    }
}
