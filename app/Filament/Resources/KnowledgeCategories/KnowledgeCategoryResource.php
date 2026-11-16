<?php

namespace App\Filament\Resources\KnowledgeCategories;

use App\Filament\Resources\KnowledgeCategories\Pages\CreateKnowledgeCategory;
use App\Filament\Resources\KnowledgeCategories\Pages\EditKnowledgeCategory;
use App\Filament\Resources\KnowledgeCategories\Pages\ListKnowledgeCategories;
use App\Models\KnowledgeCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class KnowledgeCategoryResource extends Resource
{
    protected static ?string $model = KnowledgeCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Pusat Bantuan';

    protected static ?string $navigationLabel = 'Kategori Panduan';

    protected static ?string $modelLabel = 'Kategori Panduan';

    protected static ?string $pluralModelLabel = 'Kategori Panduan';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Kategori')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label('Nama Kategori')
                            ->placeholder('Contoh: Manajemen RKA')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $set, ?KnowledgeCategory $record): void {
                                if (! $record && $state) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Slug URL')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(140),

                        TextInput::make('icon')
                            ->label('Nama Ikon (Heroicon)')
                            ->placeholder('Contoh: heroicon-o-book-open')
                            ->maxLength(80),

                        TextInput::make('sort_order')
                            ->label('Urutan Tampil')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ]),

                    Textarea::make('description')
                        ->label('Deskripsi Singkat')
                        ->rows(2)
                        ->maxLength(500),

                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->color('gray'),
                TextColumn::make('articles_count')
                    ->label('Jumlah Artikel')
                    ->counts('articles')
                    ->badge()
                    ->color('info'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                DeleteAction::make()->label('Hapus'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKnowledgeCategories::route('/'),
            'create' => CreateKnowledgeCategory::route('/create'),
            'edit' => EditKnowledgeCategory::route('/{record}/edit'),
        ];
    }
}
