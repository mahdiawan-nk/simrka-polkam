<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RkaHeader extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'rka_headers';

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'schedule_id',
        'department_id',
        'setting_id',
        'unit_head_user_id',
        'submitted_at',
        'pagu_budget',
        'reviewer_user_id',
        'review_date',
        'general_notes',
        'status',
        'is_locked',
        'unlocked_by_admin',
    ];

    /**
     * Atribut yang harus di-cast ke tipe data tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'submitted_at' => 'datetime',
        'review_date' => 'date',
        'pagu_budget' => 'decimal:2',
        'is_locked' => 'boolean',
        'unlocked_by_admin' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Constants untuk Opsi Status Dokumen RKA.
     */
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_IN_REVIEW = 'IN_REVIEW';

    public const STATUS_IN_APPROVED = 'IN_APPROVED';

    public const STATUS_REVISION_REQUIRED = 'REVISION_REQUIRED';

    public const STATUS_APPROVED = 'APPROVED';

    /* =========================================================================
     | RELATIONS
     | ========================================================================= */

    /**
     * Relasi ke Jadwal Portal RKA.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RkaSchedule::class, 'schedule_id');
    }

    /**
     * Relasi ke Department / Unit Kerja pengusul.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Relasi ke Konfigurasi Institusi / Pengaturan Penandatangan RKA.
     */
    public function setting(): BelongsTo
    {
        return $this->belongsTo(RkaSetting::class, 'setting_id');
    }

    /**
     * Relasi ke User Pengusul (Ka. Unit / Kepala Department).
     */
    public function unitHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unit_head_user_id');
    }

    /**
     * Relasi ke User Reviewer (Keuangan / Wadir II).
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    /**
     * Relasi ke daftar Indikator Kinerja Belanja (rka_indicators).
     */
    public function indicators(): HasMany
    {
        return $this->hasMany(RkaIndicator::class, 'rka_header_id');
    }

    /**
     * Relasi ke rincian item belanja (rka_items).
     */
    public function items(): HasMany
    {
        return $this->hasMany(RkaItem::class, 'rka_header_id');
    }

    /**
     * Relasi ke daftar penyesuaian usulan / shared cost (rka_adjustments).
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(RkaAdjustment::class, 'rka_header_id');
    }

    /**
     * Relasi ke riwayat persetujuan & digital sign-off (rka_approvals).
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(RkaApproval::class, 'rka_header_id');
    }

    /**
     * Relasi ke log audit perubahan dokumen RKA (rka_logs).
     */
    public function logs(): HasMany
    {
        return $this->hasMany(RkaLog::class, 'rka_header_id');
    }

    /**
     * Relasi ke riwayat batch import RKA Approved.
     */
    public function approvedImportBatches(): HasMany
    {
        return $this->hasMany(RkaApprovedImportBatch::class, 'rka_header_id');
    }

    /**
     * Relasi ke riwayat permintaan pembukaan kunci RKA.
     */
    public function unlockRequests(): HasMany
    {
        return $this->hasMany(RkaUnlockRequest::class, 'rka_header_id');
    }

    /* =========================================================================
     | LOCAL SCOPES
     | ========================================================================= */

    /**
     * Scope berdasarkan status dokumen.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk dokumen yang terkunci.
     */
    public function scopeLocked(Builder $query): Builder
    {
        return $query->where('is_locked', true);
    }

    /**
     * Scope untuk dokumen yang sudah disetujui (APPROVED).
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /* =========================================================================
     | HELPER METHODS & CALCULATIONS
     | ========================================================================= */

    /**
     * Menghitung total anggaran yang diusulkan oleh unit (berdasarkan rka_items).
     */
    public function calculateProposedTotal(): float
    {
        return (float) $this->items()->where('is_imported', 0)->sum('proposed_total');
    }

    /**
     * Menghitung total anggaran hasil review/disetujui.
     */
    public function calculateApprovedTotal(): float
    {
        return (float) $this->items()->sum('approved_total');
    }

    /**
     * Mengecek apakah total usulan unit melebihi pagu anggaran yang dialokasikan.
     */
    public function isOverBudget(): bool
    {
        return $this->calculateProposedTotal() > (float) $this->pagu_budget;
    }

    /**
     * Accessor untuk mendapatkan warna badge status di Filament UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_SUBMITTED => 'info',
            self::STATUS_IN_REVIEW => 'warning',
            self::STATUS_REVISION_REQUIRED => 'danger',
            self::STATUS_APPROVED => 'success',
            default => 'secondary',
        };
    }

    /* =========================================================================
     | HELPER METHODS & CALCULATIONS
     | ========================================================================= */

    /**
     * Menghitung total pagu/usulan berdasarkan rka_header_id dari seluruh rka_items (proposed_total).
     */
    public function calculateTotalProposedByHeader(): float
    {
        return (float) $this->items()->where('is_imported', 0)->sum('proposed_total');
    }

    /**
     * Accessor untuk mendapatkan total proposed secara langsung via properti ($rkaHeader->total_proposed)
     */
    public function getTotalProposedAttribute(): float
    {
        return $this->calculateTotalProposedByHeader();
    }

    /**
     * Menghitung dan memperbarui pagu_budget RKA Header berdasarkan total proposed_total seluruh rka_items
     */
    public function calculatePaguRka(): float
    {
        // Hitung sum proposed_total dari seluruh items usulan unit (non-imported) terkait rka_header ini
        $totalProposed = (float) $this->items()->where('is_imported', 0)->sum('proposed_total');

        // Update kolom pagu_budget di database
        $this->update([
            'pagu_budget' => $totalProposed,
        ]);

        return $totalProposed;
    }

    /**
     * Mengambil adjustment dari unit lain yang dipindahkan (MOVED) ke unit ini.
     */
    public function incomingMovedAdjustments(): HasMany
    {
        return $this->hasMany(RkaAdjustment::class, 'destination_unit_id', 'department_id')
            ->where('section_type', RkaAdjustment::SECTION_A)
            ->where('action_type', RkaAdjustment::ACTION_MOVED);
    }

    /**
     * Mengambil adjustment dari unit lain yang merupakan Shared Cost (SHARED_COST) untuk unit ini.
     */
    public function incomingSharedCosts(): HasMany
    {
        return $this->hasMany(RkaAdjustment::class, 'target_unit_id', 'department_id')
            ->where('section_type', RkaAdjustment::SECTION_B)
            ->where('action_type', RkaAdjustment::ACTION_SHARED_COST);
    }

    /**
     * Mengambil semua adjustment dari unit lain yang dialokasikan untuk unit ini (Moved & Shared Cost).
     */
    public function incomingAdjustments(): Builder
    {
        return RkaAdjustment::query()->where(function ($query): void {
            $query->where('destination_unit_id', $this->department_id)
                ->orWhere('target_unit_id', $this->department_id);
        });
    }
}
