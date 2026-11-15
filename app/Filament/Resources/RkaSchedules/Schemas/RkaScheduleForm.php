<?php

namespace App\Filament\Resources\RkaSchedules\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class RkaScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    // KOLOM KIRI (2 SPAN): DETAIL JADWAL & WAKTU
                    Grid::make(1)
                        ->schema([
                            Section::make('Identitas Tahun & Gelombang RKA')
                                ->description('Atur tahun anggaran dan nama fase pengisian RKA.')
                                ->icon('heroicon-o-calendar-days')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('fiscal_year')
                                            ->label('Tahun Anggaran')
                                            ->placeholder('Misal: 2026/2027')
                                            ->required()
                                            ->maxLength(10)
                                            ->helperText('Format standar: YYYY/YYYY'),

                                        Select::make('phase_name')
                                            ->label('Tahapan / Gelombang')
                                            ->options([
                                                'Pengusulan Awal' => 'Pengusulan Awal',
                                                'Revisi Pasca Review' => 'Revisi Pasca Review',
                                                'Pengusulan Susulan' => 'Pengusulan Susulan',
                                                'Finalisasi & Pengesahan' => 'Finalisasi & Pengesahan',
                                            ])
                                            ->searchable()
                                            ->createOptionForm([
                                                TextInput::make('phase_name')
                                                    ->label('Nama Tahapan Baru')
                                                    ->required(),
                                            ])
                                            ->required(),

                                        TextInput::make('period_range')
                                            ->label('Rentang Periode Kinerja')
                                            ->placeholder('Misal: September 2026 – Agustus 2027')
                                            ->required()
                                            ->maxLength(100)
                                            ->columnSpanFull()
                                            ->helperText('Keterangan masa berlaku pelaksanaan anggaran.'),
                                    ]),
                                ]),

                            Section::make('Rentang Waktu Akses Portal')
                                ->description('Tentukan kapan portal pengisian dibuka dan ditutup untuk unit.')
                                ->icon('heroicon-o-clock')
                                ->schema([
                                    Grid::make(2)->schema([
                                        DateTimePicker::make('start_at')
                                            ->label('Waktu Portal Dibuka')
                                            ->required()
                                            ->seconds(false)
                                            ->native(false)
                                            ->displayFormat('d M Y, H:i')
                                            ->closeOnDateSelection(),

                                        DateTimePicker::make('end_at')
                                            ->label('Waktu Portal Ditutup')
                                            ->required()
                                            ->seconds(false)
                                            ->native(false)
                                            ->displayFormat('d M Y, H:i')
                                            ->after('start_at')
                                            ->closeOnDateSelection()
                                            ->helperText('Waktu penutupan harus setelah waktu pembukaan.'),
                                    ]),
                                ]),
                        ])
                        ->columnSpan(2),

                    // KOLOM KANAN (1 SPAN): KONTROL KETENTUAN & METADATA
                    Grid::make(1)
                        ->schema([
                            Section::make('Kontrol & Status Portal')
                                ->description('Pengaturan aturan akses portal RKA.')
                                ->icon('heroicon-o-adjustments-horizontal')
                                ->schema([
                                    Toggle::make('is_active')
                                        ->label('Aktifkan Jadwal Ini')
                                        ->helperText('Matikan jika jadwal ini ingin ditangguhkan sementara.')
                                        ->default(true)
                                        ->onColor('success')
                                        ->offColor('danger'),

                                    Toggle::make('allow_late_submission')
                                        ->label('Izinkan Pengiriman Susulan')
                                        ->helperText('Unit tetap dapat melakukan submit meskipun melewati batas waktu end_at.')
                                        ->default(false)
                                        ->onColor('warning'),
                                ]),

                            // Perekaman Pembuat Jadwal Secara Otomatis
                            Hidden::make('created_by')
                                ->default(fn () => Auth::id()),
                        ])
                        ->columnSpan(1),
                ]),
            ])
            ->columns(1);
    }
}