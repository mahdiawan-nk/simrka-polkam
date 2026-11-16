<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class KnowledgeArticleStep extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'knowledge_article_id',
        'sort_order',
        'step_title',
        'step_description',
        'action_instruction',
        'expected_result',
        'image_path',
        'image_alt',
        'image_caption',
        'annotations',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'annotations' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (KnowledgeArticleStep $step): void {
            static::deleteImageIfUnused($step->image_path, $step->id);
        });

        static::updating(function (KnowledgeArticleStep $step): void {
            if ($step->isDirty('image_path')) {
                static::deleteImageIfUnused($step->getOriginal('image_path'), $step->id);
            }
        });
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'knowledge_article_id');
    }

    protected static function deleteImageIfUnused(?string $path, int $ignoreStepId): void
    {
        if (! $path) {
            return;
        }

        $stillUsed = static::query()
            ->where('id', '!=', $ignoreStepId)
            ->where('image_path', $path)
            ->exists();

        if (! $stillUsed && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
