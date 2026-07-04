<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionTemplate extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'permission_keys',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'permission_keys' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
