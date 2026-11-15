<?php

namespace App\Filament\Resources\RkaGenerateConfigs\Schemas;

use App\Models\Account;
use App\Models\Department;
use App\Models\RkaSetting;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\{Get, Set};
use Filament\Schemas\Schema;

class RkaGenerateConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // SECTION 1: Informasi Dasar Konfigurasi
                Section::make('Informasi General Konfigurasi')
                    ->description('Atur nama konfigurasi, relasi ke setting RKA, dan status keaktifan.')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('config_name')
                                ->label('Nama Konfigurasi')
                                ->placeholder('Contoh: Konfigurasi Default RKA 2026')
                                ->required()
                                ->maxLength(150)
                                ->default('Konfigurasi Default Generate')
                                ->columnSpan(2),

                            Toggle::make('is_active')
                                ->label('Status Aktif')
                                ->helperText('Hanya 1 konfigurasi yang aktif dalam satu waktu.')
                                ->default(true)
                                ->inline(false)
                                ->columnSpan(1),
                        ]),

                        Select::make('rka_setting_id')
                            ->label('Setting RKA Terkait')
                            ->relationship('rkaSetting', 'id')
                            ->getOptionLabelFromRecordUsing(fn (RkaSetting $record) => "Setting ID #{$record->id} - Name: {$record->setting_name}")
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder('Pilih Setting RKA (Opsional)'),

                        Hidden::make('created_by')
                            ->default(fn () => auth()->id()),
                    ]),

                // SECTION 2: Pemilihan Departemen / Unit
                Section::make('1. Target Departemen / Unit')
                    ->description('Pilih departemen yang akan secara otomatis mendapatkan RKA Header & Indikator.')
                    ->icon('heroicon-o-building-office-2')
                    ->schema([
                        CheckboxList::make('selected_department_ids')
                            ->label('Daftar Departemen Terpilih')
                            ->options(fn () => Department::pluck('name', 'id')->toArray())
                            ->columns(3)
                            ->gridDirection('row')
                            ->required()
                            ->bulkToggleable(),
                    ]),

                // SECTION 3: Konfigurasi Template Indikator Level 1
                Section::make('2. Template Indikator (Account Level 1)')
                    ->description('Atur indikator default, nama program, kegiatan, dan matriks tolok ukur kinerja.')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Repeater::make('template_indicators')
                            ->label('Daftar Indikator Level 1')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->default(fn () => self::getDefaultTemplateStructure())
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('account_code')
                                        ->label('Kelompok Akun (Level 1)')
                                        ->options(fn () => Account::where('level', 1)
                                            ->where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(fn ($acc) => [$acc->code => "[{$acc->code}] {$acc->name}"])
                                            ->toArray()
                                        )
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(2),

                                    Toggle::make('is_active')
                                        ->label('Sertakan Indikator Ini')
                                        ->default(true)
                                        ->inline(false)
                                        ->columnSpan(1),
                                ]),

                                Grid::make(2)->schema([
                                    TextInput::make('program_name')
                                        ->label('Nama Program')
                                        ->required()
                                        ->placeholder('Masukkan nama program...'),

                                    TextInput::make('activity_name')
                                        ->label('Nama Kegiatan')
                                        ->required()
                                        ->placeholder('Masukkan nama kegiatan...'),
                                ]),

                                // Matriks Indikator Belanja
                                Repeater::make('matrix')
                                    ->label('Matriks Tolok Ukur Kinerja Belanja')
                                    ->addable(true)
                                    ->deletable(true)
                                    ->reorderable(true)
                                    ->schema([
                                        Grid::make(4)->schema([
                                            TextInput::make('indicator')
                                                ->label('Indikator')
                                                ->placeholder('misal: Capaian Program')
                                                ->required(),

                                            TextInput::make('benchmark')
                                                ->label('Tolok Ukur Kinerja')
                                                ->placeholder('Gunakan :department_name untuk otomatisasi')
                                                ->required(),

                                            TextInput::make('target')
                                                ->label('Target')
                                                ->placeholder('misal: 1 / -'),

                                            TextInput::make('unit')
                                                ->label('Satuan')
                                                ->placeholder('misal: Tahun / Rupiah'),
                                        ]),
                                    ])
                                    ->columns(1)
                                    ->collapsible(),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => isset($state['account_code']) 
                                ? "Formulir RKA-{$state['account_code']}" 
                                : 'Formulir Indikator'
                            ),
                    ]),
            ])
            ->columns(1);
    }

    /**
     * Struktur preset bawaan sesuai 6 formulir RKA (5.1 - 5.5 & 2.1)
     */
    protected static function getDefaultTemplateStructure(): array
    {
        return [
            '5.1' => [
                'account_code'  => '5.1',
                'is_active'     => true,
                'program_name'  => 'Peningkatan Kesejahteraan Pegawai',
                'activity_name' => 'Belanja Pegawai',
                'matrix'        => [
                    ['indicator' => 'Capaian Program', 'benchmark' => 'Tercapainya kesejahteraan pegawai', 'target' => '87', 'unit' => 'Pegawai'],
                    ['indicator' => 'Masukan', 'benchmark' => 'Jumlah dana', 'target' => '-', 'unit' => 'Rupiah'],
                    ['indicator' => 'Keluaran', 'benchmark' => 'Terlaksananya pembayaran gaji dan tunjangan', 'target' => '13', 'unit' => 'Bulan'],
                    ['indicator' => 'Hasil', 'benchmark' => 'Terjadinya peningkatan kinerja pegawai', 'target' => '', 'unit' => ''],
                    ['indicator' => 'Kelompok Sasaran', 'benchmark' => 'Seluruh Pegawai Politeknik Kampar', 'target' => '', 'unit' => ''],
                ],
            ],
            '5.2' => [
                'account_code'  => '5.2',
                'is_active'     => true,
                'program_name'  => 'Program Pelayanan Administrasi Perkantoran',
                'activity_name' => 'Belanja Barang dan Jasa',
                'matrix'        => [
                    ['indicator' => 'Capaian Program', 'benchmark' => 'Tersedianya barang dan jasa untuk operasional unit', 'target' => '1', 'unit' => 'Tahun'],
                    ['indicator' => 'Masukan', 'benchmark' => 'Jumlah dana', 'target' => '-', 'unit' => 'Rupiah'],
                    ['indicator' => 'Keluaran', 'benchmark' => 'Terpenuhinya kebutuhan barang dan jasa unit', 'target' => '1', 'unit' => 'Tahun'],
                    ['indicator' => 'Hasil', 'benchmark' => 'Tercapainya pelayanan administrasi dan pendidikan', 'target' => '', 'unit' => ''],
                    ['indicator' => 'Kelompok Sasaran', 'benchmark' => ':department_name', 'target' => '', 'unit' => ''],
                ],
            ],
            '5.3' => [
                'account_code'  => '5.3',
                'is_active'     => true,
                'program_name'  => 'Program Peningkatan Sarana dan Prasarana',
                'activity_name' => 'Belanja Modal',
                'matrix'        => [
                    ['indicator' => 'Capaian Program', 'benchmark' => 'Tersedianya sarana dan prasarana (aset tetap)', 'target' => '1', 'unit' => 'Paket'],
                    ['indicator' => 'Masukan', 'benchmark' => 'Jumlah dana', 'target' => '-', 'unit' => 'Rupiah'],
                    ['indicator' => 'Keluaran', 'benchmark' => 'Bertambahnya aset tetap unit', 'target' => '1', 'unit' => 'Paket'],
                    ['indicator' => 'Hasil', 'benchmark' => 'Meningkatnya kualitas sarana pendidikan', 'target' => '', 'unit' => ''],
                    ['indicator' => 'Kelompok Sasaran', 'benchmark' => ':department_name', 'target' => '', 'unit' => ''],
                ],
            ],
            '5.4' => [
                'account_code'  => '5.4',
                'is_active'     => true,
                'program_name'  => 'Program Penelitian, Pengabdian dan Penghargaan',
                'activity_name' => 'Belanja Hibah',
                'matrix'        => [
                    ['indicator' => 'Capaian Program', 'benchmark' => 'Terlaksananya penelitian, pengabdian dan pemberian penghargaan', 'target' => '1', 'unit' => 'Tahun'],
                    ['indicator' => 'Masukan', 'benchmark' => 'Jumlah dana', 'target' => '-', 'unit' => 'Rupiah'],
                    ['indicator' => 'Keluaran', 'benchmark' => 'Tersalurkannya hibah penelitian, pengabdian dan penghargaan', 'target' => '1', 'unit' => 'Tahun'],
                    ['indicator' => 'Hasil', 'benchmark' => 'Meningkatnya kinerja tridharma', 'target' => '', 'unit' => ''],
                    ['indicator' => 'Kelompok Sasaran', 'benchmark' => ':department_name', 'target' => '', 'unit' => ''],
                ],
            ],
            '5.5' => [
                'account_code'  => '5.5',
                'is_active'     => true,
                'program_name'  => 'Program Pelayanan Administrasi Perkantoran',
                'activity_name' => 'Belanja Pajak',
                'matrix'        => [
                    ['indicator' => 'Capaian Program', 'benchmark' => 'Terpenuhinya kewajiban perpajakan', 'target' => '1', 'unit' => 'Tahun'],
                    ['indicator' => 'Masukan', 'benchmark' => 'Jumlah dana', 'target' => '-', 'unit' => 'Rupiah'],
                    ['indicator' => 'Keluaran', 'benchmark' => 'Terbayarnya pajak tepat waktu', 'target' => '1', 'unit' => 'Tahun'],
                    ['indicator' => 'Hasil', 'benchmark' => 'Kepatuhan pajak lembaga', 'target' => '', 'unit' => ''],
                    ['indicator' => 'Kelompok Sasaran', 'benchmark' => ':department_name', 'target' => '', 'unit' => ''],
                ],
            ],
            '2.1' => [
                'account_code'  => '2.1',
                'is_active'     => true,
                'program_name'  => 'Program Pelayanan Administrasi Perkantoran',
                'activity_name' => 'Pembayaran Kewajiban',
                'matrix'        => [
                    ['indicator' => 'Capaian Program', 'benchmark' => 'Terlunasinya kewajiban tahun sebelumnya', 'target' => '1', 'unit' => 'Tahun'],
                    ['indicator' => 'Masukan', 'benchmark' => 'Jumlah dana', 'target' => '-', 'unit' => 'Rupiah'],
                    ['indicator' => 'Keluaran', 'benchmark' => 'Terbayarnya hutang gaji', 'target' => '1', 'unit' => 'Paket'],
                    ['indicator' => 'Hasil', 'benchmark' => 'Tidak ada tunggakan kewajiban', 'target' => '', 'unit' => ''],
                    ['indicator' => 'Kelompok Sasaran', 'benchmark' => ':department_name', 'target' => '', 'unit' => ''],
                ],
            ],
        ];
    }
}