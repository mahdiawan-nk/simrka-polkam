<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Services\Branding\BrandSettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KnowledgeBookPdfService
{
    public function __construct(
        private KnowledgeCenterService $knowledgeService,
        private BrandSettingsService $brandService,
    ) {}

    /**
     * Generate PDF handbook scoped strictly to the user's active role.
     *
     * @return array{pdf: DomPdfInstance, filename: string, article_count: int}
     */
    public function generateForCurrentRole(User $user): array
    {
        if (! $user->can('View:KnowledgeCenterPage')) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengunduh buku panduan.');
        }

        $roleLabel = $this->knowledgeService->getActiveRoleLabel($user);
        $articles = $this->knowledgeService->accessibleArticlesQuery($user)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $brand = $this->buildDocumentMetadata(
            title: 'BUKU PANDUAN PENGGUNA',
            subtitle: "Panduan Operasional Peran: {$roleLabel}",
            scopeLabel: $roleLabel,
            isComplete: false,
            articles: $articles,
        );

        $pdf = $this->renderPdf($brand, $this->groupArticlesByCategory($articles));

        $roleSlug = Str::slug($roleLabel) ?: 'pengguna';
        $brandSlug = Str::slug($this->brandService->getBrandName()) ?: 'e-rka';

        return [
            'pdf' => $pdf,
            'filename' => "Buku-Panduan-{$roleSlug}-{$brandSlug}.pdf",
            'article_count' => $articles->count(),
        ];
    }

    /**
     * Generate complete system handbook (Super Admin only).
     *
     * @return array{pdf: DomPdfInstance, filename: string, article_count: int}
     */
    public function generateCompleteHandbook(User $user): array
    {
        if (! $user->hasRole('super_admin') || ! $user->can('View:KnowledgeCenterPage')) {
            throw new AuthorizationException('Hanya Super Admin yang berhak mengunduh Buku Panduan Lengkap.');
        }

        $articles = KnowledgeArticle::query()
            ->with(['category:id,name,slug,description,sort_order', 'roles:id,name', 'steps'])
            ->published()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $brand = $this->buildDocumentMetadata(
            title: 'BUKU PANDUAN LENGKAP SISTEM',
            subtitle: 'Dokumentasi Operasional & Administrasi Seluruh Modul dan Peran',
            scopeLabel: 'Seluruh Modul & Peran Sistem',
            isComplete: true,
            articles: $articles,
        );

        $pdf = $this->renderPdf($brand, $this->groupArticlesByCategory($articles));
        $brandSlug = Str::slug($this->brandService->getBrandName()) ?: 'e-rka';

        return [
            'pdf' => $pdf,
            'filename' => "Buku-Panduan-Lengkap-{$brandSlug}.pdf",
            'article_count' => $articles->count(),
        ];
    }

    /**
     * Generate PDF for a single accessible article.
     *
     * @return array{pdf: DomPdfInstance, filename: string}
     */
    public function generateSingleArticle(User $user, string $slug): array
    {
        if (! $user->can('View:KnowledgeCenterPage')) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengunduh panduan.');
        }

        $article = $this->knowledgeService->findAccessibleArticleBySlug($user, $slug);
        if (! $article) {
            throw new AuthorizationException('Anda tidak memiliki akses ke panduan ini.');
        }

        $articles = new Collection([$article]);
        $roleLabel = $this->knowledgeService->getActiveRoleLabel($user);

        $brand = $this->buildDocumentMetadata(
            title: 'PANDUAN PENGGUNAAN FITUR',
            subtitle: $article->title,
            scopeLabel: $roleLabel,
            isComplete: false,
            articles: $articles,
        );

        $pdf = $this->renderPdf($brand, $this->groupArticlesByCategory($articles));
        $articleSlug = Str::slug($article->slug) ?: 'panduan';

        return [
            'pdf' => $pdf,
            'filename' => "Panduan-{$articleSlug}.pdf",
        ];
    }

    /**
     * @param  Collection<int, KnowledgeArticle>  $articles
     * @return Collection<int, array{category_name: string, category_description: ?string, articles: Collection<int, KnowledgeArticle>}>
     */
    public function groupArticlesByCategory(Collection $articles): Collection
    {
        return $articles
            ->groupBy(fn (KnowledgeArticle $article): int => (int) $article->category_id)
            ->sortBy(fn (Collection $group): int => (int) ($group->first()?->category?->sort_order ?? 999))
            ->values()
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'category_name' => (string) ($first?->category?->name ?? 'Panduan Umum'),
                    'category_description' => $first?->category?->description,
                    'articles' => $group->values(),
                ];
            });
    }

    /**
     * @param  Collection<int, KnowledgeArticle>  $articles
     * @return array<string, mixed>
     */
    protected function buildDocumentMetadata(
        string $title,
        string $subtitle,
        string $scopeLabel,
        bool $isComplete,
        Collection $articles,
    ): array {
        $settings = $this->brandService->getSettings();
        $latestVersion = $articles->pluck('doc_version')->filter()->first() ?: '1.0';

        return [
            'doc_title' => $title,
            'doc_subtitle' => $subtitle,
            'scope_label' => $scopeLabel,
            'is_complete' => $isComplete,
            'brand_name' => (string) $settings['brand_name'],
            'institution_name' => (string) $settings['institution_name'],
            'institution_short_name' => (string) $settings['institution_short_name'],
            'app_full_name' => (string) $settings['app_full_name'],
            'tagline' => (string) $settings['tagline'],
            'logo_data_uri' => $this->resolveLogoDataUri($settings),
            'doc_version' => $latestVersion,
            'published_date' => now()->translatedFormat('d F Y'),
            'article_count' => $articles->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  Collection<int, array<string, mixed>>  $chapters
     */
    protected function renderPdf(array $meta, Collection $chapters): DomPdfInstance
    {
        $stepImages = [];
        $figures = [];
        $figureNumber = 0;

        foreach ($chapters as $chapter) {
            foreach ($chapter['articles'] as $article) {
                foreach ($article->steps as $step) {
                    $dataUri = $this->resolveStorageImageDataUri($step->image_path);
                    if ($dataUri !== null) {
                        $figureNumber++;
                        $stepImages[$step->id] = [
                            'data_uri' => $dataUri,
                            'figure_number' => $figureNumber,
                        ];
                        $figures[] = [
                            'number' => $figureNumber,
                            'caption' => $step->image_caption ?: $step->step_title,
                            'article_title' => $article->title,
                        ];
                    }
                }
            }
        }

        $pdf = Pdf::loadView('pdf.knowledge-handbook', [
            'meta' => $meta,
            'chapters' => $chapters,
            'stepImages' => $stepImages,
            'figures' => $figures,
        ])->setPaper('a4', 'portrait');

        $dompdf = $pdf->getDomPDF();
        $dompdf->set_option('isPhpEnabled', true);

        return $pdf;
    }

    protected function resolveStorageImageDataUri(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $disk = Storage::disk(BrandSettingsService::STORAGE_DISK);
        if (! $disk->exists($path)) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => null,
        };

        if ($mime === null) {
            return null;
        }

        $bytes = $disk->get($path);

        return ($bytes !== null && $bytes !== '')
            ? 'data:'.$mime.';base64,'.base64_encode($bytes)
            : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function resolveLogoDataUri(array $settings): ?string
    {
        $candidates = [
            $settings['document_logo_path'] ?? null,
            $settings['logo_light_path'] ?? null,
            $settings['logo_path'] ?? null,
        ];

        $disk = Storage::disk(BrandSettingsService::STORAGE_DISK);

        foreach ($candidates as $path) {
            if (! is_string($path) || $path === '' || ! $disk->exists($path)) {
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                default => null,
            };

            if ($mime === null) {
                continue;
            }

            $bytes = $disk->get($path);
            if ($bytes !== null && $bytes !== '') {
                return 'data:'.$mime.';base64,'.base64_encode($bytes);
            }
        }

        return null;
    }
}
