<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FundRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'request_date',
        'fiscal_year',
        'department_id',
        'rka_header_id',
        'activity_name',
        'execution_date',
        'responsible_person',
        'disbursement_type',
        'recipient_name',
        'bank_account_info',
        'attachments',
        'total_requested',
        'total_approved',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'execution_date' => 'date',
            'attachments' => 'array',
            'total_requested' => 'decimal:2',
            'total_approved' => 'decimal:2',
        ];
    }

    /**
     * Department requesting the fund.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Approved RKA Header used as budget source for this fund request.
     */
    public function rkaHeader(): BelongsTo
    {
        return $this->belongsTo(RkaHeader::class, 'rka_header_id');
    }

    /**
     * User who created the request.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(FundRequestVerification::class, 'fund_request_id');
    }

    /**
     * Itemized details for this fund request.
     */
    public function items(): HasMany
    {
        return $this->hasMany(FundRequestItem::class);
    }

    /**
     * Calculate and sync total requested amount from child items.
     */
    public function recalculateTotals(): void
    {
        $this->total_requested = $this->items()->sum('total_price');
        $this->total_approved = $this->items()->sum('approved_price');
        $this->save();
    }

    /**
     * Semua riwayat/log approval permohonan ini.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(FundRequestApproval::class, 'fund_request_id')->latest();
    }

    /**
     * Log verifikasi Ka BAKKU terakhir.
     */
    public function kabakkuApproval(): HasOne
    {
        return $this->hasOne(FundRequestApproval::class, 'fund_request_id')
            ->where('stage', 'kabakku')
            ->latestOfMany();
    }

    /**
     * Log verifikasi Wadir BAKKU terakhir.
     */
    public function wadirBakkuApproval(): HasOne
    {
        return $this->hasOne(FundRequestApproval::class, 'fund_request_id')
            ->where('stage', 'wadir_bakku')
            ->latestOfMany();
    }

    /**
     * Log verifikasi Direktur terakhir.
     */
    public function directorApproval(): HasOne
    {
        return $this->hasOne(FundRequestApproval::class, 'fund_request_id')
            ->where('stage', 'director')
            ->latestOfMany();
    }
}
