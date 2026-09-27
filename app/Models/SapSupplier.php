<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SapSupplier extends Model
{
    protected $fillable = [
        'card_code',
        'card_name',
        'payment_terms',
        'currency',
        'is_active',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
