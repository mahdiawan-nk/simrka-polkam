<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkaApproval extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'rka_approvals';

    /**
     * Properti timestamps yang digunakan.
     * Mengingat tabel ini hanya menggunakan signed_at dan tanpa updated_at/created_at standar.
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
        'approver_user_id',
        'stage',
        'action',
        'comments',
        'digital_signature_hash',
        'signed_at',
    ];

    /**
     * Atribut yang harus di-cast ke tipe data tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rka_header_id'    => 'integer',
        'approver_user_id' => 'integer',
        'signed_at'        => 'datetime',
    ];

    /**
     * Constants untuk Tahapan Persetujuan (Stage).
     */
    public const STAGE_KA_UNIT_SUBMIT = 'KA_UNIT_SUBMIT';
    public const STAGE_VERIFIKASI_1    = 'VERIFIKASI_1';    // Staf Keuangan
    public const STAGE_WADIR2_REVIEW   = 'WADIR2_REVIEW';   // Wadir II Keuangan
    public const STAGE_DIREKTUR_APPROVE = 'DIREKTUR_APPROVAL'; // Direktur

    /**
     * Constants untuk Jenis Aksi Persetujuan (Action).
     */
    public const ACTION_SUBMITTED = 'SUBMITTED';
    public const ACTION_REVISED   = 'REVISED';
    public const ACTION_APPROVED  = 'APPROVED';
    public const ACTION_REJECTED  = 'REJECTED';

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
     * Relasi ke User Penandatangan / Approver.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    /* =========================================================================
     | LOCAL SCOPES
     | ========================================================================= */

    /**
     * Scope berdasarkan tahapan persetujuan (stage).
     */
    public function scopeStage(Builder $query, string $stage): Builder
    {
        return $query->where('stage', $stage);
    }

    /**
     * Scope berdasarkan aksi persetujuan (action).
     */
    public function scopeAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope untuk persetujuan yang memiliki tanda tangan digital (TTE).
     */
    public function scopeSigned(Builder $query): Builder
    {
        return $query->whereNotNull('digital_signature_hash');
    }

    /* =========================================================================
     | ACCESSORS & HELPER METHODS
     | ========================================================================= */

    /**
     * Accessor untuk mengecek apakah persetujuan ini sudah ditandatangani digital (TTE).
     */
    public function getIsSignedAttribute(): bool
    {
        return ! empty($this->digital_signature_hash);
    }

    /**
     * Accessor untuk warna badge action pada UI / Filament Table.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_SUBMITTED => 'info',
            self::ACTION_APPROVED  => 'success',
            self::ACTION_REVISED   => 'warning',
            self::ACTION_REJECTED  => 'danger',
            default                => 'secondary',
        };
    }

    /**
     * Accessor untuk label deskriptif tahapan persetujuan.
     */
    public function getStageLabelAttribute(): string
    {
        return match ($this->stage) {
            self::STAGE_KA_UNIT_SUBMIT  => 'Pengajuan Ka. Unit',
            self::STAGE_VERIFIKASI_1    => 'Verifikasi Keuangan',
            self::STAGE_WADIR2_REVIEW   => 'Review Wadir II',
            self::STAGE_DIREKTUR_APPROVE => 'Pengesahan Direktur',
            default                     => $this->stage,
        };
    }
}