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
        'rka_item_id',
        'account_id',
        'rka_account_code_snapshot',
        'rka_account_name_snapshot',
        'rka_approved_volume_snapshot',
        'rka_approved_total_snapshot',
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
            'rka_item_id' => 'integer',
            'rka_approved_volume_snapshot' => 'decimal:2',
            'rka_approved_total_snapshot' => 'decimal:2',
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
     * Approved RKA Item referenced by this line item.
     */
    public function rkaItem(): BelongsTo
    {
        return $this->belongsTo(RkaItem::class, 'rka_item_id');
    }

    /**
     * Account code used for this line item.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
