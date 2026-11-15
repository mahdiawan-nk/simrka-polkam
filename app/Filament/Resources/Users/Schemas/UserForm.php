<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Unique;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        
                        Group::make()
                            ->schema([
                                Section::make('Informasi Pengguna')
                                    ->description('Masukkan data diri dan kredensial login pengguna.')
                                    ->icon('heroicon-m-user')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nama Lengkap')
                                            ->placeholder('Contoh: John Doe')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('email')
                                            ->label('Alamat Email')
                                            ->email()
                                            ->placeholder('nama@domain.com')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true),

                                        TextInput::make('password')
                                            ->label('Kata Sandi')
                                            ->password()
                                            ->revealable()
                                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                                            ->dehydrated(fn (?string $state): bool => filled($state))
                                            ->required(fn (string $operation): bool => $operation === 'create')
                                            ->placeholder(fn (string $operation): string => $operation === 'create' ? 'Masukkan kata sandi' : 'Kosongkan jika tidak diubah')
                                            ->minLength(8),
                                    ])->columns(2),

                                Section::make('Penugasan Departemen')
                                    ->description('Atur unit kerja utama dan penugasan PLT untuk pengguna ini.')
                                    ->icon('heroicon-m-building-office-2')
                                    ->schema([
                                        Repeater::make('userDepartments')
                                            ->relationship('userDepartments')
                                            ->label('Daftar Departemen')
                                            ->schema([
                                                Grid::make(12)
                                                    ->schema([
                                                        Select::make('department_id')
                                                            ->label('Unit Kerja / Departemen')
                                                            ->relationship('department', 'name')
                                                            ->searchable()
                                                            ->preload()
                                                            ->required()
                                                            ->columnSpan(5),

                                                        Select::make('assignment_type')
                                                            ->label('Tipe Penugasan')
                                                            ->options([
                                                                'primary' => 'Utama / Definitif',
                                                                'plt'     => 'PLT (Pelaksana Tugas)',
                                                            ])
                                                            ->default('primary')
                                                            ->required()
                                                            ->live()
                                                            ->columnSpan(3),

                                                        DatePicker::make('start_date')
                                                            ->label('Tgl Mulai')
                                                            ->visible(fn ($get) => $get('assignment_type') === 'plt')
                                                            ->columnSpan(2),

                                                        DatePicker::make('end_date')
                                                            ->label('Tgl Selesai')
                                                            ->visible(fn ($get) => $get('assignment_type') === 'plt')
                                                            ->columnSpan(2),
                                                    ]),
                                            ])
                                            ->defaultItems(1)
                                            ->addActionLabel('Tambah Penugasan Departemen')
                                            ->reorderable(false)
                                            ->collapsible(),
                                    ]),
                            ])
                            ->columnSpan(2),

                        Group::make()
                            ->schema([
                                Section::make('Status & Akses')
                                    ->icon('heroicon-m-shield-check')
                                    ->schema([
                                        DateTimePicker::make('email_verified_at')
                                            ->label('Waktu Verifikasi Email')
                                            ->nullable()
                                            ->helperText('Kosongkan jika belum diverifikasi.'),

                                        Select::make('roles')
                                            ->label('Peran / Role')
                                            ->relationship('roles', 'name')
                                            ->multiple()
                                            ->preload()
                                            ->searchable()
                                            ->placeholder('Pilih role akses...'),
                                    ]),
                            ])
                            ->columnSpan(1),

                    ]),
            ])
            ->columns(1);
    }
}