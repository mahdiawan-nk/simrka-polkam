<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RkaSchedule extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'rka_schedules';

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'fiscal_year',
        'period_range',
        'phase_name',
        'start_at',
        'end_at',
        'is_active',
        'allow_late_submission',
        'created_by',
    ];

    /**
     * Atribut yang harus di-cast ke tipe data tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_active' => 'boolean',
        'allow_late_submission' => 'boolean',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* =========================================================================
     | RELATIONS
     | ========================================================================= */

    /**
     * Relasi ke User pembuat jadwal.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke dokumen Header RKA unit yang terikat dengan jadwal ini.
     */
    public function rkaHeaders(): HasMany
    {
        return $this->hasMany(RkaHeader::class, 'schedule_id');
    }

    /* =========================================================================
     | LOCAL SCOPES
     | ========================================================================= */

    /**
     * Scope untuk mengambil jadwal yang sedang aktif (is_active = true).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk mengambil jadwal yang rentang waktunya sedang berlangsung saat ini.
     */
    public function scopeOpenNow(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->where('is_active', true)
            ->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now);
    }

    /**
     * Scope berdasarkan tahun anggaran (misal: '2026/2027').
     */
    public function scopeForFiscalYear(Builder $query, string $fiscalYear): Builder
    {
        return $query->where('fiscal_year', $fiscalYear);
    }

    /* =========================================================================
     | HELPER METHODS & ACCESSORS
     | ========================================================================= */

    /**
     * Mengecek apakah portal pengisian RKA sedang terbuka berdasarkan waktu sekarang.
     */
    public function isOpen(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return Carbon::now()->between($this->start_at, $this->end_at);
    }

    /**
     * Mengambil tahun anggaran aktif dari jadwal RKA, atau fallback ke jadwal terbaru / tahun berjalan.
     */
    public static function currentFiscalYear(): string
    {
        return static::query()
            ->where('is_active', true)
            ->orderByDesc('start_at')
            ->value('fiscal_year')
            ?? static::query()
                ->orderByDesc('start_at')
                ->value('fiscal_year')
            ?? (now()->month >= 9
                ? now()->year.'/'.(now()->year + 1)
                : (now()->year - 1).'/'.now()->year);
    }

    /**
     * Mengecek apakah jadwal sudah terlewat (expired).
     */
    public function isExpired(): bool
    {
        return Carbon::now()->greaterThan($this->end_at);
    }

    /**
     * Accessor untuk mendapatkan status human-readable untuk UI/Badge Filament.
     * Contoh: $schedule->status_label
     */
    public function getStatusLabelAttribute(): string
    {
        if (! $this->is_active) {
            return 'Nonaktif';
        }

        $now = Carbon::now();

        if ($now->lessThan($this->start_at)) {
            return 'Belum Dimulai';
        }

        if ($now->between($this->start_at, $this->end_at)) {
            return 'Terbuka';
        }

        return $this->allow_late_submission ? 'Terbuka (Susulan)' : 'Ditutup';
    }
}
