<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'code_clean',
        'name',
        'description',
        'managed_by_note',
        'parent_id',
        'parent_code',
        'level',
        'level_label',
        'is_centralized',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'level' => 'integer',
        'is_centralized' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get full display label for Filament selects.
     */
    public function getFullAccountLabelAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /*
    |--------------------------------------------------------------------------
    | Hierarchy Relations (Self-Referencing Parent-Child)
    |--------------------------------------------------------------------------
    */

    /**
     * Account parent (Jenis / Kelompok).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * Account children / sub-accounts.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Transaction Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Department budget allocations for this account.
     */
    public function departmentBudgets(): HasMany
    {
        return $this->hasMany(DepartmentBudget::class);
    }

    /**
     * Fund request items associated with this account.
     */
    public function fundRequestItems(): HasMany
    {
        return $this->hasMany(FundRequestItem::class);
    }
}
