<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RkaSetting extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'rka_settings';

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'setting_name',
        'director_user_id',
        'director_name',
        'director_nrp',
        'foundation_head_name',
        'signing_place',
        'pdf_header_config',
        'pdf_footer_config',
        'signing_config',
    ];

    /**
     * Atribut yang harus di-cast ke tipe data tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'pdf_header_config' => 'array',
        'pdf_footer_config' => 'array',
        'signing_config'    => 'array',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /**
     * Default nilai atribut untuk kolom JSON agar tidak bernilai null saat dipanggil.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'signing_place' => 'Bangkinang',
    ];

    /* =========================================================================
     | RELATIONS
     | ========================================================================= */

    /**
     * Relasi ke User Direktur.
     */
    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_user_id');
    }

    /**
     * Relasi ke Header RKA yang menggunakan setting ini.
     */
    public function rkaHeaders(): HasMany
    {
        return $this->hasMany(RkaHeader::class, 'setting_id');
    }

    /* =========================================================================
     | HELPER METHODS & ACCESSORS
     | ========================================================================= */

    /**
     * Mengambil konfigurasi verifikator/penandatangan spesifik berdasarkan key/stage.
     * Contoh: $setting->getSigningStage('verifikasi_1')
     */
    public function getSigningStage(string $stage): ?array
    {
        return $this->signing_config[$stage] ?? null;
    }

    /**
     * Mengambil konfigurasi header PDF dengan fallback default.
     */
    public function getPdfHeaderConfig(): array
    {
        return $this->pdf_header_config ?? [
            'show_logo'   => true,
            'margin_top'  => '10mm',
            'institution' => 'POLITEKNIK KAMPAR',
        ];
    }

    /**
     * Mengambil konfigurasi footer PDF dengan fallback default.
     */
    public function getPdfFooterConfig(): array
    {
        return $this->pdf_footer_config ?? [
            'show_page_number' => true,
            'footer_text'      => 'Dokumen RKA Politeknik Kampar',
        ];
    }
}
