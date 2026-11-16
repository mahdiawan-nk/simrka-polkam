<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Models\User;
use App\Services\Context\Contracts\ContextManagerInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class KnowledgeCenterService
{
    /**
     * Resolve role IDs used for scoping Knowledge Center articles for the given user.
     * Prioritizes the active role from ContextManager so multi-role users see guides
     * matching their currently selected workspace role.
     *
     * @return list<int>
     */
    public function resolveEffectiveRoleIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        try {
            /** @var ContextManagerInterface $context */
            $context = app(ContextManagerInterface::class);
            $activeRoleModel = $context->getActiveRoleModel();
            if ($activeRoleModel) {
                return [(int) $activeRoleModel->id];
            }
        } catch (\Throwable) {
            // Fallback to user's assigned roles if context manager is unavailable.
        }

        return $user->roles()
            ->pluck('roles.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function getActiveRoleLabel(?User $user): string
    {
        if (! $user) {
            return 'Tamu';
        }

        try {
            /** @var ContextManagerInterface $context */
            $context = app(ContextManagerInterface::class);
            $activeRole = $context->getActiveRole();
            if ($activeRole) {
                return $this->formatRoleName($activeRole);
            }
        } catch (\Throwable) {
            // Ignore and fallback.
        }

        $firstRole = $user->roles()->value('name');

        return $firstRole ? $this->formatRoleName((string) $firstRole) : 'Pengguna';
    }

    public function formatRoleName(string $roleName): string
    {
        return match ($roleName) {
            'super_admin' => 'Super Admin',
            'direktur' => 'Direktur',
            'wadir_keuangan' => 'Wadir II (BAKKU)',
            'keuangan' => 'Keuangan / Ka. BAKKU',
            'staf_keuangan' => 'Staf Keuangan (Read-Only)',
            'pemohon' => 'Pemohon / Unit Kerja',
            default => Str::headline($roleName),
        };
    }

    public function accessibleArticlesQuery(?User $user): Builder
    {
        $roleIds = $this->resolveEffectiveRoleIds($user);

        return KnowledgeArticle::query()
            ->with(['category:id,name,slug,icon', 'roles:id,name', 'steps'])
            ->published()
            ->accessibleByRoleIds($roleIds);
    }

    public function findAccessibleArticleBySlug(?User $user, string $slug): ?KnowledgeArticle
    {
        return $this->accessibleArticlesQuery($user)
            ->where('slug', $slug)
            ->first();
    }

    public function getAccessibleCategories(?User $user): Collection
    {
        $roleIds = $this->resolveEffectiveRoleIds($user);

        return KnowledgeCategory::query()
            ->where('is_active', true)
            ->whereHas('articles', function (Builder $q) use ($roleIds): void {
                $q->where('status', KnowledgeArticle::STATUS_PUBLISHED)
                    ->accessibleByRoleIds($roleIds);
            })
            ->withCount([
                'articles as accessible_articles_count' => function (Builder $q) use ($roleIds): void {
                    $q->where('status', KnowledgeArticle::STATUS_PUBLISHED)
                        ->accessibleByRoleIds($roleIds);
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Extract headings (h2, h3) for automatic Table of Contents and add anchor IDs.
     *
     * @return array{html: string, toc: list<array{id: string, title: string, level: int}>}
     */
    public function prepareArticleContent(string $html): array
    {
        $toc = [];
        $usedSlugs = [];

        $processed = preg_replace_callback(
            '/<(h[23])([^>]*)>(.*?)<\/\1>/is',
            function (array $matches) use (&$toc, &$usedSlugs): string {
                $tag = strtolower($matches[1]);
                $attrs = $matches[2];
                $inner = $matches[3];
                $plainText = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES, 'UTF-8'));

                if ($plainText === '') {
                    return $matches[0];
                }

                $baseSlug = Str::slug($plainText) ?: 'bagian';
                $slug = $baseSlug;
                $counter = 2;
                while (in_array($slug, $usedSlugs, true)) {
                    $slug = "{$baseSlug}-{$counter}";
                    $counter++;
                }
                $usedSlugs[] = $slug;

                $toc[] = [
                    'id' => $slug,
                    'title' => $plainText,
                    'level' => $tag === 'h2' ? 2 : 3,
                ];

                if (preg_match('/\bid\s*=/i', $attrs) === 1) {
                    return $matches[0];
                }

                return "<{$tag}{$attrs} id=\"{$slug}\">{$inner}</{$tag}>";
            },
            $html
        );

        return [
            'html' => $processed ?? $html,
            'toc' => $toc,
        ];
    }

    /**
     * Identify articles whose referenced Filament Resource class no longer exists.
     *
     * @return Collection<int, KnowledgeArticle>
     */
    public function getArticlesNeedingVerification(): Collection
    {
        return KnowledgeArticle::query()
            ->with('category')
            ->get()
            ->filter(function (KnowledgeArticle $article): bool {
                if ($article->resource_fqcn && ! class_exists($article->resource_fqcn)) {
                    return true;
                }

                if ($article->last_verified_at === null) {
                    return true;
                }

                return $article->last_verified_at->lt(now()->subMonths(6));
            })
            ->values();
    }
}
