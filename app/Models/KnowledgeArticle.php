<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

class KnowledgeArticle extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'workflow_steps',
        'status',
        'featured',
        'reading_time',
        'sort_order',
        'module_key',
        'resource_fqcn',
        'doc_version',
        'last_verified_at',
        'maintainer_name',
        'attachments',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'reading_time' => 'integer',
            'sort_order' => 'integer',
            'workflow_steps' => 'array',
            'attachments' => 'array',
            'last_verified_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCategory::class, 'category_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'knowledge_article_role', 'knowledge_article_id', 'role_id')
            ->withTimestamps();
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(KnowledgeArticleFeedback::class, 'knowledge_article_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(KnowledgeArticleStep::class, 'knowledge_article_id')
            ->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereHas('category', fn (Builder $cat): Builder => $cat->where('is_active', true));
    }

    /**
     * @param  list<int>  $roleIds
     */
    public function scopeAccessibleByRoleIds(Builder $query, array $roleIds): Builder
    {
        return $query->where(function (Builder $q) use ($roleIds): void {
            // Artikel umum (tidak terikat role spesifik) dapat dibaca semua pengguna
            $q->whereDoesntHave('roles');

            if ($roleIds !== []) {
                $q->orWhereHas('roles', fn (Builder $roleQuery): Builder => $roleQuery->whereIn('roles.id', $roleIds));
            }
        });
    }
}
