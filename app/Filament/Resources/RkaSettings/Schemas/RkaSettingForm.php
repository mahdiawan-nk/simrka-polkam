<?php

namespace App\Filament\Resources\RkaSettings\Schemas;

use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class RkaSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('RkaSettingTabs')
                    ->tabs([
                        // TAB 1: INFORMASI UMUM & PEJABAT
                        Tabs\Tab::make('Informasi & Pejabat')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Section::make('Identitas Pengaturan')
                                    ->description('Nama konfigurasi dan tempat pelaksanaan tanda tangan.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('setting_name')
                                                ->label('Nama Konfigurasi')
                                                ->placeholder('Misal: Setting RKA Standar 2026')
                                                ->required()
                                                ->maxLength(255),

                                            TextInput::make('signing_place')
                                                ->label('Tempat TTE / Tanda Tangan')
                                                ->placeholder('Bangkinang')
                                                ->required()
                                                ->default('Bangkinang')
                                                ->maxLength(100),
                                        ]),
                                    ]),

                                Section::make('Data Pejabat & Penandatangan Utama')
                                    ->description('Informasi pimpinan institusi untuk lembar pengesahan RKA.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('director_user_id')
                                                ->label('User Akun Direktur')
                                                ->relationship('director', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    if ($user = User::find($state)) {
                                                        $set('director_name', $user->name);
                                                    }
                                                })
                                                ->helperText('Pilih akun user yang menjabat sebagai Direktur.'),

                                            TextInput::make('director_name')
                                                ->label('Nama Lengkap Direktur (beserta Gelar)')
                                                ->required()
                                                ->maxLength(150),

                                            TextInput::make('director_nrp')
                                                ->label('NRP / NIDN Direktur')
                                                ->required()
                                                ->maxLength(50),

                                            TextInput::make('foundation_head_name')
                                                ->label('Nama Ketua Yayasan')
                                                ->required()
                                                ->maxLength(150),
                                        ]),
                                    ]),
                            ]),

                        // TAB 2: MATRIKS PENANDATANGAN (JSON)
                        Tabs\Tab::make('Matriks Penandatangan')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Section::make('Alur Verifikasi & Persetujuan')
                                    ->description('Konfigurasi daftar pihak yang menandatangani dokumen RKA.')
                                    ->schema([
                                        Repeater::make('signing_config')
                                            ->label('Daftar Penandatangan')
                                            ->schema([
                                                Grid::make(3)->schema([
                                                    TextInput::make('stage_key')
                                                        ->label('Kode Tahap')
                                                        ->placeholder('pemohon / verifikasi_1 / approval')
                                                        ->required(),

                                                    TextInput::make('label')
                                                        ->label('Jabatan pada Dokumen')
                                                        ->placeholder('Misal: Ka. Unit / Wadir II')
                                                        ->required(),

                                                    Select::make('role')
                                                        ->label('Role Akses Sistem')
                                                        ->options(Role::where('name', '!=', 'super_admin')->pluck('name', 'name')->toArray())
                                                        ->required(),
                                                ]),


                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('manual_name')
                                                            ->label('Nama Lengkap beserta Gelar')
                                                            ->placeholder('Misal: Dr. Ir. H. Ahmad Fauzi, M.T.')
                                                            ->required(fn($get) => (bool) $get('is_manual_input')),

                                                        TextInput::make('manual_nip')
                                                            ->label('NRP / NIP')
                                                            ->placeholder('Misal: 19820315 200812 1 002')
                                                            ->required(fn($get) => (bool) $get('is_manual_input')),
                                                    ])
                                            ])
                                            ->columns(1)
                                            ->defaultItems(4)
                                            ->reorderableWithButtons()
                                            ->collapsible(),
                                    ]),
                            ]),

                        // TAB 3: TEMPLATE PDF (JSON HEADER & FOOTER)
                        Tabs\Tab::make('Template Dokumen PDF')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Konfigurasi Header (Kop Surat)')
                                    ->description('Atur tipe kop surat sesuai kebutuhan pencetakan PDF RKA.')
                                    ->schema([
                                        Radio::make('pdf_header_config.header_type')
                                            ->label('Mode Header / Kop Surat')
                                            ->options([
                                                'full_image' => 'Full Image Kop Surat (Satu Gambar Utuh)',
                                                'manual_two_logos' => 'Manual (Dua Logo: Kiri & Kanan)',
                                                'manual_single_logo' => 'Manual (Satu Logo)',
                                            ])
                                            ->default('manual_two_logos')
                                            ->reactive()
                                            ->inline()
                                            ->columnSpanFull(),

                                        // 1. OPSI FULL IMAGE KOP
                                        Grid::make(1)
                                            ->schema([
                                                FileUpload::make('pdf_header_config.full_kop_image')
                                                    ->label('Upload Gambar Kop Surat Lengkap')
                                                    ->image()
                                                    ->directory('rka-settings/headers')
                                                    ->helperText('Unggah gambar header utuh beresolusi tinggi (meliputi logo, teks, dan garis pembatas).')
                                                    ->required(fn($get) => $get('pdf_header_config.header_type') === 'full_image'),
                                            ])
                                            ->hidden(fn($get) => $get('pdf_header_config.header_type') !== 'full_image'),

                                        // 2. OPSI MANUAL (LOGOS & TEKS)
                                        Grid::make(2)
                                            ->schema([
                                                // Opsi Posisi Logo untuk Single Logo Mode
                                                Select::make('pdf_header_config.single_logo_position')
                                                    ->label('Posisi Logo Utama')
                                                    ->options([
                                                        'left' => 'Kiri (Teks di Kanan)',
                                                        'right' => 'Kanan (Teks di Kiri)',
                                                    ])
                                                    ->default('left')
                                                    ->required(fn($get) => $get('pdf_header_config.header_type') === 'manual_single_logo')
                                                    ->hidden(fn($get) => $get('pdf_header_config.header_type') !== 'manual_single_logo')
                                                    ->columnSpanFull(),

                                                FileUpload::make('pdf_header_config.left_logo')
                                                    ->label(fn($get) => $get('pdf_header_config.header_type') === 'manual_single_logo' ? 'Logo Utama' : 'Logo Kiri (Misal: Logo Yayasan)')
                                                    ->image()
                                                    ->directory('rka-settings/logos')
                                                    ->required(fn($get) => in_array($get('pdf_header_config.header_type'), ['manual_two_logos', 'manual_single_logo'])),

                                                FileUpload::make('pdf_header_config.right_logo')
                                                    ->label('Logo Kanan (Misal: Logo Institusi)')
                                                    ->image()
                                                    ->directory('rka-settings/logos')
                                                    ->hidden(fn($get) => $get('pdf_header_config.header_type') !== 'manual_two_logos')
                                                    ->required(fn($get) => $get('pdf_header_config.header_type') === 'manual_two_logos'),

                                                TextInput::make('pdf_header_config.foundation_name')
                                                    ->label('Nama Yayasan (Baris 1)')
                                                    ->placeholder('YAYASAN DATUK TABANO')
                                                    ->default('YAYASAN DATUK TABANO')
                                                    ->columnSpanFull(),

                                                TextInput::make('pdf_header_config.institution_name')
                                                    ->label('Nama Institusi (Baris 2 Utama)')
                                                    ->placeholder('POLITEKNIK KAMPAR')
                                                    ->default('POLITEKNIK KAMPAR')
                                                    ->required(fn($get) => $get('pdf_header_config.header_type') !== 'full_image')
                                                    ->columnSpanFull(),

                                                TextInput::make('pdf_header_config.address_line')
                                                    ->label('Alamat / Sekretariat (Baris 3)')
                                                    ->placeholder('Sekretariat: Jl. Tengku Muhammad KM.2 Bangkinang Riau 28461')
                                                    ->default('Sekretariat: Jl. Tengku Muhammad KM.2 Bangkinang Riau 28461')
                                                    ->columnSpanFull(),

                                                TextInput::make('pdf_header_config.contact_line')
                                                    ->label('Kontak / Website / Email (Baris 4)')
                                                    ->placeholder('Telp: 0762 3240818 HP. 0852 1129 0621 www.poltek-kampar.ac.id Email : polkam@poltek-kampar.ac.id')
                                                    ->default('Telp: 0762 3240818 HP. 0852 1129 0621 www.poltek-kampar.ac.id Email : polkam@poltek-kampar.ac.id')
                                                    ->columnSpanFull(),

                                                Toggle::make('pdf_header_config.show_divider_line')
                                                    ->label('Tampilkan Garis Pembatas (Double Line)')
                                                    ->default(true)
                                                    ->columnSpanFull(),
                                            ])
                                            ->hidden(fn($get) => $get('pdf_header_config.header_type') === 'full_image'),
                                    ]),

                                Section::make('Konfigurasi Footer & Catatan Kaki')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('pdf_footer_config.footer_text')
                                                ->label('Teks Catatan Kaki')
                                                ->default('Dokumen Rencana Kerja & Anggaran (RKA) Politeknik Kampar')
                                                ->maxLength(255),

                                            Toggle::make('pdf_footer_config.show_page_number')
                                                ->label('Tampilkan Nomor Halaman (Hal x dari y)')
                                                ->default(true),
                                        ]),

                                        KeyValue::make('pdf_footer_config.extra_notes')
                                            ->label('Catatan Tambahan Footer')
                                            ->keyLabel('Atribut')
                                            ->valueLabel('Nilai'),
                                    ]),
                            ])
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
