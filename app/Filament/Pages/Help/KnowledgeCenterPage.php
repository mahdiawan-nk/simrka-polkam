<?php

namespace App\Filament\Pages\Help;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleFeedback;
use App\Services\Knowledge\KnowledgeCenterService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use UnitEnum;

class KnowledgeCenterPage extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Pusat Bantuan';

    protected static ?string $navigationLabel = 'Pusat Panduan';

    protected static ?string $title = 'Pusat Panduan e-RKA';

    protected static ?string $slug = 'pusat-panduan';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.help.knowledge-center-page';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'kategori', except: '')]
    public string $selectedCategory = '';

    #[Url(as: 'modul', except: '')]
    public string $selectedModule = '';

    #[Url(as: 'artikel', except: '')]
    public string $articleSlug = '';

    public function mount(KnowledgeCenterService $service): void
    {
        if ($this->articleSlug !== '') {
            $article = $service->findAccessibleArticleBySlug(Auth::user(), $this->articleSlug);
            if (! $article) {
                $existsInSystem = KnowledgeArticle::query()->where('slug', $this->articleSlug)->exists();
                abort($existsInSystem ? 403 : 404);
            }
        }
    }

    public function openArticle(string $slug, KnowledgeCenterService $service): void
    {
        $article = $service->findAccessibleArticleBySlug(Auth::user(), $slug);
        if (! $article) {
            abort(403, 'Anda tidak memiliki akses ke panduan ini.');
        }

        $this->articleSlug = $slug;
    }

    public function backToList(): void
    {
        $this->articleSlug = '';
    }

    public function selectCategory(string $slug): void
    {
        $this->selectedCategory = $this->selectedCategory === $slug ? '' : $slug;
        $this->articleSlug = '';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->selectedCategory = '';
        $this->selectedModule = '';
        $this->articleSlug = '';
    }

    public function submitFeedback(int $articleId, bool $isHelpful, KnowledgeCenterService $service): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $article = $service->accessibleArticlesQuery($user)
            ->where('knowledge_articles.id', $articleId)
            ->first();

        abort_unless($article !== null, 403);

        KnowledgeArticleFeedback::query()->updateOrCreate(
            [
                'knowledge_article_id' => $article->id,
                'user_id' => $user->id,
            ],
            [
                'is_helpful' => $isHelpful,
            ]
        );

        Notification::make()
            ->success()
            ->title('Terima kasih atas masukan Anda')
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $service = app(KnowledgeCenterService::class);
        $user = Auth::user();

        $categories = $service->getAccessibleCategories($user);
        $activeRoleLabel = $service->getActiveRoleLabel($user);

        if ($this->articleSlug !== '') {
            $activeArticle = $service->findAccessibleArticleBySlug($user, $this->articleSlug);

            if (! $activeArticle) {
                abort(403, 'Anda tidak memiliki akses ke panduan ini.');
            }

            $prepared = $service->prepareArticleContent($activeArticle->content);

            $siblingList = $service->accessibleArticlesQuery($user)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'title', 'slug', 'category_id']);

            $currentIndex = $siblingList->search(fn (KnowledgeArticle $item): bool => $item->id === $activeArticle->id);
            $previousArticle = ($currentIndex !== false && $currentIndex > 0) ? $siblingList->get($currentIndex - 1) : null;
            $nextArticle = ($currentIndex !== false && $currentIndex < $siblingList->count() - 1) ? $siblingList->get($currentIndex + 1) : null;

            $relatedArticles = $service->accessibleArticlesQuery($user)
                ->where('id', '!=', $activeArticle->id)
                ->where('category_id', $activeArticle->category_id)
                ->orderBy('sort_order')
                ->limit(3)
                ->get();

            $userFeedback = $user
                ? KnowledgeArticleFeedback::query()
                    ->where('knowledge_article_id', $activeArticle->id)
                    ->where('user_id', $user->id)
                    ->first()
                : null;

            return [
                'mode' => 'detail',
                'activeRoleLabel' => $activeRoleLabel,
                'canDownloadComplete' => (bool) ($user?->hasRole('super_admin')),
                'categories' => $categories,
                'article' => $activeArticle,
                'renderedHtml' => $prepared['html'],
                'toc' => $prepared['toc'],
                'previousArticle' => $previousArticle,
                'nextArticle' => $nextArticle,
                'relatedArticles' => $relatedArticles,
                'userFeedback' => $userFeedback,
            ];
        }

        $query = $service->accessibleArticlesQuery($user);

        if ($this->selectedCategory !== '') {
            $query->whereHas('category', fn (Builder $q): Builder => $q->where('slug', $this->selectedCategory));
        }

        if ($this->selectedModule !== '') {
            $query->where('module_key', $this->selectedModule);
        }

        $term = trim($this->search);
        if ($term !== '') {
            $query->where(function (Builder $q) use ($term): void {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('excerpt', 'like', "%{$term}%")
                    ->orWhere('content', 'like', "%{$term}%");
            });
        }

        $articles = $query
            ->orderByDesc('featured')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $featuredArticles = ($term === '' && $this->selectedCategory === '' && $this->selectedModule === '')
            ? $articles->where('featured', true)->take(4)->values()
            : new Collection;

        $moduleOptions = $service->accessibleArticlesQuery($user)
            ->whereNotNull('module_key')
            ->distinct()
            ->pluck('module_key')
            ->mapWithKeys(fn (string $key): array => [$key => $this->formatModuleLabel($key)])
            ->all();

        return [
            'mode' => 'index',
            'activeRoleLabel' => $activeRoleLabel,
            'canDownloadComplete' => (bool) ($user?->hasRole('super_admin')),
            'totalAccessibleCount' => $service->accessibleArticlesQuery($user)->count(),
            'categories' => $categories,
            'articles' => $articles,
            'featuredArticles' => $featuredArticles,
            'moduleOptions' => $moduleOptions,
        ];
    }

    public function formatModuleLabel(string $moduleKey): string
    {
        return match ($moduleKey) {
            'umum' => 'Panduan Umum',
            'dashboard' => 'Dasbor',
            'rka' => 'Manajemen RKA',
            'fund_request' => 'Pengajuan Pencairan Dana',
            'budget' => 'Manajemen Anggaran',
            'master_data' => 'Master Data',
            'access' => 'Pengaturan Akses',
            'system' => 'Administrasi Sistem',
            default => ucfirst($moduleKey),
        };
    }
}
