<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkaUnlockRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_USED = 'USED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'rka_unlock_requests';

    protected $fillable = [
        'rka_header_id',
        'department_id',
        'requested_by',
        'reason',
        'error_description',
        'status',
        'reviewed_by',
        'decision_notes',
        'reviewed_at',
        'reimport_granted_at',
        'reimport_expires_at',
        'reimport_used_at',
        'reimport_batch_id',
    ];

    protected $casts = [
        'rka_header_id' => 'integer',
        'department_id' => 'integer',
        'requested_by' => 'integer',
        'reviewed_by' => 'integer',
        'reimport_batch_id' => 'integer',
        'reviewed_at' => 'datetime',
        'reimport_granted_at' => 'datetime',
        'reimport_expires_at' => 'datetime',
        'reimport_used_at' => 'datetime',
    ];

    public function rkaHeader(): BelongsTo
    {
        return $this->belongsTo(RkaHeader::class, 'rka_header_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reimportBatch(): BelongsTo
    {
        return $this->belongsTo(RkaApprovedImportBatch::class, 'reimport_batch_id');
    }

    public function isReimportActive(): bool
    {
        if ($this->status !== self::STATUS_APPROVED || $this->reimport_used_at !== null) {
            return false;
        }

        if ($this->reimport_expires_at !== null && $this->reimport_expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
