<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundRequestApproval extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'fund_request_approvals';

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'fund_request_id',
        'stage',
        'action',
        'approved_amount',
        'note',
        'user_id',
    ];

    /**
     * Penataan tipe data atribut (casting).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'approved_amount' => 'decimal:2',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /* -------------------------------------------------------------------------- */
    /* RELASI ELOQUENT                                                            */
    /* -------------------------------------------------------------------------- */

    /**
     * Relasi balik ke Pengajuan Dana (FundRequest).
     */
    public function fundRequest(): BelongsTo
    {
        return $this->belongsTo(FundRequest::class, 'fund_request_id');
    }

    /**
     * Relasi ke User / Pejabat Penyetuju (Verifikator).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* -------------------------------------------------------------------------- */
    /* QUERY SCOPES                                                               */
    /* -------------------------------------------------------------------------- */

    /**
     * Scope untuk menyaring log tahap Ka BAKKU.
     */
    public function scopeKabakku($query)
    {
        return $query->where('stage', 'kabakku');
    }

    /**
     * Scope untuk menyaring log tahap Wadir BAKKU.
     */
    public function scopeWadirBakku($query)
    {
        return $query->where('stage', 'wadir_bakku');
    }

    /**
     * Scope untuk menyaring log tahap Direktur.
     */
    public function scopeDirector($query)
    {
        return $query->where('stage', 'director');
    }

    /**
     * Scope untuk menyaring keputusan yang disetujui.
     */
    public function scopeApproved($query)
    {
        return $query->where('action', 'approved');
    }

    /**
     * Scope untuk menyaring keputusan yang ditolak.
     */
    public function scopeRejected($query)
    {
        return $query->where('action', 'rejected');
    }

    /* -------------------------------------------------------------------------- */
    /* ACCESSORS / HELPER ATTRIBUTES                                              */
    /* -------------------------------------------------------------------------- */

    /**
     * Format label nama jabatan / tahap verifikasi secara rapi.
     */
    public function getStageLabelAttribute(): string
    {
        return match ($this->stage) {
            'kabakku'     => 'Kepala BAKKU',
            'wadir_bakku' => 'Wakil Direktur BAKKU',
            'director'    => 'Direktur',
            default       => ucfirst($this->stage),
        };
    }

    /**
     * Format label keputusan (Action).
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'revised'  => 'Dikembalikan (Revisi)',
            default    => ucfirst($this->action),
        };
    }

    /**
     * Color badge Tailwind / Filament berdasarkan aksi keputusan.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'approved' => 'success', // hijau
            'rejected' => 'danger',  // merah
            'revised'  => 'warning', // kuning
            default    => 'gray',
        };
    }
}