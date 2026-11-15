<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RkaAdjustmentMatch extends Model
{
    use HasFactory;

    public const STATUS_WAITING_COUNTERPART = 'WAITING_COUNTERPART';

    public const STATUS_MATCHED = 'MATCHED';

    public const STATUS_REVIEW_REQUIRED = 'REVIEW_REQUIRED';

    public const STATUS_UNMATCHED = 'UNMATCHED';

    public const STATUS_CONFLICT = 'CONFLICT';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const LEVEL_MATCH_UNIQUE = 'MATCH_UNIQUE';

    public const LEVEL_MATCH_NONE = 'MATCH_NONE';

    public const LEVEL_MATCH_AMBIGUOUS = 'MATCH_AMBIGUOUS';

    public const LEVEL_MATCH_CONFLICT = 'MATCH_CONFLICT';

    public const LEVEL_NOT_REQUIRED = 'NOT_REQUIRED';

    protected $fillable = [
        'schedule_id',
        'source_adjustment_id',
        'counterpart_adjustment_id',
        'source_item_id',
        'counterpart_item_id',
        'source_department_id',
        'target_department_id',
        'reconciliation_status',
        'matching_level',
        'matched_amount',
        'matching_rule',
        'match_details',
        'source_import_batch_id',
        'counterpart_import_batch_id',
        'matched_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'matched_amount' => 'decimal:2',
        'match_details' => 'array',
        'matched_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_WAITING_COUNTERPART => 'Menunggu Unit Lawan',
            self::STATUS_MATCHED => 'Cocok Otomatis (Sah)',
            self::STATUS_REVIEW_REQUIRED => 'Perlu Tinjauan Finance',
            self::STATUS_UNMATCHED => 'Tidak Ada Pasangan',
            self::STATUS_CONFLICT => 'Konflik Data/Nilai',
            self::STATUS_CANCELLED => 'Dibatalkan',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RkaSchedule::class, 'schedule_id');
    }

    public function sourceAdjustment(): BelongsTo
    {
        return $this->belongsTo(RkaAdjustment::class, 'source_adjustment_id');
    }

    public function counterpartAdjustment(): BelongsTo
    {
        return $this->belongsTo(RkaAdjustment::class, 'counterpart_adjustment_id');
    }

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(RkaItem::class, 'source_item_id');
    }

    public function counterpartItem(): BelongsTo
    {
        return $this->belongsTo(RkaItem::class, 'counterpart_item_id');
    }

    public function sourceDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'source_department_id');
    }

    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    public function sourceImportBatch(): BelongsTo
    {
        return $this->belongsTo(RkaApprovedImportBatch::class, 'source_import_batch_id');
    }

    public function counterpartImportBatch(): BelongsTo
    {
        return $this->belongsTo(RkaApprovedImportBatch::class, 'counterpart_import_batch_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RkaReconciliationHistory::class, 'rka_adjustment_match_id')->orderByDesc('id');
    }
}
