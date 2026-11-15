<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserDepartment extends Pivot
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model.
     *
     * @var string
     */
    protected $table = 'user_departments';

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'department_id',
        'assignment_type',
        'start_date',
        'end_date',
    ];

    /**
     * Penataan tipe data atribut (casting).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    /**
     * Relasi balik ke model User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi balik ke model Department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Scope untuk menyaring penugasan Utama (Primary).
     */
    public function scopePrimary($query)
    {
        return $query->where('assignment_type', 'primary');
    }

    /**
     * Scope untuk menyaring penugasan PLT.
     */
    public function scopePlt($query)
    {
        return $query->where('assignment_type', 'plt');
    }

    /**
     * Helper atribut untuk mengecek apakah status PLT masih aktif.
     */
    public function getIsActivePltAttribute(): bool
    {
        if ($this->assignment_type !== 'plt') {
            return false;
        }

        $today = now()->startOfDay();

        $startValid = is_null($this->start_date) || $this->start_date->lte($today);
        $endValid   = is_null($this->end_date) || $this->end_date->gte($today);

        return $startValid && $endValid;
    }
}