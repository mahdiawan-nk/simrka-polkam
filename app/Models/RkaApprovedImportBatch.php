<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkaApprovedImportBatch extends Model
{
    use HasFactory;

    public const TYPE_INITIAL = 'INITIAL';

    public const TYPE_REIMPORT = 'REIMPORT';

    public const STATUS_SUCCESS = 'SUCCESS';

    public const STATUS_FAILED = 'FAILED';

    protected $table = 'rka_approved_import_batches';

    protected $fillable = [
        'rka_header_id',
        'department_id',
        'schedule_id',
        'imported_by',
        'unlock_request_id',
        'import_type',
        'original_filename',
        'file_checksum',
        'belanja_count',
        'adjustment_count',
        'total_proposed',
        'total_approved',
        'status',
        'summary',
    ];

    protected $casts = [
        'rka_header_id' => 'integer',
        'department_id' => 'integer',
        'schedule_id' => 'integer',
        'imported_by' => 'integer',
        'unlock_request_id' => 'integer',
        'belanja_count' => 'integer',
        'adjustment_count' => 'integer',
        'total_proposed' => 'decimal:2',
        'total_approved' => 'decimal:2',
        'summary' => 'array',
    ];

    public function rkaHeader(): BelongsTo
    {
        return $this->belongsTo(RkaHeader::class, 'rka_header_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RkaSchedule::class, 'schedule_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function unlockRequest(): BelongsTo
    {
        return $this->belongsTo(RkaUnlockRequest::class, 'unlock_request_id');
    }
}
