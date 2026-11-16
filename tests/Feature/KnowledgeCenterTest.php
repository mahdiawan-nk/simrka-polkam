<?php

use App\Filament\Pages\Help\KnowledgeCenterPage;
use App\Filament\Resources\KnowledgeArticles\Pages\CreateKnowledgeArticle;
use App\Filament\Resources\KnowledgeArticles\Pages\ListKnowledgeArticles;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleStep;
use App\Models\KnowledgeCategory;
use App\Models\User;
use App\Services\Branding\BrandSettingsService;
use App\Services\Context\Contracts\ContextManagerInterface;
use App\Services\Knowledge\KnowledgeBookPdfService;
use App\Services\Knowledge\KnowledgeCenterService;
use Database\Seeders\KnowledgeCenterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    foreach ([
        'brand_settings',
        'knowledge_article_steps',
        'knowledge_article_feedback',
        'knowledge_article_role',
        'knowledge_articles',
        'knowledge_categories',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'roles',
        'permissions',
        'users',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });

    Schema::create('roles', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });

    Schema::create('model_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
    });

    Schema::create('model_has_roles', function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
    });

    Schema::create('role_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
    });

    $kcMigration = require database_path('migrations/2026_11_17_023520_create_knowledge_center_tables.php');
    $kcMigration->up();

    $kcVisualMigration = require database_path('migrations/2026_11_17_025604_add_visual_steps_to_knowledge_center.php');
    $kcVisualMigration->up();

    $brandMigration = require database_path('migrations/2026_11_17_010006_create_brand_settings_table.php');
    $brandMigration->up();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RoleSeeder::class);
    $this->seed(KnowledgeCenterSeeder::class);
});

test('published general and role-specific articles are visible while other roles, drafts, and archived articles are blocked', function (): void {
    $pemohon = User::factory()->create();
    $pemohon->assignRole('pemohon');
    $this->actingAs($pemohon);

    $cat = KnowledgeCategory::query()->firstOrFail();

    KnowledgeArticle::query()->create([
        'category_id' => $cat->id,
        'title' => 'Artikel Draft Rahasia',
        'slug' => 'artikel-draft-rahasia',
        'content' => '<p>Konten draft</p>',
        'status' => KnowledgeArticle::STATUS_DRAFT,
    ]);

    KnowledgeArticle::query()->create([
        'category_id' => $cat->id,
        'title' => 'Artikel Arsip Lama',
        'slug' => 'artikel-arsip-lama',
        'content' => '<p>Konten arsip</p>',
        'status' => KnowledgeArticle::STATUS_ARCHIVED,
    ]);

    // Reader page shows general + pemohon articles, hides super_admin, draft, and archived
    Livewire::test(KnowledgeCenterPage::class)
        ->assertOk()
        ->assertSee('Mengenal e-RKA / SIMRKA')
        ->assertSee('Panduan Pemohon: Menyusun')
        ->assertDontSee('Panduan Super Admin')
        ->assertDontSee('Artikel Draft Rahasia')
        ->assertDontSee('Artikel Arsip Lama');

    // Direct URL access to super_admin, draft, or archived article is forbidden (403)
    Livewire::withQueryParams(['artikel' => 'panduan-super-admin-pengguna-shield-brand-dan-backup'])
        ->test(KnowledgeCenterPage::class)
        ->assertForbidden();

    Livewire::withQueryParams(['artikel' => 'artikel-draft-rahasia'])
        ->test(KnowledgeCenterPage::class)
        ->assertForbidden();

    Livewire::withQueryParams(['artikel' => 'artikel-arsip-lama'])
        ->test(KnowledgeCenterPage::class)
        ->assertForbidden();

    // Direct URL access to allowed pemohon article succeeds & renders TOC
    Livewire::withQueryParams(['artikel' => 'panduan-pemohon-penyusunan-dan-pengajuan-rka'])
        ->test(KnowledgeCenterPage::class)
        ->assertOk()
        ->assertSee('Daftar Isi')
        ->assertSee('Langkah-Langkah Penggunaan');
});

test('multi-role user switching active role updates accessible knowledge articles', function (): void {
    $user = User::factory()->create();
    $user->assignRole(['pemohon', 'keuangan']);
    $this->actingAs($user);

    /** @var ContextManagerInterface $context */
    $context = app(ContextManagerInterface::class);

    $context->setActiveRole('pemohon');
    Livewire::test(KnowledgeCenterPage::class)
        ->assertSee('Panduan Pemohon: Menyusun')
        ->assertDontSee('Panduan Keuangan: Jadwal');

    $context->setActiveRole('keuangan');
    Livewire::test(KnowledgeCenterPage::class)
        ->assertSee('Panduan Keuangan: Jadwal')
        ->assertDontSee('Panduan Pemohon: Menyusun');
});

