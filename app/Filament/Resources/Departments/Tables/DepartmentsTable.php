<?php

namespace App\Filament\Resources\Departments\Tables;

use App\Models\Department;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextInputColumn;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Matikan pengurutan default dari UI Filament
            ->defaultSort(null)
            ->modifyQueryUsing(function (Builder $query) {
                // Eager load parent untuk menghindari N+1 query
                $query->with('parent');

                // Ambil semua data terelasi untuk diproses di memori
                $allDepartments = Department::with('parent')->get();

                // Susun urutan ID secara Depth-First Search (Parent -> Child -> Grandchild)
                $orderedTree = static::buildTreeData($allDepartments);
                $orderedIds = array_column($orderedTree, 'id');

                if (!empty($orderedIds)) {
                    $idsString = implode(',', $orderedIds);
                    // Gunakan FIELD() untuk mengunci urutan hasil query
                    $query->orderByRaw("FIELD(id, {$idsString})");
                }
            })
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->color('primary')
                    ->copyable()
                    ->searchable(),

                // TextInputColumn::make('level_code')
                //     ->label('Kode Level')
                //     ->searchable()
                //     ->toggleable(),

                // Kolom Nama Departemen dengan Indentasi & Simbol Tree
                TextColumn::make('name')
                    ->label('Nama Departemen')
                    ->searchable()
                    ->formatStateUsing(function (string $state, Department $record): string {
                        // Menghitung kedalaman (depth) riil dari database
                        $depth = 0;
                        $current = $record;

                        while ($current->parent) {
                            $depth++;
                            $current = $current->parent;
                        }

                        if ($depth === 0) {
                            return '<strong>' . e($state) . '</strong>';
                        }

                        // Menggunakan spasi non-breaking dan karakter branch '└─ '
                        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
                        return $indent . '<span class="text-gray-400">└─ </span>' . e($state);
                    })
                    ->html()
                    ->wrap(),

                TextColumn::make('parent.name')
                    ->label('Induk Departemen')
                    ->placeholder('- Utama/Root -')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'direktorat' => 'Institusi / Direktorat',
                        'bidang'     => 'Bidang',
                        'badan'      => 'Badan / Pusat',
                        'bagian'     => 'Bagian',
                        'prodi'      => 'Prodi',
                        'upt'        => 'UPT / Lab',
                        default      => $state ? ucfirst($state) : '-',
                    })
                    ->color(fn(?string $state): string => match ($state) {
                        'direktorat' => 'danger',
                        'bidang'     => 'warning',
                        'badan'      => 'info',
                        'bagian'     => 'primary',
                        'prodi'      => 'success',
                        'upt'        => 'gray',
                        default      => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipe Unit')
                    ->options([
                        'direktorat' => 'Institusi / Direktorat',
                        'bidang'     => 'Bidang',
                        'badan'      => 'Badan / Pusat',
                        'bagian'     => 'Bagian',
                        'prodi'      => 'Prodi',
                        'upt'        => 'UPT / Lab',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Algoritma Rekursif Depth-First Search (DFS)
     * Memastikan Anak (Children) selalu disisipkan tepat di bawah Induknya (Parent)
     */
    protected static function buildTreeData($departments, $parentId = null): array
    {
        $result = [];
        $children = $departments->where('parent_id', $parentId);

        foreach ($children as $child) {
            $result[] = [
                'id' => $child->id,
                'name' => $child->name,
            ];

            // Panggilan rekursif untuk mengambil anak dari $child secara mendalam
            $subChildren = static::buildTreeData($departments, $child->id);
            if (!empty($subChildren)) {
                $result = array_merge($result, $subChildren);
            }
        }

        return $result;
    }
}
