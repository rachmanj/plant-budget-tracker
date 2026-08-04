<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectCache extends Model
{
    protected $table = 'projects_cache';

    protected $fillable = [
        'project_code',
        'project_name',
        'is_active',
        'selectable_only',
        'raw_payload',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'selectable_only' => 'boolean',
            'raw_payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
