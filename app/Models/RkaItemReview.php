<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkaItemReview extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'rka_item_reviews';

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rka_item_id',
        'reviewer_id',
        'approved_volume',
        'approved_unit_price',
        'approved_total',
        'status',
        'is_trimmed',
        'price_trimmed',
        'review_notes',
    ];

    /**
     * Atribut yang harus di-cast ke tipe data tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rka_item_id'         => 'integer',
        'reviewer_id'          => 'integer',
        'approved_volume'     => 'decimal:2',
        'approved_unit_price' => 'decimal:2',
        'approved_total'      => 'decimal:2',
        'is_trimmed'          => 'boolean',
        'price_trimmed'       => 'decimal:2',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    /**
     * Constants untuk Status Review Item RKA.
     */
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_REDUCED  = 'REDUCED';
    public const STATUS_REJECTED = 'REJECTED';

    /* =========================================================================
     | RELATIONS
     | ========================================================================= */

    /**
     * Relasi ke item usulan RKA (rka_items).
     */
    public function rkaItem(): BelongsTo
    {
        return $this->belongsTo(RkaItem::class, 'rka_item_id');
    }

    /**
     * Relasi ke pengguna/reviewer yang melakukan verifikasi.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /* =========================================================================
     | LOCAL SCOPES
     | ========================================================================= */

    /**
     * Scope berdasarkan status review.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk menyaring item yang statusnya PENDING.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope untuk item yang mengalami pemangkasan volume/harga.
     */
    public function scopeTrimmed(Builder $query): Builder
    {
        return $query->where('is_trimmed', true);
    }

    /* =========================================================================
     | ACCESSORS & MUTATORS
     | ========================================================================= */

    /**
     * Accessor untuk warna badge status pada UI / Filament Table.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING  => 'gray',
            self::STATUS_ACCEPTED => 'success',
            self::STATUS_REDUCED  => 'warning',
            self::STATUS_REJECTED => 'danger',
            default               => 'secondary',
        };
    }

    /* =========================================================================
     | DOMAIN LOGIC / HELPER METHODS
     | ========================================================================= */

    /**
     * Menerapkan hasil review berdasarkan volume dan harga satuan yang disetujui.
     *
     * @param float $approvedVolume
     * @param float|null $approvedUnitPrice Jika null, menggunakan harga satuan awal usulan.
     * @param string|null $notes
     * @param int|null $reviewerId
     * @return $this
     */
    public function applyReview(
        float $approvedVolume,
        ?float $approvedUnitPrice = null,
        ?string $notes = null,
        ?int $reviewerId = null
    ): self {
        $item = $this->rkaItem;

        $unitPrice = $approvedUnitPrice ?? (float) $item->unit_price;
        $calculatedTotal = $approvedVolume * $unitPrice;
        $proposedTotal = (float) $item->proposed_total;

        $this->approved_volume = $approvedVolume;
        $this->approved_unit_price = $unitPrice;
        $this->approved_total = $calculatedTotal;
        $this->price_trimmed = max(0, $proposedTotal - $calculatedTotal);
        $this->is_trimmed = $calculatedTotal < $proposedTotal;

        if ($approvedVolume <= 0) {
            $this->status = self::STATUS_REJECTED;
        } elseif ($this->is_trimmed) {
            $this->status = self::STATUS_REDUCED;
        } else {
            $this->status = self::STATUS_ACCEPTED;
        }

        if ($notes !== null) {
            $this->review_notes = $notes;
        }

        if ($reviewerId !== null) {
            $this->reviewer_id = $reviewerId;
        }

        return $this;
    }

    /**
     * Mengembalikan nilai review ke status PENDING dan menyamakan nilai dengan usulan awal.
     *
     * @return $this
     */
    public function resetToPending(): self
    {
        $item = $this->rkaItem;

        $this->approved_volume = $item->proposed_volume;
        $this->approved_unit_price = $item->unit_price;
        $this->approved_total = $item->proposed_total;
        $this->status = self::STATUS_PENDING;
        $this->is_trimmed = false;
        $this->price_trimmed = 0.00;
        $this->review_notes = null;

        return $this;
    }
}
