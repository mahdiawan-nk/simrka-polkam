<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relasi langsung ke tabel pivot UserDepartment.
     */
    public function userDepartments(): HasMany
    {
        return $this->hasMany(UserDepartment::class, 'user_id');
    }

    /**
     * Semua department yang terhubung dengan user.
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'user_departments')
            ->using(UserDepartment::class)
            ->withPivot(['assignment_type', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    /**
     * Filter Department Asal / Utama.
     */
    public function primaryDepartment(): BelongsToMany
    {
        return $this->departments()->wherePivot('assignment_type', 'primary');
    }

    /**
     * Filter Department PLT.
     */
    public function pltDepartments(): BelongsToMany
    {
        return $this->departments()->wherePivot('assignment_type', 'plt');
    }
}
