<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TableEmptyRun extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'status',
        'total_tables',
        'processed_tables',
        'total_rows_deleted',
        'backup_before_empty',
        'backup_run_id',
        'started_at',
        'completed_at',
        'duration_seconds',
        'error_message',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'backup_before_empty' => 'boolean',
            'total_tables' => 'integer',
            'processed_tables' => 'integer',
            'total_rows_deleted' => 'integer',
            'duration_seconds' => 'integer',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Status Constants
    |--------------------------------------------------------------------------
    */

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_SUCCESS => 'Success',
            self::STATUS_PARTIAL => 'Partial',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function items(): HasMany
    {
        return $this->hasMany(TableEmptyItem::class);
    }

    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isCompleted(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESS,
            self::STATUS_PARTIAL,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function getProgressAttribute(): int
    {
        if ($this->total_tables === 0) {
            return 0;
        }

        return (int) round(
            ($this->processed_tables / $this->total_tables) * 100
        );
    }
}