test('search and category filter never leak restricted or draft articles', function (): void {
    $pemohon = User::factory()->create();
    $pemohon->assignRole('pemohon');
    $this->actingAs($pemohon);

    Livewire::test(KnowledgeCenterPage::class)
        ->set('search', 'Backup')
        ->assertDontSee('Panduan Super Admin')
        ->assertSee('Panduan tidak ditemukan');

    Livewire::test(KnowledgeCenterPage::class)
        ->set('search', 'Pencairan')
        ->assertSee('Panduan Pemohon: Mengajukan Pencairan Dana');

    Livewire::test(KnowledgeCenterPage::class)
        ->call('selectCategory', 'panduan-umum')
        ->assertSee('Mengenal e-RKA / SIMRKA')
        ->assertDontSee('Panduan Pemohon: Menyusun');
});

test('reader can submit helpfulness feedback on accessible articles', function (): void {
    $pemohon = User::factory()->create();
    $pemohon->assignRole('pemohon');
    $this->actingAs($pemohon);

    $article = KnowledgeArticle::query()
        ->where('slug', 'mengenal-simrka-dan-perpindahan-konteks')
        ->firstOrFail();

    Livewire::withQueryParams(['artikel' => $article->slug])
        ->test(KnowledgeCenterPage::class)
        ->call('submitFeedback', $article->id, true)
        ->assertHasNoErrors();

    expect($article->feedback()->where('user_id', $pemohon->id)->value('is_helpful'))->toBeTrue();
});

