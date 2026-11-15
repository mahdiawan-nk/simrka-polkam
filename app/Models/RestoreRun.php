<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestoreRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'table_names' => 'array',
            'metadata' => 'array',
            'safety_backup_created' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class);
    }

    public function backupFile(): BelongsTo
    {
        return $this->belongsTo(BackupFile::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function safetyBackup(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class, 'safety_backup_run_id');
    }
}