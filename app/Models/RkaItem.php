<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class RkaItem extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'rka_items';

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rka_header_id',
        'rka_indicator_id',
        'account_id',
        'form_type',
        'description',
        'group_header',
        'proposed_volume',
        'unit_symbol',
        'unit_price',
        'approved_volume',
        'approved_total',
        'status',
        'is_trimmed',
        'price_trimed',
        'is_imported',
        'review_notes',
    ];

    /**
     * Atribut yang harus di-cast ke tipe data tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rka_header_id'    => 'integer',
        'rka_indicator_id' => 'integer',
        'account_id'       => 'integer',
        'proposed_volume'  => 'decimal:2',
        'unit_price'       => 'decimal:2',
        'proposed_total'   => 'decimal:2',
        'approved_volume'  => 'decimal:2',
        'approved_total'   => 'decimal:2',
        'is_trimmed'       => 'boolean',
        'price_trimed'    => 'decimal:2',
        'is_imported'       => 'boolean',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    /**
     * Constants untuk Status Review Item Belanja.
     */
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_REDUCED  = 'REDUCED';
    public const STATUS_REJECTED = 'REJECTED';

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
     * Relasi ke Indikator Kinerja RKA.
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(RkaIndicator::class, 'rka_indicator_id');
    }

    /**
     * Relasi ke Akun Rekening Belanja.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Relasi ke Hasil Review Item Belanja (1:1).
     */
    public function review(): HasOne
    {
        return $this->hasOne(RkaItemReview::class, 'rka_item_id');
    }

    /**
     * Relasi ke penyesuaian/alokasi pemindahan item saat review (jika ada).
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(RkaAdjustment::class, 'rka_item_id');
    }

    /* =========================================================================
     | LOCAL SCOPES
     | ========================================================================= */

    /**
     * Scope berdasarkan jenis formulir RKA (misal: '5.1', '5.2').
     */
    public function scopeFormType(Builder $query, string $formType): Builder
    {
        return $query->where('form_type', $formType);
    }

    /**
     * Scope menyaring item hasil limpahan/impor adjustment.
     */
    public function scopeImported(Builder $query): Builder
    {
        return $query->where('is_imported', true);
    }

    /* =========================================================================
     | ACCESSORS & HELPER METHODS
     | ========================================================================= */

    /**
     * Accessor untuk mendapatkan nominal total akhir yang berlaku.
     * Mengambil approved_total dari relasi review jika sudah direview, atau proposed_total usulan awal.
     */
    public function getEffectiveTotalAttribute(): float
    {
        return $this->review && $this->review->approved_total !== null
            ? (float) $this->review->approved_total
            : (float) $this->proposed_total;
    }
}
