<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
/**
 * Class Department
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $type
 * @property string $code
 * @property string $level_code
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Department extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'departments';

    /**
     * Atribut yang dapat diisi secara masal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_id',
        'type',
        'code',
        'level_code',
        'name',
    ];

    /**
     * Tipe data atribut yang perlu di-cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'parent_id' => 'integer',
        'type' => 'string',
        'code' => 'string',
        'level_code' => 'string',
        'name' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Mutator & Accessor untuk memastikan kode department tersimpan dalam huruf kapital.
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn(string $value) => strtoupper(trim($value)),
        );
    }

    /**
     * Relasi ke Induk Departemen (Parent / Self-Referencing).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * Relasi ke Sub-Departemen / Turunan (Children / Self-Referencing).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }
    /**
     * Budgets allocated to this department.
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(DepartmentBudget::class);
    }

    /**
     * Fund requests submitted by this department.
     */
    public function fundRequests(): HasMany
    {
        return $this->hasMany(FundRequest::class);
    }

    /**
     * Relasi langsung ke tabel user_departments.
     */
    public function userDepartments(): HasMany
    {
        return $this->hasMany(UserDepartment::class, 'department_id');
    }

    /**
     * Semua user di departemen ini.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_departments')
            ->using(UserDepartment::class)
            ->withPivot(['id', 'assignment_type', 'start_date', 'end_date'])
            ->withTimestamps();
    }
    // /**
    //  * Relasi ke User / Pegawai.
    //  */
    // public function users(): HasMany
    // {
    //     return $this->hasMany(User::class);
    // }

    // /**
    //  * Relasi ke Jabatan (Positions) dalam Departemen.
    //  */
    // public function positions(): HasMany
    // {
    //     return $this->hasMany(Position::class);
    // }
}
