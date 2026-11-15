<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Spatie\Permission\Models\Role;

class UsersImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * Mengonversi setiap baris Excel menjadi Model User.
     */
    public function model(array $row): Model|array|null
    {
        // 1. Simpan atau perbarui User berdasarkan Email
        $user = User::updateOrCreate(
            ['email' => $row['email']],
            [
                'name'              => $row['name'],
                'password'          => Hash::make($row['password']),
                'email_verified_at' => isset($row['email_verified_at']) ? $row['email_verified_at'] : null,
            ]
        );

        // 2. Attach Role (Spatie Permission) berdasarkan Nama Role
        if (! empty($row['role'])) {
            $role = Role::where('name', $row['role'])->first();
            if ($role) {
                $user->syncRoles([$role->name]);
            }
        }

        // 3. Attach Department berdasarkan Kode dengan assignment_type = 'primary'
        if (! empty($row['department'])) {
            $department = Department::where('code', $row['department'])->first();
            if ($department) {
                $user->departments()->syncWithoutDetaching([
                    $department->id => [
                        'assignment_type' => 'primary',
                    ],
                ]);
            }
        }

        return $user;
    }

    /**
     * Validasi untuk setiap kolom pada baris Excel.
     */
    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255'],
            'password'   => ['required', 'max:255'],
            'role'       => ['nullable', 'string', 'exists:roles,name'],
            'department' => ['nullable', 'string', 'exists:departments,code'],
        ];
    }

    /**
     * Pesan kustom untuk error validasi.
     */
    public function customValidationMessages(): array
    {
        return [
            'department.exists' => 'Kode department tidak ditemukan di sistem.',
            'role.exists'       => 'Role tidak ditemukan di sistem.',
        ];
    }
}