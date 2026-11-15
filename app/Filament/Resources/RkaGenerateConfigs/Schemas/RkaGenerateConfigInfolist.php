<?php

namespace App\Filament\Resources\RkaGenerateConfigs\Schemas;

use App\Models\Department;
use App\Models\RkaSetting;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RkaGenerateConfigInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // SECTION 1: Informasi General & Meta Data
                Section::make('Informasi General Konfigurasi')
                    ->description('Detail status, nama konfigurasi, serta relasi setting RKA.')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('config_name')
                                ->label('Nama Konfigurasi')
                                ->weight('bold')
                                ->copyable()
                                ->columnSpan(2),

                            IconEntry::make('is_active')
                                ->label('Status Keaktifan')
                                ->boolean()
                                ->trueIcon('heroicon-o-check-circle')
                                ->falseIcon('heroicon-o-x-circle')
                                ->trueColor('success')
                                ->falseColor('danger')
                                ->columnSpan(1),
                        ]),

                        Grid::make(3)->schema([
                            TextEntry::make('rkaSetting.director_name')
                                ->label('Setting RKA (Direktur)')
                                ->placeholder('-')
                                ->formatStateUsing(function ($state, $record) {
                                    if (!$record->rka_setting_id) {
                                        return 'Global (Tanpa Linked Setting)';
                                    }
                                    return "Setting ID #{$record->rka_setting_id} - Dir: " . ($record->rkaSetting?->director_name ?? '-');
                                })
                                ->badge()
                                ->color('info'),

                            TextEntry::make('creator.name')
                                ->label('Dibuat Oleh')
                                ->placeholder('System / Admin')
                                ->icon('heroicon-o-user'),

                            TextEntry::make('created_at')
                                ->label('Waktu Dibuat')
                                ->dateTime('d M Y, H:i T')
                                ->placeholder('-')
                                ->icon('heroicon-o-calendar'),
                        ]),
                    ]),

                // SECTION 2: Departemen Terpilih
                Section::make('1. Target Departemen / Unit')
                    ->description('Daftar departemen yang dikonfigurasikan untuk menerima auto generate RKA.')
                    ->icon('heroicon-o-building-office-2')
                    ->schema([
                        TextEntry::make('selected_department_ids')
                            ->label('Departemen Terpilih')
                            ->badge()
                            ->color('primary')
                            ->placeholder('Tidak Ada Departemen Terpilih')
                            ->formatStateUsing(function ($state) {
                                // Jika state berupa ID tunggal (karena Filament melakukan iterasi per item array)
                                if (is_numeric($state)) {
                                    return Department::find($state)?->name ?? "Dept ID #{$state}";
                                }

                                // Jika state berupa array / JSON string dari banyak ID
                                $deptIds = is_array($state) ? $state : json_decode($state, true);

                                if (empty($deptIds)) {
                                    return null;
                                }

                                return Department::whereIn('id', (array) $deptIds)
                                    ->pluck('name')
                                    ->toArray();
                            }),
                    ]),

                // SECTION 3: Template Indikator & Matriks Belanja
                Section::make('2. Template Indikator Level 1')
                    ->description('Rincian indikator, nama program, kegiatan, dan matriks tolok ukur kinerja.')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        RepeatableEntry::make('template_indicators')
                            ->label('Daftar Indikator Level 1')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('account_code')
                                        ->label('Formulir RKA')
                                        ->formatStateUsing(fn($state) => "Formulir RKA-{$state}")
                                        ->weight('bold')
                                        ->color('primary')
                                        ->columnSpan(2),

                                    TextEntry::make('is_active')
                                        ->label('Status Indikator')
                                        ->badge()
                                        ->formatStateUsing(fn($state): string => !empty($state) ? 'Aktif' : 'Non-Aktif')
                                        ->color(fn(string $state): string => $state ? 'success' : 'gray')
                                        ->columnSpan(1),
                                ]),

                                Grid::make(2)->schema([
                                    TextEntry::make('program_name')
                                        ->label('Nama Program')
                                        ->icon('heroicon-o-folder'),

                                    TextEntry::make('activity_name')
                                        ->label('Nama Kegiatan')
                                        ->icon('heroicon-o-clipboard-document-list'),
                                ]),

                                // Rincian Matriks Tolok Ukur Kinerja Belanja
                                RepeatableEntry::make('matrix')
                                    ->label('Matriks Tolok Ukur Kinerja Belanja')
                                    ->schema([
                                        Grid::make(4)->schema([
                                            TextEntry::make('indicator')
                                                ->label('Indikator')
                                                ->weight('medium'),

                                            TextEntry::make('benchmark')
                                                ->label('Tolok Ukur Kinerja')
                                                ->placeholder('-'),

                                            TextEntry::make('target')
                                                ->label('Target')
                                                ->placeholder('-')
                                                ->alignCenter(),

                                            TextEntry::make('unit')
                                                ->label('Satuan')
                                                ->placeholder('-')
                                                ->alignCenter(),
                                        ]),
                                    ])
                                    ->columns(1),
                            ])
                            ->columns(1),
                    ]),
            ]);
    }
}
