<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentBudget extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'account_id',
        'fiscal_year',
        'schedule_id',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
        ];
    }

    /**
     * The department owning this budget allocation.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The chart of account associated with this budget.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The schedule associated with this budget allocation.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RkaSchedule::class);
    }
}