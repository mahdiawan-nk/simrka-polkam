<?php

namespace App\Filament\Resources\RkaHeaders\Schemas;

use App\Models\RkaHeader;
use App\Models\RkaSchedule;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class RkaHeaderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('RkaHeaderTabs')
                    ->tabs([
                        // TAB 1: DOKUMEN & PENGUSUL
                        Tabs\Tab::make('Dokumen & Pengusul')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Identitas Dokumen RKA')
                                    ->description('Penetapan jadwal portal, unit kerja pengusul, dan konfigurasi institusi.')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('schedule_id')
                                                ->label('Jadwal & Gelombang RKA')
                                                ->relationship('schedule', 'phase_name')
                                                ->getOptionLabelFromRecordUsing(fn (RkaSchedule $record) => "TA {$record->fiscal_year} - {$record->phase_name}")
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->columnSpan(2),

                                            Select::make('setting_id')
                                                ->label('Konfigurasi Institutional')
                                                ->relationship('setting', 'setting_name')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->columnSpan(1),

                                            Select::make('department_id')
                                                ->label('Unit Kerja / Department Pengusul')
                                                ->relationship('department', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->columnSpan(2),

                                            TextInput::make('pagu_budget')
                                                ->label('Pagu Anggaran Unit')
                                                ->prefix('Rp')
                                                ->numeric()
                                                ->default(0.00)
                                                ->required()
                                                ->columnSpan(1)
                                                ->helperText('Batas maksimal alokasi usulan belanja unit.'),
                                        ]),
                                    ]),

                                Section::make('Penanggung Jawab Pengusulan')
                                    ->description('Informasi pengusul (Kepala Unit / Department) dan waktu pengiriman.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('unit_head_user_id')
                                                ->label('Pengusul (Ka. Unit / Dept)')
                                                ->relationship('unitHead', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->default(fn () => Auth::id()),

                                            DateTimePicker::make('submitted_at')
                                                ->label('Waktu Pengiriman (Submit)')
                                                ->seconds(false)
                                                ->native(false)
                                                ->displayFormat('d M Y, H:i')
                                                ->helperText('Otomatis terisi saat status diubah ke SUBMITTED.'),
                                        ]),
                                    ]),
                            ]),

                        // TAB 2: REVIEW & STATUS PERSETUJUAN
                        Tabs\Tab::make('Review & Status')
                            ->icon('heroicon-o-check-badge')
                            ->schema([
                                Section::make('Status Dokumen & Reviewer')
                                    ->description('Atur status persetujuan dokumen dan catat reviewer penanggung jawab.')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('status')
                                                ->label('Status Dokumen RKA')
                                                ->options([
                                                    RkaHeader::STATUS_DRAFT => 'DRAFT (Penyusunan Unit)',
                                                    RkaHeader::STATUS_SUBMITTED => 'SUBMITTED (Diajukan Keuangan)',
                                                    RkaHeader::STATUS_IN_REVIEW => 'IN_REVIEW (Dalam Proses Review)',
                                                    RkaHeader::STATUS_REVISION_REQUIRED => 'REVISION_REQUIRED (Perlu Revisi)',
                                                    RkaHeader::STATUS_APPROVED => 'APPROVED (Disetujui / Disahkan)',
                                                ])
                                                ->required()
                                                ->default(RkaHeader::STATUS_DRAFT)
                                                ->columnSpan(1),

                                            Select::make('reviewer_user_id')
                                                ->label('Reviewer Penanggung Jawab')
                                                ->relationship('reviewer', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->columnSpan(1),

                                            DatePicker::make('review_date')
                                                ->label('Tanggal Review')
                                                ->native(false)
                                                ->displayFormat('d M Y')
                                                ->columnSpan(1),
                                        ]),

                                        Textarea::make('general_notes')
                                            ->label('Catatan Umum Reviewer')
                                            ->placeholder('Masukkan catatan perbaikan, arahan revisi, atau rekomendasi anggaran...')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Kontrol Kunci & Penguncian Portal')
                                    ->description('Pengaturan akses edit unit kerja terhadap dokumen RKA ini.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Toggle::make('is_locked')
                                                ->label('Kunci Dokumen RKA')
                                                ->helperText('Jika aktif, unit kerja tidak dapat merubah item usulan.')
                                                ->default(false)
                                                ->onColor('danger'),

                                            Toggle::make('unlocked_by_admin')
                                                ->label('Bypass Buka Kunci Khusus Admin')
                                                ->helperText('Izinkan unit merubah data meskipun portal jadwal umum sudah ditutup.')
                                                ->default(false)
                                                ->onColor('warning'),
                                        ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}