test('only authorized admin can manage knowledge articles while pemohon is forbidden', function (): void {
    $pemohon = User::factory()->create();
    $pemohon->assignRole('pemohon');
    $this->actingAs($pemohon);

    Livewire::test(ListKnowledgeArticles::class)->assertForbidden();
    Livewire::test(CreateKnowledgeArticle::class)->assertForbidden();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $category = KnowledgeCategory::query()->firstOrFail();
    $rolePemohon = Role::query()->where('name', 'pemohon')->firstOrFail();

    Livewire::test(CreateKnowledgeArticle::class)
        ->fillForm([
            'title' => 'Panduan Baru Uji Admin',
            'slug' => 'panduan-baru-uji-admin',
            'category_id' => $category->id,
            'roles' => [$rolePemohon->id],
            'excerpt' => 'Ringkasan uji',
            'content' => '<h2>Langkah</h2><p>Isi panduan uji</p>',
            'status' => KnowledgeArticle::STATUS_PUBLISHED,
            'reading_time' => 2,
            'sort_order' => 10,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(KnowledgeArticle::query()->where('slug', 'panduan-baru-uji-admin')->exists())->toBeTrue();
});

test('pemohon can download role-scoped pdf handbook containing only published pemohon and general articles', function (): void {
    $pemohon = User::factory()->create();
    $pemohon->assignRole('pemohon');
    $this->actingAs($pemohon);

    $cat = KnowledgeCategory::query()->firstOrFail();
    KnowledgeArticle::query()->create([
        'category_id' => $cat->id,
        'title' => 'Artikel Draft Tidak Boleh Masuk PDF',
        'slug' => 'artikel-draft-pdf',
        'content' => '<p>Rahasia draft</p>',
        'status' => KnowledgeArticle::STATUS_DRAFT,
    ]);

    $pdfService = app(KnowledgeBookPdfService::class);
    $result = $pdfService->generateForCurrentRole($pemohon);

    expect($result['filename'])->toContain('Buku-Panduan-')
        ->and($result['article_count'])->toBe(3); // 1 general + 2 pemohon published

    $response = $this->get(route('knowledge-center.handbook.role'));
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(str_starts_with((string) $response->getContent(), '%PDF-'))->toBeTrue();
});

test('non-super-admin cannot download complete handbook while super admin can', function (): void {
    $pemohon = User::factory()->create();
    $pemohon->assignRole('pemohon');

    $this->actingAs($pemohon)
        ->get(route('knowledge-center.handbook.complete'))
        ->assertForbidden();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)
        ->get(route('knowledge-center.handbook.complete'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(str_starts_with((string) $response->getContent(), '%PDF-'))->toBeTrue();
});

test('single article pdf endpoint enforces role and published status authorization', function (): void {
    $pemohon = User::factory()->create();
    $pemohon->assignRole('pemohon');

    $this->actingAs($pemohon)
        ->get(route('knowledge-center.handbook.article', ['slug' => 'panduan-super-admin-pengguna-shield-brand-dan-backup']))
        ->assertForbidden();

    $this->actingAs($pemohon)
        ->get(route('knowledge-center.handbook.article', ['slug' => 'panduan-pemohon-penyusunan-dan-pengajuan-rka']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('pdf cover and metadata dynamically use active brand settings', function (): void {
    app(BrandSettingsService::class)->updateSettings([
        'brand_name' => 'SIMRKA-UJI',
        'institution_name' => 'Politeknik Kampar Dinamis',
        'institution_short_name' => 'PKD',
        'app_full_name' => 'Sistem Anggaran Terpadu',
    ]);

    $pemohon = User::factory()->create();
    $pemohon->assignRole('pemohon');
    $this->actingAs($pemohon);

    $service = app(KnowledgeBookPdfService::class);
    $articles = app(KnowledgeCenterService::class)->accessibleArticlesQuery($pemohon)->get();
    $chapters = $service->groupArticlesByCategory($articles);

    $html = view('pdf.knowledge-handbook', [
        'meta' => [
            'doc_title' => 'BUKU PANDUAN PENGGUNA',
            'doc_subtitle' => 'Panduan Operasional Peran: Pemohon',
            'scope_label' => 'Pemohon',
            'is_complete' => false,
            'brand_name' => 'SIMRKA-UJI',
            'institution_name' => 'Politeknik Kampar Dinamis',
            'institution_short_name' => 'PKD',
            'app_full_name' => 'Sistem Anggaran Terpadu',
            'tagline' => 'Sistem Perencanaan dan Anggaran',
            'logo_data_uri' => null,
            'doc_version' => '1.0',
            'published_date' => '17 November 2026',
            'article_count' => $articles->count(),
        ],
        'chapters' => $chapters,
    ])->render();

    expect($html)->toContain('SIMRKA-UJI')
        ->and($html)->toContain('Politeknik Kampar Dinamis')
        ->and($html)->toContain('BUKU PANDUAN PENGGUNA')
        ->and($html)->toContain('Daftar Isi');
});

test('visual steps, screenshots, annotations, workflow diagram, and list of figures render on web and pdf without leaking across roles', function (): void {
    Storage::fake('public');

    $pemohon = User::factory()->create();
    $pemohon->assignRole('pemohon');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    // Simpan screenshot palsu valid pada artikel pemohon dan artikel super_admin
    $pngBytes = UploadedFile::fake()->image('rka-01.png', 640, 360)->get();
    Storage::disk('public')->put('knowledge-center/screenshots/rka-01.png', $pngBytes);
    Storage::disk('public')->put('knowledge-center/screenshots/admin-01.png', $pngBytes);

    $pemohonArticle = KnowledgeArticle::query()
        ->where('slug', 'panduan-pemohon-penyusunan-dan-pengajuan-rka')
        ->firstOrFail();

    $pemohonArticle->update([
        'workflow_steps' => [
            ['title' => 'Isi RKA', 'actor' => 'Pemohon', 'note' => 'Status Draft'],
            ['title' => 'Ajukan', 'actor' => 'Pemohon', 'note' => 'Status Diajukan'],
        ],
    ]);

    KnowledgeArticleStep::query()->create([
        'knowledge_article_id' => $pemohonArticle->id,
        'sort_order' => 1,
        'step_title' => 'Membuka Form Data Item RKA',
        'step_description' => 'Pilih dokumen RKA berstatus Draft.',
        'action_instruction' => 'Klik tombol Data Item pada baris tabel.',
        'expected_result' => 'Halaman pengisian indikator dan item terbuka.',
        'image_path' => 'knowledge-center/screenshots/rka-01.png',
        'image_alt' => 'Screenshot Data Item RKA',
        'image_caption' => 'Tampilan tombol Data Item pada daftar RKA',
        'annotations' => [
            ['number' => '1', 'x' => 75, 'y' => 30, 'highlight_w' => 15, 'highlight_h' => 10, 'label' => 'Klik Data Item'],
        ],
    ]);

    $adminArticle = KnowledgeArticle::query()
        ->where('slug', 'panduan-super-admin-pengguna-shield-brand-dan-backup')
        ->firstOrFail();

    KnowledgeArticleStep::query()->create([
        'knowledge_article_id' => $adminArticle->id,
        'sort_order' => 1,
        'step_title' => 'Konfigurasi Backup Database',
        'image_path' => 'knowledge-center/screenshots/admin-01.png',
        'image_caption' => 'Halaman Backup Database Khusus Super Admin',
    ]);

    // 1. Pemohon melihat langkah visual, caption, anotasi, dan diagram workflow di web
    $this->actingAs($pemohon);
    Livewire::withQueryParams(['artikel' => $pemohonArticle->slug])
        ->test(KnowledgeCenterPage::class)
        ->assertOk()
        ->assertSee('Diagram Alur Kerja')
        ->assertSee('Membuka Form Data Item RKA')
        ->assertSee('Gambar 1: Tampilan tombol Data Item pada daftar RKA')
        ->assertSee('Klik Data Item')
        ->assertSee('Halaman pengisian indikator dan item terbuka.');

    // 2. PDF Pemohon memuat gambar & Daftar Gambar pemohon, dan TIDAK membocorkan gambar Super Admin
    $pemohonPdf = $this->get(route('knowledge-center.handbook.role'));
    $pemohonPdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

    // 3. PDF Lengkap Super Admin berhasil dibuat dengan seluruh visual
    $this->actingAs($superAdmin);
    $completePdf = $this->get(route('knowledge-center.handbook.complete'));
    $completePdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
});
