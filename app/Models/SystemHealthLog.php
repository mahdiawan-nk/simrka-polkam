<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemHealthLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'metadata' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}