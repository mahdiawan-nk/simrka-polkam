<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundRequestVerification extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'fund_request_verifications';

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'fund_request_id',
        'account_id',
        'final_account_id',
        'remaining_budget',
        'amount_approved',
        'status',
        'register_proof_number',
    ];

    /**
     * Penataan tipe data atribut (casting).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'remaining_budget' => 'decimal:2',
        'amount_approved'   => 'decimal:2',
    ];

    /**
     * Relasi balik ke Pengajuan Dana (FundRequest).
     */
    public function fundRequest(): BelongsTo
    {
        return $this->belongsTo(FundRequest::class, 'fund_request_id');
    }

    /**
     * Relasi ke Kode Akun Asal (sebelum dikoreksi).
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Relasi ke Kode Akun Final (setelah dikoreksi oleh keuangan).
     */
    public function finalAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'final_account_id');
    }
}