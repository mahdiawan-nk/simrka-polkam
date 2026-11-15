<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

class RkaLog extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'rka_logs';

    /**
     * Tabel log hanya mencatat timestamp penciptaan (created_at) tanpa updated_at.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rka_header_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
        'ip_address',
        'created_at',
    ];

    /**
     * Atribut yang harus di-cast ke tipe data tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rka_header_id' => 'integer',
        'user_id'       => 'integer',
        'old_values'    => 'array',
        'new_values'    => 'array',
        'created_at'    => 'datetime',
    ];

    /**
     * Constants untuk Jenis Aksi Audit Log.
     */
    public const ACTION_OPEN_LOCK_UNIT   = 'OPEN_LOCK_UNIT';
    public const ACTION_LOCK_UNIT        = 'LOCK_UNIT';
    public const ACTION_SUBMIT_RKA       = 'SUBMIT_RKA';
    public const ACTION_REVIEW_UPDATE    = 'REVIEW_UPDATE';
    public const ACTION_TRIM_ITEM_VOLUME = 'TRIM_ITEM_VOLUME';
    public const ACTION_ADJUSTMENT_MOVE = 'ADJUSTMENT_MOVE';
    public const ACTION_APPROVE_RKA      = 'APPROVE_RKA';
    public const ACTION_REJECT_RKA       = 'REJECT_RKA';

    /* =========================================================================
     | RELATIONS
     | ========================================================================= */

    /**
     * Relasi ke Header RKA Unit (Opsional, null jika aksi berlingkup sistem/global).
     */
    public function rkaHeader(): BelongsTo
    {
        return $this->belongsTo(RkaHeader::class, 'rka_header_id');
    }

    /**
     * Relasi ke User pelaku aksi.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* =========================================================================
     | LOCAL SCOPES
     | ========================================================================= */

    /**
     * Scope berdasarkan jenis aksi audit log.
     */
    public function scopeAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope untuk menyaring log berdasarkan user pelaku.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope untuk log yang memiliki kaitan dengan RKA Header tertentu.
     */
    public function scopeForHeader(Builder $query, int $headerId): Builder
    {
        return $query->where('rka_header_id', $headerId);
    }

    /* =========================================================================
     | HELPER METHODS & STATIC AUDITOR
     | ========================================================================= */

    /**
     * Helper Static Method untuk mencatat log audit secara praktis.
     * 
     * Contoh Penggunaan:
     * RkaLog::record(
     *     action: RkaLog::ACTION_TRIM_ITEM_VOLUME,
     *     headerId: $rkaHeader->id,
     *     oldValues: ['volume' => 10],
     *     newValues: ['volume' => 5]
     * );
     */
    public static function record(
        string $action,
        ?int $headerId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): static {
        return static::create([
            'rka_header_id' => $headerId,
            'user_id'       => $userId ?? auth()->id(),
            'action'        => $action,
            'old_values'    => $oldValues,
            'new_values'    => $newValues,
            'ip_address'    => Request::ip(),
            'created_at'    => now(),
        ]);
    }

    /**
     * Accessor untuk menentukan warna badge jenis aksi pada UI/Filament Table.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_SUBMIT_RKA, self::ACTION_APPROVE_RKA => 'success',
            self::ACTION_TRIM_ITEM_VOLUME, self::ACTION_ADJUSTMENT_MOVE => 'warning',
            self::ACTION_REJECT_RKA, self::ACTION_LOCK_UNIT => 'danger',
            self::ACTION_OPEN_LOCK_UNIT => 'info',
            default => 'gray',
        };
    }
}