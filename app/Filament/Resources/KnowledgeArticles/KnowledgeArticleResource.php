<?php

namespace App\Filament\Resources\KnowledgeArticles;

use App\Filament\Resources\KnowledgeArticles\Pages\CreateKnowledgeArticle;
use App\Filament\Resources\KnowledgeArticles\Pages\EditKnowledgeArticle;
use App\Filament\Resources\KnowledgeArticles\Pages\ListKnowledgeArticles;
use App\Models\KnowledgeArticle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UnitEnum;

class KnowledgeArticleResource extends Resource
{
    protected static ?string $model = KnowledgeArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Pusat Bantuan';

    protected static ?string $navigationLabel = 'Kelola Artikel Panduan';

    protected static ?string $modelLabel = 'Artikel Panduan';

    protected static ?string $pluralModelLabel = 'Daftar Artikel Panduan';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Utama Artikel')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('title')
                            ->label('Judul Panduan')
                            ->placeholder('Contoh: Cara Mengajukan Pencairan Dana dari RKA Approved')
                            ->required()
                            ->maxLength(200)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $set, ?KnowledgeArticle $record): void {
                                if (! $record && $state) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Slug URL')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(220),

                        Select::make('category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('roles')
                            ->label('Target Peran (Role)')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Kosongkan agar panduan dapat dibaca oleh seluruh peran (panduan umum).'),
                    ]),

                    Textarea::make('excerpt')
                        ->label('Ringkasan Singkat')
                        ->placeholder('Penjelasan singkat isi panduan untuk kartu daftar artikel')
                        ->rows(2)
                        ->maxLength(500),

                    RichEditor::make('content')
                        ->label('Isi Panduan')
                        ->required()
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('knowledge-center/images')
                        ->columnSpanFull(),
                ]),

            Section::make('Diagram Alur Kerja (Workflow)')
                ->collapsed()
                ->description('Opsional: Tambahkan tahapan alur kerja untuk ditampilkan sebagai diagram visual.')
                ->schema([
                    Repeater::make('workflow_steps')
                        ->label('Tahapan Alur Kerja')
                        ->defaultItems(0)
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('title')
                                    ->label('Nama Tahap')
                                    ->placeholder('Contoh: Pengusulan RKA')
                                    ->required(),
                                TextInput::make('actor')
                                    ->label('Pelaku / Peran')
                                    ->placeholder('Contoh: Pemohon / Unit'),
                                TextInput::make('note')
                                    ->label('Keterangan Singkat')
                                    ->placeholder('Contoh: Status Draft -> Diajukan'),
                            ]),
                        ])
                        ->addActionLabel('Tambah Tahap Alur')
                        ->collapsible(),
                ]),

            Section::make('Langkah Visual, Screenshot, & Anotasi')
                ->collapsed()
                ->description('Tambahkan langkah-langkah bergambar menggunakan screenshot aktual aplikasi dan anotasi penunjuk.')
                ->schema([
                    Repeater::make('steps')
                        ->label('Daftar Langkah Visual')
                        ->defaultItems(0)
                        ->relationship('steps')
                        ->orderColumn('sort_order')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['step_title'] ?? 'Langkah Visual')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('step_title')
                                    ->label('Judul Langkah')
                                    ->placeholder('Contoh: Mengisi Rincian Item Belanja RKA')
                                    ->required()
                                    ->maxLength(200),

                                TextInput::make('image_caption')
                                    ->label('Caption Gambar')
                                    ->placeholder('Contoh: Tampilan form pengisian item belanja pada RKA')
                                    ->maxLength(255),
                            ]),

                            Textarea::make('step_description')
                                ->label('Penjelasan Singkat')
                                ->rows(2),

                            Grid::make(2)->schema([
                                FileUpload::make('image_path')
                                    ->label('Screenshot Aplikasi')
                                    ->helperText('Format: PNG, JPG, atau WebP (maks. 4 MB).')
                                    ->disk('public')
                                    ->directory('knowledge-center/screenshots')
                                    ->visibility('public')
                                    ->image()
                                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                    ->maxSize(4096)
                                    ->imagePreviewHeight('140'),

                                TextInput::make('image_alt')
                                    ->label('Teks Alternatif (Alt Text)')
                                    ->placeholder('Deskripsi gambar untuk aksesibilitas')
                                    ->maxLength(200),
                            ]),

                            Grid::make(2)->schema([
                                Textarea::make('action_instruction')
                                    ->label('Instruksi Tindakan')
                                    ->placeholder('Jelaskan tombol atau kolom yang harus diisi pengguna')
                                    ->rows(2),

                                Textarea::make('expected_result')
                                    ->label('Hasil yang Diharapkan')
                                    ->placeholder('Jelaskan perubahan status atau tampilan setelah langkah selesai')
                                    ->rows(2),
                            ]),

                            Repeater::make('annotations')
                                ->label('Anotasi Visual pada Screenshot')
                                ->defaultItems(0)
                                ->helperText('Posisi X dan Y dalam persen (0–100) dari kiri atas gambar.')
                                ->schema([
                                    Grid::make(6)->schema([
                                        TextInput::make('number')
                                            ->label('No.')
                                            ->default('1')
                                            ->required(),
                                        TextInput::make('x')
                                            ->label('Posisi X (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->default(20)
                                            ->required(),
                                        TextInput::make('y')
                                            ->label('Posisi Y (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->default(20)
                                            ->required(),
                                        TextInput::make('highlight_w')
                                            ->label('Lebar Kotak (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->placeholder('Opsional'),
                                        TextInput::make('highlight_h')
                                            ->label('Tinggi Kotak (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->placeholder('Opsional'),
                                        TextInput::make('label')
                                            ->label('Keterangan')
                                            ->placeholder('Contoh: Klik tombol Ajukan')
                                            ->required(),
                                    ]),
                                ])
                                ->addActionLabel('Tambah Anotasi')
                                ->collapsed(),
                        ])
                        ->addActionLabel('Tambah Langkah Visual'),
                ]),

            Section::make('Publikasi, Lampiran, & Pemeliharaan')
                ->collapsed()
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('status')
                            ->label('Status Publikasi')
                            ->options([
                                KnowledgeArticle::STATUS_DRAFT => 'Draft',
                                KnowledgeArticle::STATUS_PUBLISHED => 'Diterbitkan (Published)',
                                KnowledgeArticle::STATUS_ARCHIVED => 'Diarsipkan (Archived)',
                            ])
                            ->default(KnowledgeArticle::STATUS_DRAFT)
                            ->required(),

                        TextInput::make('reading_time')
                            ->label('Estimasi Waktu Baca (Menit)')
                            ->numeric()
                            ->default(3)
                            ->minValue(1)
                            ->required(),

                        TextInput::make('sort_order')
                            ->label('Urutan Tampil')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Toggle::make('featured')
                            ->label('Artikel Penting / Unggulan')
                            ->default(false),

                        DateTimePicker::make('published_at')
                            ->label('Tanggal Terbit'),

                        DateTimePicker::make('last_verified_at')
                            ->label('Terakhir Diverifikasi'),
                    ]),

                    Grid::make(3)->schema([
                        Select::make('module_key')
                            ->label('Modul Terkait')
                            ->options([
                                'umum' => 'Panduan Umum',
                                'dashboard' => 'Dasbor',
                                'rka' => 'Manajemen RKA',
                                'fund_request' => 'Pengajuan Pencairan Dana',
                                'budget' => 'Manajemen Anggaran',
                                'master_data' => 'Master Data',
                                'access' => 'Pengaturan Akses',
                                'system' => 'Administrasi Sistem',
                            ])
                            ->searchable(),

                        TextInput::make('doc_version')
                            ->label('Versi Dokumentasi')
                            ->default('1.0')
                            ->maxLength(20),

                        TextInput::make('maintainer_name')
                            ->label('Penanggung Jawab')
                            ->default('Tim UPT ICT')
                            ->maxLength(120),
                    ]),

                    TextInput::make('resource_fqcn')
                        ->label('Class Resource / Page Terkait (Opsional)')
                        ->placeholder('Contoh: App\\Filament\\Resources\\FundRequests\\FundRequestResource')
                        ->maxLength(200),

                    FileUpload::make('attachments')
                        ->label('Lampiran Tambahan (PDF / Gambar)')
                        ->multiple()
                        ->disk('public')
                        ->directory('knowledge-center/attachments')
                        ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
                        ->maxSize(5120)
                        ->downloadable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('Judul Panduan')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('semibold'),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Target Peran')
                    ->badge()
                    ->placeholder('Semua Peran')
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        KnowledgeArticle::STATUS_PUBLISHED => 'success',
                        KnowledgeArticle::STATUS_DRAFT => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        KnowledgeArticle::STATUS_PUBLISHED => 'Diterbitkan',
                        KnowledgeArticle::STATUS_DRAFT => 'Draft',
                        KnowledgeArticle::STATUS_ARCHIVED => 'Diarsipkan',
                        default => $state,
                    }),

                IconColumn::make('featured')
                    ->label('Unggulan')
                    ->boolean(),

                TextColumn::make('last_verified_at')
                    ->label('Diverifikasi')
                    ->date('d M Y')
                    ->placeholder('Belum')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        KnowledgeArticle::STATUS_DRAFT => 'Draft',
                        KnowledgeArticle::STATUS_PUBLISHED => 'Diterbitkan',
                        KnowledgeArticle::STATUS_ARCHIVED => 'Diarsipkan',
                    ]),
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label('Terbitkan')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (KnowledgeArticle $record): bool => $record->status !== KnowledgeArticle::STATUS_PUBLISHED
                        && (Auth::user()?->can('publish', $record) ?? false))
                    ->action(function (KnowledgeArticle $record): void {
                        $record->update([
                            'status' => KnowledgeArticle::STATUS_PUBLISHED,
                            'published_at' => $record->published_at ?? now(),
                            'updated_by' => Auth::id(),
                        ]);
                    }),
                EditAction::make()->label('Ubah'),
                DeleteAction::make()->label('Hapus'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKnowledgeArticles::route('/'),
            'create' => CreateKnowledgeArticle::route('/create'),
            'edit' => EditKnowledgeArticle::route('/{record}/edit'),
        ];
    }
}
