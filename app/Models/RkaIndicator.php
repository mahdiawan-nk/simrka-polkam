<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class RkaIndicator extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'rka_indicators';

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rka_header_id',
        'account_id',
        'program_name',
        'activity_name',
        'location',
        'indicator_matrix',
    ];

    /**
     * Atribut yang harus di-cast ke tipe data tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rka_header_id'    => 'integer',
        'account_id'       => 'integer',
        'indicator_matrix' => 'array',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    /**
     * Nilai default untuk atribut model.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'location'         => 'Politeknik Kampar',
        'indicator_matrix' => '[]',
    ];

    /**
     * Kategori Indikator Kinerja Standar RKA (Formulir 5.1 - 5.5).
     */
    public const CATEGORY_CAPAIAN_PROGRAM  = 'Capaian Program';
    public const CATEGORY_MASUKAN          = 'Masukan';
    public const CATEGORY_KELUARAN         = 'Keluaran';
    public const CATEGORY_HASIL            = 'Hasil';
    public const CATEGORY_KELOMPOK_SASARAN = 'Kelompok Sasaran';

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
     * Relasi ke Akun Rekening (Kelompok / Level 1).
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Relasi ke daftar Item Belanja RKA (rka_items).
     */
    public function items(): HasMany
    {
        return $this->hasMany(RkaItem::class, 'rka_indicator_id');
    }

    /* =========================================================================
     | LOCAL SCOPES
     | ========================================================================= */

    /**
     * Scope untuk memfilter indikator berdasarkan account_id (Level 1).
     */
    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope untuk memfilter indikator berdasarkan nama program.
     */
    public function scopeForProgram(Builder $query, string $programName): Builder
    {
        return $query->where('program_name', $programName);
    }

    /* =========================================================================
     | ACCESSORS & HELPER METHODS
     | ========================================================================= */

    /**
     * Mendapatkan daftar semua kategori indikator standar RKA.
     *
     * @return array<string, string>
     */
    public static function getCategoryOptions(): array
    {
        return [
            self::CATEGORY_CAPAIAN_PROGRAM  => self::CATEGORY_CAPAIAN_PROGRAM,
            self::CATEGORY_MASUKAN          => self::CATEGORY_MASUKAN,
            self::CATEGORY_KELUARAN         => self::CATEGORY_KELUARAN,
            self::CATEGORY_HASIL            => self::CATEGORY_HASIL,
            self::CATEGORY_KELOMPOK_SASARAN => self::CATEGORY_KELOMPOK_SASARAN,
        ];
    }

    /**
     * Recalculate dan update target_value pada indicator_matrix untuk kategori MASUKAN
     */
    public function recalculateMasukanTargetValue(): void
    {
        // 1. Hitung total biaya dari seluruh rka_items terkait
        $totalAmount = $this->items()->sum(DB::raw('proposed_volume * unit_price'));

        // 2. Ambil matrix (array of arrays)
        $matrix = $this->indicator_matrix ?? [];

        // 3. Cari row yang ber-kategori MASUKAN dan update target_value-nya
        $updated = false;
        foreach ($matrix as $key => $row) {
            if (($row['indicator_category'] ?? null) === self::CATEGORY_MASUKAN) {
                $matrix[$key]['target_value'] = (string) $totalAmount;
                $updated = true;
            }
        }

        // 4. Simpan kembali ke database jika ada perubahan
        if ($updated) {
            $this->update(['indicator_matrix' => $matrix]);
        }
    }
}
