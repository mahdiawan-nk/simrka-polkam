<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Request;

class RkaLog extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'rka_logs';

    /**
     * Tabel log hanya mencatat timestamp penciptaan (created_at) tanpa updated_at.
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
        'subject_type',
        'subject_id',
        'user_id',
        'action',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'created_at',
    ];

    /**
     * Atribut yang harus di-cast ke tipe data tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rka_header_id' => 'integer',
        'user_id' => 'integer',
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Constants untuk Jenis Aksi Audit Log.
     */
    public const ACTION_CREATE_RKA = 'CREATE_RKA';

    public const ACTION_UPDATE_RKA = 'UPDATE_RKA';

    public const ACTION_DELETE_RKA = 'DELETE_RKA';

    public const ACTION_GENERATE_RKA = 'GENERATE_RKA';

    public const ACTION_ADD_INDICATOR = 'ADD_INDICATOR';

    public const ACTION_ADD_ITEM = 'ADD_ITEM';

    public const ACTION_UPDATE_ITEM = 'UPDATE_ITEM';

    public const ACTION_DELETE_ITEM = 'DELETE_ITEM';

    public const ACTION_CLEAR_ITEMS = 'CLEAR_ITEMS';

    public const ACTION_IMPORT_ITEMS = 'IMPORT_ITEMS';

    public const ACTION_OPEN_LOCK_UNIT = 'OPEN_LOCK_UNIT';

    public const ACTION_LOCK_UNIT = 'LOCK_UNIT';

    public const ACTION_SUBMIT_RKA = 'SUBMIT_RKA';

    public const ACTION_RESUBMIT_RKA = 'RESUBMIT_RKA';

    public const ACTION_START_REVIEW = 'START_REVIEW';

    public const ACTION_REVIEW_UPDATE = 'REVIEW_UPDATE';

    public const ACTION_BULK_APPROVE = 'BULK_APPROVE';

    public const ACTION_TRIM_ITEM_VOLUME = 'TRIM_ITEM_VOLUME';

    public const ACTION_ADJUSTMENT_MOVE = 'ADJUSTMENT_MOVE';

    public const ACTION_ADD_ADJUSTMENT = 'ADD_ADJUSTMENT';

    public const ACTION_DELETE_ADJUSTMENT = 'DELETE_ADJUSTMENT';

    public const ACTION_IMPORT_ADJUSTMENT = 'IMPORT_ADJUSTMENT';

    public const ACTION_FORWARD_DIREKTUR = 'FORWARD_DIREKTUR';

    public const ACTION_REQUEST_REVISION = 'REQUEST_REVISION';

    public const ACTION_APPROVE_RKA = 'APPROVE_RKA';

    public const ACTION_REJECT_RKA = 'REJECT_RKA';

    public const ACTION_EXPORT_RKA = 'rka.exported';

    public const ACTION_APPROVED_IMPORT_PREVIEW = 'rka.approved_import.preview';

    public const ACTION_APPROVED_IMPORT_UNIT_ASSIGNED = 'rka.approved_import.unit_assigned';

    public const ACTION_APPROVED_IMPORT_EXECUTED = 'rka.approved_import.executed';

    public const ACTION_APPROVED_IMPORT_FAILED = 'rka.approved_import.failed';

    public const ACTION_APPROVED_REIMPORT_EXECUTED = 'rka.approved_reimport.executed';

    public const ACTION_APPROVED_REIMPORT_FAILED = 'rka.approved_reimport.failed';

    public const ACTION_UNLOCK_REQUESTED = 'rka.unlock.requested';

    public const ACTION_UNLOCK_APPROVED = 'rka.unlock.approved';

    public const ACTION_UNLOCK_REJECTED = 'rka.unlock.rejected';

    public const ACTION_UNLOCK_REIMPORT_USED = 'rka.unlock.used';

    public const ACTION_UNLOCK_REIMPORT_EXPIRED = 'rka.unlock.expired';

    public const ACTION_RELOCKED = 'rka.relocked';

    public const ACTION_RECONCILIATION_STARTED = 'rka.reconciliation.started';

    public const ACTION_RECONCILIATION_MATCHED = 'rka.reconciliation.matched';

    public const ACTION_RECONCILIATION_WAITING = 'rka.reconciliation.waiting';

    public const ACTION_RECONCILIATION_CONFLICT = 'rka.reconciliation.conflict';

    public const ACTION_RECONCILIATION_ADJUSTED = 'rka.reconciliation.adjusted';

    public const ACTION_RECONCILIATION_CANCELLED = 'rka.reconciliation.cancelled';

    public const ACTION_RECONCILIATION_RERUN = 'rka.reconciliation.rerun';

    /* =========================================================================
     | RELATIONS
     | ========================================================================= */

    /**
     * Relasi ke Header RKA Unit (Opsional, null jika aksi berlingkup sistem/global).
     */
    public function rkaHeader(): BelongsTo
    {
        return $this->belongsTo(RkaHeader::class, 'rka_header_id');
    }

    /**
     * Relasi ke User pelaku aksi.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi polimorfik ke entitas terdampak (RkaHeader, RkaIndicator, RkaItem, RkaAdjustment, RkaApproval).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /* =========================================================================
     | LOCAL SCOPES
     | ========================================================================= */

    /**
     * Scope berdasarkan jenis aksi audit log.
     */
    public function scopeAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope untuk menyaring log berdasarkan user pelaku.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope untuk log yang memiliki kaitan dengan RKA Header tertentu.
     */
    public function scopeForHeader(Builder $query, int $headerId): Builder
    {
        return $query->where('rka_header_id', $headerId);
    }

    /**
     * Scope untuk log berdasarkan entitas subject tertentu.
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    /* =========================================================================
     | HELPER METHODS & STATIC AUDITOR
     | ========================================================================= */

    /**
     * Helper Static Method untuk mencatat log audit secara praktis.
     *
     * Contoh Penggunaan:
     * RkaLog::record(
     *     action: RkaLog::ACTION_TRIM_ITEM_VOLUME,
     *     headerId: $rkaHeader->id,
     *     oldValues: ['volume' => 10],
     *     newValues: ['volume' => 5]
     * );
     */
    public static function record(
        string $action,
        ?int $headerId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?Model $subject = null,
        ?string $description = null
    ): static {
        $resolvedUserId = $userId ?? auth()->id();

        if (! $resolvedUserId && $headerId) {
            $header = RkaHeader::query()
                ->with('schedule:id,created_by')
                ->select(['id', 'schedule_id', 'unit_head_user_id', 'reviewer_user_id'])
                ->find($headerId);
            $resolvedUserId = $header?->unit_head_user_id
                ?? $header?->reviewer_user_id
                ?? $header?->schedule?->created_by;
        }

        $resolvedDescription = $description ?? ($newValues['description'] ?? null);

        if (! $resolvedUserId) {
            return new static([
                'rka_header_id' => $headerId,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'action' => $action,
                'description' => is_string($resolvedDescription) ? mb_substr($resolvedDescription, 0, 500) : null,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]);
        }

        return static::create([
            'rka_header_id' => $headerId,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'user_id' => $resolvedUserId,
            'action' => $action,
            'description' => is_string($resolvedDescription) ? mb_substr($resolvedDescription, 0, 500) : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Accessor label deskriptif untuk jenis aktivitas.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_CREATE_RKA => 'Pembuatan RKA',
            self::ACTION_UPDATE_RKA => 'Perubahan RKA',
            self::ACTION_DELETE_RKA => 'Penghapusan RKA',
            self::ACTION_GENERATE_RKA => 'Generate RKA',
            self::ACTION_ADD_INDICATOR => 'Tambah Indikator',
            self::ACTION_ADD_ITEM => 'Tambah Item',
            self::ACTION_UPDATE_ITEM => 'Ubah Item',
            self::ACTION_DELETE_ITEM => 'Hapus Item',
            self::ACTION_CLEAR_ITEMS => 'Hapus Semua Item',
            self::ACTION_IMPORT_ITEMS => 'Impor Item Excel',
            self::ACTION_SUBMIT_RKA => 'Pengajuan RKA',
            self::ACTION_RESUBMIT_RKA => 'Pengajuan Ulang RKA',
            self::ACTION_START_REVIEW => 'Mulai Review',
            self::ACTION_REVIEW_UPDATE => 'Review Item',
            self::ACTION_BULK_APPROVE => 'Approve Massal',
            self::ACTION_TRIM_ITEM_VOLUME => 'Pemangkasan Volume',
            self::ACTION_ADJUSTMENT_MOVE => 'Pemindahan Item',
            self::ACTION_ADD_ADJUSTMENT => 'Tambah Penyesuaian',
            self::ACTION_DELETE_ADJUSTMENT => 'Hapus Penyesuaian',
            self::ACTION_IMPORT_ADJUSTMENT => 'Impor Penyesuaian',
            self::ACTION_FORWARD_DIREKTUR => 'Teruskan ke Direktur',
            self::ACTION_REQUEST_REVISION => 'Permintaan Revisi',
            self::ACTION_APPROVE_RKA => 'Pengesahan Direktur',
            self::ACTION_REJECT_RKA => 'Penolakan RKA',
            self::ACTION_OPEN_LOCK_UNIT => 'Buka Kunci Unit',
            self::ACTION_LOCK_UNIT => 'Kunci Unit',
            self::ACTION_EXPORT_RKA => 'Ekspor Excel RKA',
            self::ACTION_APPROVED_IMPORT_PREVIEW => 'Preview Import RKA Approved',
            self::ACTION_APPROVED_IMPORT_EXECUTED => 'Import RKA Approved',
            self::ACTION_APPROVED_IMPORT_FAILED => 'Gagal Import RKA Approved',
            self::ACTION_APPROVED_REIMPORT_EXECUTED => 'Import Ulang RKA Approved',
            self::ACTION_APPROVED_REIMPORT_FAILED => 'Gagal Import Ulang RKA Approved',
            self::ACTION_UNLOCK_REQUESTED => 'Pengajuan Buka Kunci RKA',
            self::ACTION_UNLOCK_APPROVED => 'Persetujuan Buka Kunci RKA',
            self::ACTION_UNLOCK_REJECTED => 'Penolakan Buka Kunci RKA',
            self::ACTION_UNLOCK_REIMPORT_USED => 'Hak Reimport Digunakan',
            self::ACTION_UNLOCK_REIMPORT_EXPIRED => 'Hak Reimport Kedaluwarsa',
            self::ACTION_RELOCKED => 'RKA Dikunci Kembali',
            default => $this->action,
        };
    }

    /**
     * Accessor deskripsi aktivitas dari kolom description atau fallback metadata new_values.
     */
    public function getDescriptionAttribute(?string $value = null): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $desc = $this->new_values['description'] ?? null;

        return is_string($desc) && $desc !== '' ? $desc : $this->action_label;
    }

    /**
     * Accessor label ringkas untuk entitas terdampak (subject).
     */
    public function getSubjectLabelAttribute(): ?string
    {
        return match ($this->subject_type) {
            RkaHeader::class => 'RKA Header',
            RkaIndicator::class => 'Program / Indikator',
            RkaItem::class => 'Item Anggaran',
            RkaAdjustment::class => 'Penyesuaian',
            RkaApproval::class => 'Persetujuan',
            RkaApprovedImportBatch::class => 'Batch Import Approved',
            RkaUnlockRequest::class => 'Permintaan Buka Kunci',
            default => $this->subject_type ? class_basename($this->subject_type) : null,
        };
    }

    /**
     * Accessor untuk menentukan warna badge jenis aksi pada UI/Filament Table.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_SUBMIT_RKA,
            self::ACTION_RESUBMIT_RKA,
            self::ACTION_APPROVE_RKA,
            self::ACTION_BULK_APPROVE,
            self::ACTION_FORWARD_DIREKTUR,
            self::ACTION_APPROVED_IMPORT_EXECUTED,
            self::ACTION_APPROVED_REIMPORT_EXECUTED,
            self::ACTION_UNLOCK_APPROVED => 'success',
            self::ACTION_TRIM_ITEM_VOLUME,
            self::ACTION_ADJUSTMENT_MOVE,
            self::ACTION_REVIEW_UPDATE,
            self::ACTION_START_REVIEW,
            self::ACTION_ADD_ADJUSTMENT,
            self::ACTION_UNLOCK_REQUESTED,
            self::ACTION_UNLOCK_REIMPORT_EXPIRED => 'warning',
            self::ACTION_REJECT_RKA,
            self::ACTION_REQUEST_REVISION,
            self::ACTION_DELETE_ITEM,
            self::ACTION_CLEAR_ITEMS,
            self::ACTION_DELETE_ADJUSTMENT,
            self::ACTION_DELETE_RKA,
            self::ACTION_LOCK_UNIT,
            self::ACTION_APPROVED_IMPORT_FAILED,
            self::ACTION_APPROVED_REIMPORT_FAILED,
            self::ACTION_UNLOCK_REJECTED => 'danger',
            self::ACTION_OPEN_LOCK_UNIT,
            self::ACTION_CREATE_RKA,
            self::ACTION_GENERATE_RKA,
            self::ACTION_IMPORT_ITEMS,
            self::ACTION_IMPORT_ADJUSTMENT,
            self::ACTION_APPROVED_IMPORT_PREVIEW,
            self::ACTION_UNLOCK_REIMPORT_USED,
            self::ACTION_RELOCKED,
            self::ACTION_EXPORT_RKA => 'info',
            default => 'gray',
        };
    }
}
