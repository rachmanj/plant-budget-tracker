<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Component extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'level',
        'equipment_id',
        'component_code',
        'description',
        'status',
        'maintained_by',
        'synced_to_arkfleet',
    ];

    protected function casts(): array
    {
        return [
            'synced_to_arkfleet' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Component::class, 'parent_id');
    }

    public function cannibalRequests(): BelongsToMany
    {
        return $this->belongsToMany(CannibalRequest::class, 'cannibal_request_component');
    }
}
