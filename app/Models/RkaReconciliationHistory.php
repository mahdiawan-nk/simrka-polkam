<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkaReconciliationHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'rka_adjustment_match_id',
        'action',
        'actor_id',
        'source',
        'reason',
        'before_state',
        'after_state',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(RkaAdjustmentMatch::class, 'rka_adjustment_match_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
