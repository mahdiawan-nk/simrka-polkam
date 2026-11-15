<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama User')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-envelope')
                    ->copyable()
                    ->copyMessage('Alamat email berhasil disalin'),

                // Menampilkan List Roles (Spatie Permission / BelongsToMany)
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->colors([
                        'danger' => 'super_admin',
                        'warning' => 'admin',
                        'success' => 'user',
                    ])
                    ->formatStateUsing(fn(string $state): string => ucwords(str_replace('_', ' ', $state)))
                    ->separator(', ')
                    ->searchable(),

                // Menampilkan List Departments (BelongsTo / BelongsToMany)
                TextColumn::make('departments.name')
                    ->label('Departemen')
                    ->badge()
                    ->color('gray')
                    ->separator(', ')
                    ->placeholder('Tidak ada departemen')
                    ->searchable(),

                TextColumn::make('email_verified_at')
                    ->label('Verifikasi Email')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('roles')
                    ->label('Filter Role')
                    ->relationship('roles', 'name')
                    ->preload()
                    ->multiple(),

                SelectFilter::make('departments')
                    ->label('Filter Departemen')
                    ->relationship('departments', 'name')
                    ->preload()
                    ->multiple(),
            ])
            ->recordActions([
                // ACTION SWITCH ACCOUNT / IMPERSONATE
                Action::make('switch_account')
                    ->label('Switch Account')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Switch / Login Sebagai User')
                    ->modalDescription(fn(User $record): string => "Apakah Anda yakin ingin login sebagai '{$record->name}' ({$record->email})?")
                    ->modalSubmitActionLabel('Ya, Switch Account')
                    // Sembunyikan action jika user target adalah diri sendiri
                    ->hidden(fn(User $record): bool => $record->id === Auth::id())
                    ->action(function (User $record) {
                        // 1. Simpan ID admin asli ke dalam session (opsional: agar nanti bisa 'Leave Impersonation')
                        session()->put('impersonator_id', Auth::id());

                        // 2. Auto login sebagai user target
                        Auth::login($record);

                        // 3. Regenerate session ID untuk keamanan
                        session()->regenerate();

                        Notification::make()
                            ->title('Berhasil Switch Account')
                            ->body("Anda sekarang login sebagai {$record->name}.")
                            ->success()
                            ->send();

                        // 4. Redirect ke dashboard atau halaman utama aplikasi
                        return redirect()->to(filament()->getUrl());
                    }),

                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
