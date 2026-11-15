<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkaAdjustment extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'rka_adjustments';

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rka_header_id',
        'rka_indicator_id',
        'rka_item_id',
        'section_type',
        'section_name',
        'account_id',
        'description',
        'destination_unit_id',
        'target_unit_id',
        'proposed_volume',
        'unit_symbol',
        'unit_price',
        'proposed_amount',
        'approved_amount',
        'action_type',
        'notes',
        'adjusted_by',
        'is_imported',
        'imported_rka_item_id',
    ];

    /**
     * Atribut yang harus di-cast ke tipe data tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rka_header_id'       => 'integer',
        'rka_indicator_id'    => 'integer',
        'rka_item_id'         => 'integer',
        'account_id'          => 'integer',
        'destination_unit_id' => 'integer',
        'target_unit_id'      => 'integer',
        'proposed_volume'     => 'decimal:2',
        'unit_price'          => 'decimal:2',
        'adjusted_by'         => 'integer',
        'is_imported'          => 'boolean',
        'proposed_amount'     => 'decimal:2',
        'approved_amount'     => 'decimal:2',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    /**
     * Constants untuk Tipe Bagian Penyesuaian (Section Type).
     */
    public const SECTION_A = 'A'; // Usulan Dipindah / Tidak Dianggarkan di Unit Ini
    public const SECTION_B = 'B'; // Pos Dikelola Unit Ini untuk Unit Lain (Shared Cost / Usulan Tambahan)

    /**
     * Constants untuk Jenis Aksi Reviewer (Action Type).
     */
    public const ACTION_MOVED                  = 'MOVED';                  // Dipindahkan ke unit lain (Membuat Section B)
    public const ACTION_BUDGETED_ELSEWHERE     = 'BUDGETED_ELSEWHERE';     // Sudah Dianggarkan di Unit Lain / Terpusat
    public const ACTION_REJECTED_WITH_ADJUSTMENT = 'REJECTED_WITH_ADJUSTMENT'; // Disesuaikan Lalu Ditolak
    public const ACTION_REJECTED               = 'REJECTED';               // Dibatalkan / Ditolak Langsung
    public const ACTION_REMOVED                = 'REMOVED';                // Dipangkas / Dihapus
    public const ACTION_PENDING                = 'PENDING';                // Ditunda
    public const ACTION_SHARED_COST            = 'SHARED_COST';            // Dialokasikan untuk unit lain
    public const ACTION_ADDED_BY_REVIEWER      = 'ADDED_BY_REVIEWER';      // Diberikan tambahan anggaran oleh reviewer

    /* =========================================================================
     | RELATIONS
     | ========================================================================= */

    /**
     * Relasi ke Header RKA Unit.
     */
    public function rkaHeader(): BelongsTo
    {
        return $this->belongsTo(RkaHeader::class, 'rka_header_id');
    }

    /**
     * Relasi ke Indikator Kinerja RKA (Opsional).
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(RkaIndicator::class, 'rka_indicator_id');
    }

    /**
     * Relasi ke Item Belanja Asal (Opsional, jika ditarik dari rka_items).
     */
    public function rkaItem(): BelongsTo
    {
        return $this->belongsTo(RkaItem::class, 'rka_item_id');
    }

    /**
     * Relasi ke Akun Rekening Belanja (Opsional).
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Relasi ke Unit/Department Tujuan (For Section A: Pemindahan Usulan).
     */
    public function destinationUnit(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'destination_unit_id');
    }

    /**
     * Relasi ke Unit/Department Penerima Manfaat (For Section B: Shared Cost).
     */
    public function targetUnit(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_unit_id');
    }

    /**
     * Relasi ke User Reviewer yang melakukan penyesuaian.
     */
    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    /* =========================================================================
     | LOCAL SCOPES
     | ========================================================================= */

    /**
     * Scope untuk Bagian A (Usulan Dipindah / Dibatalkan).
     */
    public function scopeSectionA(Builder $query): Builder
    {
        return $query->where('section_type', self::SECTION_A);
    }

    /**
     * Scope untuk Bagian B (Pos Dikelola / Shared Cost).
     */
    public function scopeSectionB(Builder $query): Builder
    {
        return $query->where('section_type', self::SECTION_B);
    }

    /**
     * Scope berdasarkan jenis aksi reviewer.
     */
    public function scopeActionType(Builder $query, string $actionType): Builder
    {
        return $query->where('action_type', $actionType);
    }

    /* =========================================================================
     | ACCESSORS & HELPER METHODS
     | ========================================================================= */

    /**
     * Accessor untuk mendapatkan selisih penyesuaian anggaran (Approved - Proposed).
     */
    public function getAdjustmentDifferenceAttribute(): float
    {
        return (float) $this->approved_amount - (float) $this->proposed_amount;
    }

    /**
     * Accessor untuk warna badge action_type pada UI / Filament Table.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action_type) {
            self::ACTION_MOVED                  => 'warning',
            self::ACTION_BUDGETED_ELSEWHERE     => 'info',
            self::ACTION_REJECTED_WITH_ADJUSTMENT => 'danger',
            self::ACTION_REJECTED               => 'danger',
            self::ACTION_REMOVED                => 'gray',
            self::ACTION_PENDING                => 'warning',
            self::ACTION_SHARED_COST            => 'info',
            self::ACTION_ADDED_BY_REVIEWER      => 'success',
            default                             => 'secondary',
        };
    }

    /**
     * Accessor untuk label deskriptif action_type.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action_type) {
            self::ACTION_MOVED                  => 'Dipindahkan',
            self::ACTION_BUDGETED_ELSEWHERE     => 'Dianggarkan di Unit Lain',
            self::ACTION_REJECTED_WITH_ADJUSTMENT => 'Disesuaikan Lalu Ditolak',
            self::ACTION_REJECTED               => 'Dibatalkan / Ditolak',
            self::ACTION_REMOVED                => 'Dipangkas / Dihapus',
            self::ACTION_PENDING                => 'Ditunda',
            self::ACTION_SHARED_COST            => 'Pos Shared Cost',
            self::ACTION_ADDED_BY_REVIEWER      => 'Tambahan Reviewer',
            default                             => $this->action_type,
        };
    }
}
