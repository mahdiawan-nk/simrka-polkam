<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class RkaGenerateConfig extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'rka_generate_configs';

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rka_setting_id',
        'config_name',
        'is_active',
        'selected_department_ids',
        'template_indicators',
        'created_by',
    ];

    /**
     * Tipe data asli yang perlu dikonversi (type casting).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active'               => 'boolean',
        'selected_department_ids' => 'array',
        'template_indicators'     => 'array',
    ];

    /* =========================================================================
     * RELATIONSHIPS
     * ========================================================================= */

    /**
     * Relasi ke RkaSetting (Many to One).
     */
    public function rkaSetting(): BelongsTo
    {
        return $this->belongsTo(RkaSetting::class, 'rka_setting_id');
    }

    /**
     * Relasi ke User pembuat konfigurasi (Many to One).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* =========================================================================
     * SCOPES
     * ========================================================================= */

    /**
     * Scope query untuk mengambil konfigurasi yang sedang aktif.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /* =========================================================================
     * HELPER METHODS
     * ========================================================================= */

    /**
     * Aktifkan konfigurasi ini dan otomatis menonaktifkan konfigurasi lainnya.
     */
    public function markAsActive(): bool
    {
        static::query()->where('id', '!=', $this->id)->update(['is_active' => false]);

        return $this->update(['is_active' => true]);
    }

    /**
     * Cek apakah departemen tertentu termasuk dalam daftar terpilih di konfigurasi ini.
     */
    public function hasDepartment(int $departmentId): bool
    {
        return in_array($departmentId, $this->selected_department_ids ?? [], true);
    }

    /**
     * Ambil template indikator spesifik berdasarkan kode akun (misal: '5.1', '5.2').
     */
    public function getIndicatorTemplateByCode(string $code): ?array
    {
        return $this->template_indicators[$code] ?? null;
    }
}
