<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'fund_request_id',
        'account_id',
        'description',
        'volume',
        'unit',
        'unit_price',
        'total_price',
        'approved_volume',
        'approved_price',
        'verification_status',
        'finance_note',
    ];

    protected function casts(): array
    {
        return [
            'volume' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'approved_volume' => 'decimal:2',
            'approved_price' => 'decimal:2',
        ];
    }

    /**
     * Boot method for automated total price calculation.
     */
    protected static function booted(): void
    {
        static::saving(function (FundRequestItem $item) {
            $item->total_price = $item->volume * $item->unit_price;
        });
    }

    /**
     * Parent fund request document.
     */
    public function fundRequest(): BelongsTo
    {
        return $this->belongsTo(FundRequest::class);
    }

    /**
     * Account code used for this line item.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}